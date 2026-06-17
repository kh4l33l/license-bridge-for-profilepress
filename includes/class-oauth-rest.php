<?php

namespace LicenseBridgeForProfilePress;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

class OAuth_REST {

	private const CODE_TTL  = 600;
	private const ACTION_AUTHORIZE = 'lbfp_oauth_authorize';

	private SLM_Client $slm;
	private License_Store $store;

	public function __construct( SLM_Client $slm, License_Store $store ) {
		$this->slm   = $slm;
		$this->store = $store;
	}

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );

		foreach ( self::authorize_actions() as $action ) {
			add_action( 'admin_post_' . $action, array( $this, 'authorize_admin' ) );
			add_action( 'admin_post_nopriv_' . $action, array( $this, 'authorize_admin' ) );
		}
	}

	private static function authorize_actions(): array {
		return array_values( array_unique( (array) apply_filters( 'lbfp_oauth_authorize_actions', array( self::ACTION_AUTHORIZE ) ) ) );
	}

	public function register_routes(): void {
		foreach ( Update_Server::namespaces() as $namespace ) {
			register_rest_route(
				$namespace,
				'/oauth/authorize',
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'authorize' ),
					'permission_callback' => '__return_true',
				)
			);

			register_rest_route(
				$namespace,
				'/oauth/token',
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'token' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'code'     => array(
							'type'     => 'string',
							'required' => true,
						),
						'site_url' => array(
							'type'     => 'string',
							'required' => true,
						),
					),
				)
			);
		}
	}

	public function authorize( WP_REST_Request $request ): void {
		$redirect_uri = $this->sanitize_url_param( (string) $request->get_param( 'redirect_uri' ) );
		$site_url     = $this->sanitize_url_param( (string) $request->get_param( 'site_url' ) );
		$state        = sanitize_text_field( (string) $request->get_param( 'state' ) );

		$this->authorize_request( $redirect_uri, $site_url, $state );
	}

	public function authorize_admin(): void {
		$redirect_uri = isset( $_GET['redirect_uri'] ) ? $this->sanitize_url_param( (string) wp_unslash( $_GET['redirect_uri'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$site_url     = isset( $_GET['site_url'] ) ? $this->sanitize_url_param( (string) wp_unslash( $_GET['site_url'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state        = isset( $_GET['state'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['state'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$this->authorize_request( $redirect_uri, $site_url, $state );
	}

	private function authorize_request( string $redirect_uri, string $site_url, string $state ): void {
		if ( '' === $redirect_uri || '' === $site_url || '' === $state ) {
			$this->redirect_error( $redirect_uri, $state, 'missing_params', __( 'The account connection request is incomplete.', 'license-bridge-for-profilepress' ) );
		}

		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( $this->authorize_url( $redirect_uri, $site_url, $state ) ) );
			exit;
		}

		$user_id = get_current_user_id();
		$key     = $this->store->get_key( $user_id );

		if ( '' === $key ) {
			$this->redirect_error( $redirect_uri, $state, 'no_license', __( 'No license is attached to this account.', 'license-bridge-for-profilepress' ) );
		}

		$check   = $this->slm->check( $key );
		$payload = is_array( $check['payload'] ?? null ) ? $check['payload'] : array();
		$status  = strtolower( (string) ( $payload['status'] ?? '' ) );

		if ( ! $check['ok'] || 'active' !== $status ) {
			$this->redirect_error( $redirect_uri, $state, 'inactive_license', __( 'Your license is not active.', 'license-bridge-for-profilepress' ) );
		}

		$code = wp_generate_password( 48, false, false );
		set_transient(
			$this->code_transient_key( $code ),
			array(
				'user_id'     => $user_id,
				'license_key' => $key,
				'site_url'    => $this->normalize_site_url( $site_url ),
				'created_at'  => time(),
			),
			self::CODE_TTL
		);

		$this->external_redirect(
			add_query_arg(
				array(
					'code'  => $code,
					'state' => $state,
				),
				$redirect_uri
			)
		);
	}

	public function token( WP_REST_Request $request ): WP_REST_Response {
		$code     = sanitize_text_field( (string) $request->get_param( 'code' ) );
		$site_url = $this->sanitize_url_param( (string) $request->get_param( 'site_url' ) );

		if ( '' === $code || '' === $site_url ) {
			return $this->error_response( 'missing_params', __( 'The token request is incomplete.', 'license-bridge-for-profilepress' ) );
		}

		$key  = $this->code_transient_key( $code );
		$data = get_transient( $key );

		if ( ! is_array( $data ) ) {
			return $this->error_response( 'invalid_code', __( 'The connection code is invalid or has expired.', 'license-bridge-for-profilepress' ) );
		}

		if ( $this->normalize_site_url( $site_url ) !== (string) ( $data['site_url'] ?? '' ) ) {
			return $this->error_response( 'site_mismatch', __( 'The connection code does not belong to this site.', 'license-bridge-for-profilepress' ) );
		}

		delete_transient( $key );

		$license_key = (string) ( $data['license_key'] ?? '' );
		if ( '' === $license_key ) {
			return $this->error_response( 'missing_license', __( 'No license key is available for this account.', 'license-bridge-for-profilepress' ) );
		}

		return new WP_REST_Response(
			array(
				'success'     => true,
				'license_key' => $license_key,
			)
		);
	}

	private function redirect_error( string $redirect_uri, string $state, string $code, string $message ): void {
		if ( '' === $redirect_uri ) {
			wp_die( esc_html( $message ) );
		}

		$this->external_redirect(
			add_query_arg(
				array(
					'error'         => $code,
					'error_message' => $message,
					'state'         => $state,
				),
				$redirect_uri
			)
		);
	}

	private function error_response( string $code, string $message ): WP_REST_Response {
		return new WP_REST_Response(
			array(
				'success' => false,
				'code'    => $code,
				'message' => $message,
			),
			400
		);
	}

	private function sanitize_url_param( string $url ): string {
		$url = esc_url_raw( rawurldecode( $url ) );
		if ( '' === $url ) {
			return '';
		}

		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $url, PHP_URL_HOST );

		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) || ! is_string( $host ) || '' === $host ) {
			return '';
		}

		return $url;
	}

	private function normalize_site_url( string $url ): string {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		if ( ! is_string( $host ) || '' === $host ) {
			return '';
		}

		return preg_replace( '/^www\./', '', strtolower( $host ) ) ?: '';
	}

	private function code_transient_key( string $code ): string {
		return 'lbfp_oauth_' . hash( 'sha256', $code );
	}

	private function authorize_url( string $redirect_uri, string $site_url, string $state ): string {
		return add_query_arg(
			array(
				'action'       => self::ACTION_AUTHORIZE,
				'redirect_uri' => $redirect_uri,
				'site_url'     => $site_url,
				'state'        => $state,
			),
			admin_url( 'admin-post.php' )
		);
	}

	private function external_redirect( string $url ): void {
		// phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- Customer-site callback URL is validated before this intentional external redirect.
		wp_redirect( esc_url_raw( $url ) );
		exit;
	}
}
