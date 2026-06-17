<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class SLM_Reader {

	private function keys_table(): string {
		global $wpdb;
		return defined( 'SLM_TBL_LICENSE_KEYS' ) ? SLM_TBL_LICENSE_KEYS : $wpdb->prefix . 'lic_key_tbl';
	}

	private function domains_table(): string {
		global $wpdb;
		return defined( 'SLM_TBL_LIC_DOMAIN' ) ? SLM_TBL_LIC_DOMAIN : $wpdb->prefix . 'lic_reg_domain_tbl';
	}

	public function is_available(): bool {
		global $wpdb;
		$table = $this->keys_table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		return (string) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) === $table;
	}

	public function licenses_by_keys( array $keys ): array {
		$keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
		if ( empty( $keys ) ) {
			return [];
		}

		global $wpdb;
		$table        = $this->keys_table();
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT * FROM {$table} WHERE license_key IN ({$placeholders})", $keys ),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$out[ (string) $row['license_key'] ] = $row;
		}
		return $out;
	}

	public function domains_by_keys( array $keys ): array {
		$keys = array_values( array_unique( array_filter( array_map( 'strval', $keys ) ) ) );
		if ( empty( $keys ) ) {
			return [];
		}

		global $wpdb;
		$table        = $this->domains_table();
		$placeholders = implode( ',', array_fill( 0, count( $keys ), '%s' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL
		$rows = $wpdb->get_results(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->prepare( "SELECT lic_key, registered_domain FROM {$table} WHERE lic_key IN ({$placeholders})", $keys ),
			ARRAY_A
		);

		$out = [];
		foreach ( (array) $rows as $row ) {
			$key    = (string) $row['lic_key'];
			$domain = (string) $row['registered_domain'];
			if ( '' === $domain ) {
				continue;
			}
			$out[ $key ][] = $domain;
		}
		return $out;
	}
}
