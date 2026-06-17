<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class Email_Placeholders {

	private License_Store $store;

	public function __construct( License_Store $store ) {
		$this->store = $store;
	}

	public function register(): void {
		add_filter( 'ppress_order_placeholders_values',        [ $this, 'add_for_order' ],        20, 3 );
		add_filter( 'ppress_subscription_placeholders_values', [ $this, 'add_for_subscription' ], 20, 1 );
	}

	public function add_for_order( $values, $order = null, $adminview = false ) {
		$user_id = 0;
		if ( is_object( $order ) && isset( $order->customer_id ) ) {
			$customer = \ProfilePress\Core\Membership\Models\Customer\CustomerFactory::fromId( (int) $order->customer_id );
			if ( $customer ) {
				$user_id = (int) $customer->get_user_id();
			}
		}

		return $this->merge_license_values( $values, $user_id );
	}

	public function add_for_subscription( $values ) {
		$email   = is_array( $values ) ? (string) ( $values['{{email}}'] ?? '' ) : '';
		$user_id = '' !== $email ? $this->store->find_user_by_email( $email ) : 0;

		return $this->merge_license_values( (array) $values, $user_id );
	}

	private function merge_license_values( array $values, int $user_id ): array {
		$key = $user_id > 0 ? $this->store->get_key( $user_id ) : '';
		$values['{{license_key}}']         = $key !== '' ? $key : '—';
		$values['{{license_account_url}}'] = esc_url( wp_login_url() );
		return $values;
	}
}
