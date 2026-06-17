<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class Account_Tab {

	private const ENDPOINT = 'licenses';

	private SLM_Client $slm;
	private License_Store $store;

	public function __construct( SLM_Client $slm, License_Store $store ) {
		$this->slm   = $slm;
		$this->store = $store;
	}

	public function register(): void {
		add_filter( 'ppress_myaccount_tabs', [ $this, 'register_tab' ] );
		add_action( 'admin_post_lbfp_deactivate_domain', [ $this, 'handle_deactivate' ] );
	}

	public function register_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) $tabs = [];

		$tabs[ self::ENDPOINT ] = [
			'title'    => __( 'Licenses', 'license-bridge-for-profilepress' ),
			'endpoint' => self::ENDPOINT,
			'priority' => 45,
			'icon'     => 'vpn_key',
			'callback' => [ $this, 'render' ],
		];

		return $tabs;
	}

	public function render(): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			echo '<p>' . esc_html__( 'Please log in to view your licenses.', 'license-bridge-for-profilepress' ) . '</p>';
			return;
		}

		$key = $this->store->get_key( $user_id );

		if ( '' === $key ) {
			echo '<div class="ppmyac-licenses-empty"><p>'
				. esc_html__( 'You don\'t have any active licenses on this account yet.', 'license-bridge-for-profilepress' )
				. '</p></div>';
			return;
		}

		$state   = $this->slm->check( $key );
		$payload = is_array( $state['payload'] ?? null ) ? $state['payload'] : [];
		$status  = (string) ( $payload['status']  ?? 'unknown' );
		$expires = (string) ( $payload['date_expiry'] ?? '' );
		$max     = (int)    ( $payload['max_allowed_domains'] ?? 0 );
		$domains = $this->extract_domains( $payload );

		$flash = isset( $_GET['lbfp_msg'] ) ? sanitize_text_field( wp_unslash( $_GET['lbfp_msg'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$view = LBFP_DIR . 'views/account-licenses.php';
		if ( file_exists( $view ) ) {
			include $view;
		}
	}

	public function handle_deactivate(): void {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url() );
			exit;
		}

		$user_id = get_current_user_id();
		$domain  = isset( $_POST['domain'] ) ? sanitize_text_field( wp_unslash( $_POST['domain'] ) ) : '';
		$nonce   = isset( $_POST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'lbfp_deactivate_domain' ) || '' === $domain ) {
			$this->redirect_back( 'invalid' );
		}

		$key = $this->store->get_key( $user_id );
		if ( '' === $key ) {
			$this->redirect_back( 'no_license' );
		}

		$resp = $this->slm->deactivate_domain( $key, $domain );
		$this->redirect_back( $resp['ok'] ? 'deactivated' : 'failed' );
	}

	private function redirect_back( string $msg ): void {
		$base = class_exists( '\\ProfilePress\\Core\\ShortcodeParser\\MyAccount\\MyAccountTag' )
			? \ProfilePress\Core\ShortcodeParser\MyAccount\MyAccountTag::get_endpoint_url( self::ENDPOINT )
			: home_url( '/' );

		wp_safe_redirect( add_query_arg( 'lbfp_msg', $msg, $base ) );
		exit;
	}

	private function extract_domains( array $payload ): array {
		$rows = $payload['registered_domains'] ?? [];
		if ( ! is_array( $rows ) ) return [];

		$out = [];
		foreach ( $rows as $row ) {
			if ( is_array( $row ) && isset( $row['registered_domain'] ) ) {
				$out[] = (string) $row['registered_domain'];
			} elseif ( is_string( $row ) ) {
				$out[] = $row;
			}
		}
		return $out;
	}
}
