<?php

namespace LicenseBridgeForProfilePress;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class Update_Server {

	private const NAMESPACE   = 'license-bridge-for-profilepress/v1';
	public const OPTION_NAME  = 'lbfp_release';

	private SLM_Client $slm;

	public function __construct( SLM_Client $slm ) {
		$this->slm = $slm;
	}

	public function register(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/update/check', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'check' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'license_key' => [ 'type' => 'string', 'required' => true ],
				'domain'      => [ 'type' => 'string', 'required' => true ],
			],
		] );

		register_rest_route( self::NAMESPACE, '/update/download', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'download' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'license_key' => [ 'type' => 'string', 'required' => true ],
				'domain'      => [ 'type' => 'string', 'required' => true ],
			],
		] );
	}

	public function check( WP_REST_Request $request ): WP_REST_Response {
		$license_key = trim( (string) $request->get_param( 'license_key' ) );
		$domain      = (string) $request->get_param( 'domain' );

		$release = $this->release();
		if ( '' === $release['version'] ) {
			return $this->fail( 'no_release', __( 'No release is currently published.', 'license-bridge-for-profilepress' ), 200 );
		}

		if ( '' === $release['zip_path'] ) {
			return $this->fail( 'no_package', __( 'No release package is configured.', 'license-bridge-for-profilepress' ), 200 );
		}

		$valid = $this->validate_license( $license_key, $domain );
		if ( ! $valid['ok'] ) {
			return $this->fail( $valid['code'], $valid['message'], 403 );
		}

		$download_url = $this->is_url( $release['zip_path'] )
			? $release['zip_path']
			: add_query_arg(
				[
					'license_key' => rawurlencode( $license_key ),
					'domain'      => rawurlencode( $domain ),
				],
				rest_url( self::NAMESPACE . '/update/download' )
			);

		return new WP_REST_Response( [
			'success'      => true,
			'name'         => Product::name(),
			'slug'         => Product::slug(),
			'version'      => $release['version'],
			'requires'     => $release['requires'],
			'tested'       => $release['tested'],
			'requires_php' => $release['requires_php'],
			'last_updated' => $release['last_updated'],
			'homepage'     => Product::homepage(),
			'author'       => Product::author(),
			'sections'     => [
				'description' => $release['description'],
				'changelog'   => $release['changelog'],
			],
			'download_url' => $download_url,
		], 200 );
	}

	public function download( WP_REST_Request $request ): void {
		$license_key = trim( (string) $request->get_param( 'license_key' ) );
		$domain      = (string) $request->get_param( 'domain' );

		$valid = $this->validate_license( $license_key, $domain );
		if ( ! $valid['ok'] ) {
			status_header( 403 );
			wp_die( esc_html( $valid['message'] ), '', [ 'response' => 403 ] );
		}

		$path = $this->release()['zip_path'];

		if ( $this->is_url( $path ) ) {
			// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Admin-configured release URL, not user input.
			wp_redirect( esc_url_raw( $path ) );
			exit;
		}

		if ( '' === $path || ! is_readable( $path ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'Release package not found. Contact support.', 'license-bridge-for-profilepress' ), '', [ 'response' => 404 ] );
		}

		nocache_headers();
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . Product::slug() . '.zip"' );
		header( 'Content-Length: ' . filesize( $path ) );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Streaming a server-local binary; WP_Filesystem buffers the whole file in memory.
		readfile( $path );
		exit;
	}

	private function validate_license( string $license_key, string $domain ): array {
		if ( '' === $license_key ) {
			return [ 'ok' => false, 'code' => 'missing_license', 'message' => __( 'License key is required.', 'license-bridge-for-profilepress' ) ];
		}

		$check   = $this->slm->check( $license_key );
		$payload = is_array( $check['payload'] ?? null ) ? $check['payload'] : [];

		if ( ! $check['ok'] ) {
			return [ 'ok' => false, 'code' => 'license_check_failed', 'message' => $check['message'] ?: __( 'License could not be verified.', 'license-bridge-for-profilepress' ) ];
		}

		if ( 'active' !== strtolower( (string) ( $payload['status'] ?? '' ) ) ) {
			return [ 'ok' => false, 'code' => 'inactive_license', 'message' => __( 'Your license is not active.', 'license-bridge-for-profilepress' ) ];
		}

		if ( ! $this->domain_registered( $payload, $domain ) ) {
			return [ 'ok' => false, 'code' => 'domain_not_registered', 'message' => __( 'This site is not registered to the license. Activate the license here first.', 'license-bridge-for-profilepress' ) ];
		}

		return [ 'ok' => true, 'code' => 'ok', 'message' => '' ];
	}

	private function domain_registered( array $payload, string $domain ): bool {
		$needle = $this->normalize_domain( $domain );
		if ( '' === $needle ) {
			return false;
		}

		$rows = $payload['registered_domains'] ?? [];
		if ( ! is_array( $rows ) ) {
			return false;
		}

		foreach ( $rows as $row ) {
			$candidate = is_array( $row ) ? (string) ( $row['registered_domain'] ?? '' ) : (string) $row;
			if ( '' !== $candidate && $needle === $this->normalize_domain( $candidate ) ) {
				return true;
			}
		}
		return false;
	}

	private function normalize_domain( string $domain ): string {
		$domain = strtolower( trim( $domain ) );
		if ( '' === $domain ) {
			return '';
		}
		$host = wp_parse_url( $domain, PHP_URL_HOST );
		if ( is_string( $host ) && '' !== $host ) {
			$domain = $host;
		}
		return preg_replace( '/^www\./', '', $domain ) ?: '';
	}

	private function release(): array {
		$saved = (array) get_option( self::OPTION_NAME, [] );
		return wp_parse_args( $saved, [
			'version'      => '',
			'requires'     => '',
			'tested'       => '',
			'requires_php' => '',
			'last_updated' => '',
			'description'  => '',
			'changelog'    => '',
			'zip_path'     => '',
		] );
	}

	private function is_url( string $value ): bool {
		return (bool) preg_match( '#^https?://#i', trim( $value ) );
	}

	private function fail( string $code, string $message, int $status ): WP_REST_Response {
		return new WP_REST_Response( [
			'success' => false,
			'code'    => $code,
			'message' => $message,
		], $status );
	}
}
