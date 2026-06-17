<?php

namespace LicenseBridgeForProfilePress;

defined( 'ABSPATH' ) || exit;

class Product {

	public const OPTION = 'lbfp_product';

	private static function field( string $name, string $default = '' ): string {
		$opt = (array) get_option( self::OPTION, [] );
		$val = isset( $opt[ $name ] ) ? trim( (string) $opt[ $name ] ) : '';
		return '' !== $val ? $val : $default;
	}

	public static function reference(): string {
		if ( defined( 'LBFP_ITEM_REFERENCE' ) && '' !== (string) \LBFP_ITEM_REFERENCE ) {
			return (string) \LBFP_ITEM_REFERENCE;
		}
		return self::field( 'item_reference' );
	}

	public static function name(): string {
		return self::field( 'name', self::reference() );
	}

	public static function slug(): string {
		$slug = self::field( 'slug' );
		if ( '' === $slug ) {
			$slug = sanitize_title( self::name() );
		}
		return '' !== $slug ? $slug : 'licensed-plugin';
	}

	public static function homepage(): string {
		return self::field( 'homepage' );
	}

	public static function author(): string {
		return self::field( 'author' );
	}
}
