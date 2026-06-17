<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class License_Store {

	private const META_KEY     = 'lbfp_license_key';
	private const META_SUB_ID  = 'lbfp_subscription_id';
	private const META_PLAN_ID = 'lbfp_plan_id';

	public function get_key( int $user_id ): string {
		return (string) get_user_meta( $user_id, self::META_KEY, true );
	}

	public function set_key( int $user_id, string $key, int $subscription_id, int $plan_id ): void {
		update_user_meta( $user_id, self::META_KEY,    $key );
		update_user_meta( $user_id, self::META_SUB_ID, $subscription_id );
		update_user_meta( $user_id, self::META_PLAN_ID, $plan_id );
	}

	public function update_plan( int $user_id, int $plan_id ): void {
		update_user_meta( $user_id, self::META_PLAN_ID, $plan_id );
	}

	public function get_subscription_id( int $user_id ): int {
		return (int) get_user_meta( $user_id, self::META_SUB_ID, true );
	}

	public function get_plan_id( int $user_id ): int {
		return (int) get_user_meta( $user_id, self::META_PLAN_ID, true );
	}

	public function find_user_by_key( string $key ): int {
		if ( '' === $key ) return 0;
		$users = get_users( [
			'meta_key'   => self::META_KEY,
			'meta_value' => $key,
			'number'     => 1,
			'fields'     => 'ID',
		] );
		return ! empty( $users ) ? (int) $users[0] : 0;
	}

	public function find_user_by_email( string $email ): int {
		$user = get_user_by( 'email', $email );
		return $user ? (int) $user->ID : 0;
	}
}
