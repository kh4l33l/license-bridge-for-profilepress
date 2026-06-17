<?php

namespace LicenseBridgeForProfilePress;

use ProfilePress\Core\Membership\Models\Customer\CustomerFactory;
use ProfilePress\Core\Membership\Models\Order\OrderEntity;
use ProfilePress\Core\Membership\Models\Subscription\SubscriptionEntity;
use ProfilePress\Core\Membership\Models\Subscription\SubscriptionFactory;

defined( 'ABSPATH' ) || exit;

class Subscription_Handler {

	private SLM_Client $slm;
	private License_Store $store;

	public function __construct( SLM_Client $slm, License_Store $store ) {
		$this->slm   = $slm;
		$this->store = $store;
	}

	public function register(): void {
		add_action( 'ppress_order_completed',         [ $this, 'on_order_completed' ], 5, 1 );
		add_action( 'ppress_subscription_activated',  [ $this, 'on_activated' ],       5, 1 );
		add_action( 'ppress_subscription_post_renew', [ $this, 'on_renewed' ],         5, 3 );
		add_action( 'ppress_subscription_expired',    [ $this, 'on_expired' ],         5, 1 );
	}

	public function on_order_completed( OrderEntity $order ): void {
		$subscription_id = (int) $order->get_subscription_id();
		if ( $subscription_id <= 0 ) return;

		$sub = SubscriptionFactory::fromId( $subscription_id );
		if ( ! $sub || ! ( $sub instanceof SubscriptionEntity ) || (int) $sub->id <= 0 ) {
			return;
		}

		$this->on_activated( $sub );
	}

	public function on_activated( SubscriptionEntity $sub ): void {
		$user_id = (int) $sub->customer_id;
		$customer = CustomerFactory::fromId( $user_id );
		$wp_user_id = $customer ? (int) $customer->get_user_id() : $user_id;
		if ( $wp_user_id <= 0 ) return;

		$plan_id     = (int) $sub->plan_id;
		$max_domains = $this->max_domains_for_plan( $plan_id );
		if ( 0 === $max_domains ) {
			return;
		}

		$existing_key = $this->store->get_key( $wp_user_id );
		$expiry       = $this->normalize_expiry( $sub->expiration_date );

		if ( '' !== $existing_key ) {
			$this->slm->update( $existing_key, [
				'max_allowed_domains' => $max_domains,
				'date_expiry'         => $expiry,
				'lic_status'          => 'active',
				'subscr_id'           => (string) $sub->id,
			] );
			$this->store->update_plan( $wp_user_id, $plan_id );
			Admin_Dashboard::flush_cache();
			return;
		}

		$user = get_userdata( $wp_user_id );
		if ( ! $user ) return;

		$resp = $this->slm->create( [
			'first_name'          => $customer ? $customer->get_first_name() : $user->first_name,
			'last_name'           => $customer ? $customer->get_last_name()  : $user->last_name,
			'email'               => $user->user_email,
			'max_allowed_domains' => $max_domains,
			'date_expiry'         => $expiry,
			'subscr_id'           => (string) $sub->id,
			'lic_status'          => 'active',
		] );

		if ( ! $resp['ok'] ) return;

		$key = (string) ( $resp['payload']['key'] ?? '' );
		if ( '' === $key ) return;

		$this->store->set_key( $wp_user_id, $key, (int) $sub->id, $plan_id );
		Admin_Dashboard::flush_cache();
	}

	public function on_renewed( int $subscription_id, string $expiration_datetime, SubscriptionEntity $sub ): void {
		$customer = CustomerFactory::fromId( (int) $sub->customer_id );
		$user_id  = $customer ? (int) $customer->get_user_id() : (int) $sub->customer_id;
		if ( $user_id <= 0 ) return;

		$key = $this->store->get_key( $user_id );
		if ( '' === $key ) {
			$this->on_activated( $sub );
			return;
		}

		$this->slm->update( $key, [
			'date_expiry' => $this->normalize_expiry( $expiration_datetime ),
			'lic_status'  => 'active',
		] );
		Admin_Dashboard::flush_cache();
	}

	public function on_expired( SubscriptionEntity $sub ): void {
		$customer = CustomerFactory::fromId( (int) $sub->customer_id );
		$user_id  = $customer ? (int) $customer->get_user_id() : (int) $sub->customer_id;
		if ( $user_id <= 0 ) return;

		$key = $this->store->get_key( $user_id );
		if ( '' === $key ) return;

		$this->slm->update( $key, [
			'date_expiry' => gmdate( 'Y-m-d' ),
			'lic_status'  => 'expired',
		] );
		Admin_Dashboard::flush_cache();
	}

	private function max_domains_for_plan( int $plan_id ): int {
		$map = (array) get_option( 'lbfp_plan_map', [] );
		return isset( $map[ $plan_id ] ) ? max( 0, (int) $map[ $plan_id ] ) : 0;
	}

	private function normalize_expiry( $value ): string {
		if ( empty( $value ) || '0000-00-00 00:00:00' === $value ) {
			return '';
		}
		$ts = is_numeric( $value ) ? (int) $value : strtotime( (string) $value );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}
}
