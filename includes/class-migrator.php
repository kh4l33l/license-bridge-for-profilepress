<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class Migrator {

	private const FLAG = 'lbfp_migrated';

	private const OPTIONS = [
		'flowsync_lb_plan_map' => 'lbfp_plan_map',
		'flowsync_lb_release'  => 'lbfp_release',
	];

	private const META = [
		'flowsync_lb_license_key'     => 'lbfp_license_key',
		'flowsync_lb_subscription_id' => 'lbfp_subscription_id',
		'flowsync_lb_plan_id'         => 'lbfp_plan_id',
	];

	public static function maybe_migrate(): void {
		if ( get_option( self::FLAG ) ) {
			return;
		}

		self::migrate_options();
		self::migrate_meta();

		update_option( self::FLAG, LBFP_VERSION, false );
	}

	private static function migrate_options(): void {
		foreach ( self::OPTIONS as $old => $new ) {
			if ( false === get_option( $new, false ) ) {
				$value = get_option( $old, null );
				if ( null !== $value ) {
					update_option( $new, $value );
				}
			}
			delete_option( $old );
		}
	}

	private static function migrate_meta(): void {
		global $wpdb;
		foreach ( self::META as $old => $new ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$wpdb->usermeta} m
					 SET m.meta_key = %s
					 WHERE m.meta_key = %s
					   AND NOT EXISTS (
					       SELECT 1 FROM ( SELECT * FROM {$wpdb->usermeta} ) x
					       WHERE x.user_id = m.user_id AND x.meta_key = %s
					   )",
					$new,
					$old,
					$new
				)
			);
		}
		wp_cache_flush();
	}
}
