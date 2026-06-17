<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class Admin_Dashboard {

	private const META_KEY     = 'lbfp_license_key';
	private const META_SUB_ID  = 'lbfp_subscription_id';
	private const META_PLAN_ID = 'lbfp_plan_id';

	private const CACHE_KEY = 'lbfp_dashboard_rows';
	private const CACHE_TTL = 120;

	private SLM_Reader $reader;

	public function __construct( ?SLM_Reader $reader = null ) {
		$this->reader = $reader ?? new SLM_Reader();
	}

	public function register(): void {
		add_action( 'admin_menu', [ $this, 'menu' ] );
		add_action( 'admin_post_lbfp_refresh_dashboard', [ $this, 'handle_refresh' ] );
	}

	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}

	public function menu(): void {
		add_submenu_page(
			'ppress-dashboard',
			__( 'License Dashboard', 'license-bridge-for-profilepress' ),
			__( 'License Dashboard', 'license-bridge-for-profilepress' ),
			'manage_options',
			'lbfp-license-dashboard',
			[ $this, 'render' ]
		);
	}

	public function handle_refresh(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'license-bridge-for-profilepress' ) );
		}
		check_admin_referer( 'lbfp_refresh_dashboard' );
		delete_transient( self::CACHE_KEY );
		wp_safe_redirect( wp_get_referer() ?: admin_url( 'admin.php?page=lbfp-license-dashboard' ) );
		exit;
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$available = $this->reader->is_available();
		$rows      = $available ? $this->get_rows() : [];

		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search        = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$filtered = $this->apply_filters( $rows, $status_filter, $search );
		$stats    = $this->summarize( $rows );

		$view = LBFP_DIR . 'views/admin-dashboard.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	private function get_rows(): array {
		$cached = get_transient( self::CACHE_KEY );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$users = get_users( [
			'meta_key'     => self::META_KEY,
			'meta_value'   => '',
			'meta_compare' => '!=',
			'fields'       => [ 'ID', 'display_name', 'user_email' ],
		] );

		if ( empty( $users ) ) {
			set_transient( self::CACHE_KEY, [], self::CACHE_TTL );
			return [];
		}

		$keys     = [];
		$user_map = [];
		foreach ( $users as $user ) {
			$uid = (int) $user->ID;
			$key = (string) get_user_meta( $uid, self::META_KEY, true );
			if ( '' === $key ) {
				continue;
			}
			$keys[]           = $key;
			$user_map[ $key ] = $user;
		}

		$licenses  = $this->reader->licenses_by_keys( $keys );
		$domains   = $this->reader->domains_by_keys( $keys );
		$plan_name = $this->plan_name_map();

		$rows = [];
		foreach ( $keys as $key ) {
			$user    = $user_map[ $key ];
			$uid     = (int) $user->ID;
			$lic     = $licenses[ $key ] ?? [];
			$sites   = $domains[ $key ] ?? [];
			$plan_id = (int) get_user_meta( $uid, self::META_PLAN_ID, true );
			$max     = isset( $lic['max_allowed_domains'] ) ? (int) $lic['max_allowed_domains'] : 0;

			$rows[] = [
				'user_id'      => $uid,
				'customer'     => (string) $user->display_name,
				'email'        => (string) $user->user_email,
				'plan_id'      => $plan_id,
				'plan'         => $plan_name[ $plan_id ] ?? ( $plan_id > 0 ? sprintf( '#%d', $plan_id ) : '—' ),
				'subscr_id'    => (string) get_user_meta( $uid, self::META_SUB_ID, true ),
				'license_key'  => $key,
				'status'       => $this->derive_status( $lic ),
				'date_created' => $this->clean_date( $lic['date_created'] ?? '' ),
				'date_expiry'  => $this->clean_date( $lic['date_expiry'] ?? '' ),
				'max_domains'  => $max,
				'install_count'=> count( $sites ),
				'domains'      => $sites,
				'in_slm'       => ! empty( $lic ),
			];
		}

		usort( $rows, static fn( $a, $b ) => $b['install_count'] <=> $a['install_count'] );

		set_transient( self::CACHE_KEY, $rows, self::CACHE_TTL );
		return $rows;
	}

	private function apply_filters( array $rows, string $status, string $search ): array {
		$search = strtolower( trim( $search ) );

		return array_values( array_filter( $rows, static function ( array $r ) use ( $status, $search ): bool {
			if ( '' !== $status && $r['status'] !== $status ) {
				return false;
			}
			if ( '' === $search ) {
				return true;
			}
			$haystack = strtolower( implode( ' ', array_merge(
				[ $r['customer'], $r['email'], $r['license_key'], $r['plan'] ],
				$r['domains']
			) ) );
			return false !== strpos( $haystack, $search );
		} ) );
	}

	private function summarize( array $rows ): array {
		$stats = [
			'licenses'   => count( $rows ),
			'active'     => 0,
			'expired'    => 0,
			'other'      => 0,
			'installs'   => 0,
			'seats'      => 0,
			'at_capacity'=> 0,
		];

		foreach ( $rows as $r ) {
			$stats['installs'] += (int) $r['install_count'];
			$stats['seats']    += (int) $r['max_domains'];

			if ( 'active' === $r['status'] ) {
				$stats['active']++;
			} elseif ( 'expired' === $r['status'] ) {
				$stats['expired']++;
			} else {
				$stats['other']++;
			}

			if ( $r['max_domains'] > 0 && $r['install_count'] >= $r['max_domains'] ) {
				$stats['at_capacity']++;
			}
		}

		return $stats;
	}

	private function derive_status( array $lic ): string {
		if ( empty( $lic ) ) {
			return 'missing';
		}
		$status = strtolower( (string) ( $lic['lic_status'] ?? '' ) );
		return '' !== $status ? $status : 'unknown';
	}

	private function clean_date( $value ): string {
		$value = (string) $value;
		return ( '' === $value || '0000-00-00' === $value ) ? '' : $value;
	}

	private function plan_name_map(): array {
		if ( ! class_exists( '\\ProfilePress\\Core\\DBTables' ) ) {
			return [];
		}
		global $wpdb;
		$table = \ProfilePress\Core\DBTables::subscription_plans_db_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows  = $wpdb->get_results( "SELECT id, name FROM {$table}", ARRAY_A );

		$map = [];
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row['id'] ] = (string) $row['name'];
		}
		return $map;
	}
}
