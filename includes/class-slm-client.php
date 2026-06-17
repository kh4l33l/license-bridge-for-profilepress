<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class SLM_Client {

	public function create( array $args ): array {
		$args = wp_parse_args( $args, [
			'first_name'          => '',
			'last_name'           => '',
			'email'               => '',
			'company_name'        => '',
			'max_allowed_domains' => 1,
			'date_expiry'         => '',
			'subscr_id'           => '',
		] );

		return $this->post( 'slm_create_new', array_filter( $args, static fn( $v ) => '' !== $v && null !== $v ) );
	}

	public function update( string $license_key, array $fields ): array {
		$fields['license_key'] = $license_key;
		return $this->post( 'slm_update', $fields );
	}

	public function check( string $license_key ): array {
		return $this->get( 'slm_check', [ 'license_key' => $license_key ] );
	}

	public function deactivate_domain( string $license_key, string $domain ): array {
		return $this->get( 'slm_deactivate', [
			'license_key'       => $license_key,
			'registered_domain' => $domain,
		] );
	}

	private function get( string $action, array $params ): array {
		$params = $this->with_auth( $action, $params );
		$url    = add_query_arg( $params, LBFP_SLM_URL );
		$resp   = wp_remote_get( esc_url_raw( $url ), [ 'timeout' => 20, 'sslverify' => true ] );
		return $this->parse( $resp );
	}

	private function post( string $action, array $params ): array {
		$params = $this->with_auth( $action, $params );
		$resp   = wp_remote_post( LBFP_SLM_URL, [
			'timeout'   => 20,
			'sslverify' => true,
			'body'      => $params,
		] );
		return $this->parse( $resp );
	}

	private function with_auth( string $action, array $params ): array {
		return array_merge( $params, [
			'slm_action'     => $action,
			'secret_key'     => $this->secret_for( $action ),
			'item_reference' => Product::reference(),
		] );
	}

	private function secret_for( string $action ): string {
		$creation_actions = [ 'slm_create_new', 'slm_update' ];
		return in_array( $action, $creation_actions, true )
			? (string) LBFP_SLM_CREATION_SECRET
			: (string) LBFP_SLM_VERIFICATION_SECRET;
	}

	private function parse( $response ): array {
		if ( is_wp_error( $response ) ) {
			$out = [ 'ok' => false, 'message' => $response->get_error_message(), 'payload' => [] ];
			$this->log_failure( $out );
			return $out;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			$out = [ 'ok' => false, 'message' => 'Unexpected response from SLM.', 'payload' => [ 'raw' => $body ] ];
			$this->log_failure( $out );
			return $out;
		}

		$result  = (string) ( $data['result']  ?? '' );
		$message = (string) ( $data['message'] ?? '' );

		$out = [
			'ok'      => 'success' === $result,
			'message' => $message,
			'payload' => $data,
		];

		if ( ! $out['ok'] ) {
			$this->log_failure( $out );
		}

		return $out;
	}

	private function log_failure( array $out ): void {
		if ( ! ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ) return;
		error_log( '[License Bridge for ProfilePress] SLM call failed: ' . wp_json_encode( $out ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
