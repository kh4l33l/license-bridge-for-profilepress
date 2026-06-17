<?php
/**
 * Plugin Name: License Bridge for ProfilePress
 * Plugin URI:  https://ibrahim.ng/license-bridge-for-profilepress
 * Description: Bridges ProfilePress paid memberships to the Software License Manager (SLM). Auto-provisions licenses on subscription activation, exposes license info to ProfilePress emails and the My Account page, and serves license-gated plugin updates to customer sites.
 * Version:     0.0.1
 * Author:      Ibrahim Nasir
 * Author URI:  https://ibrahim.ng
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: license-bridge-for-profilepress
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: wp-user-avatar, software-license-manager
 */

defined( 'ABSPATH' ) || exit;

define( 'LBFP_VERSION', '0.0.1' );
define( 'LBFP_DIR',     plugin_dir_path( __FILE__ ) );
define( 'LBFP_URL',     plugin_dir_url( __FILE__ ) );
define( 'LBFP_FILE',    __FILE__ );

if ( ! defined( 'LBFP_SLM_URL' ) ) {
	define( 'LBFP_SLM_URL', home_url() );
}

// SLM secrets are wp-config-only — no defaults, so they never reach VCS.
if ( ! defined( 'LBFP_SLM_CREATION_SECRET' ) ) {
	define( 'LBFP_SLM_CREATION_SECRET', '' );
}
if ( ! defined( 'LBFP_SLM_VERIFICATION_SECRET' ) ) {
	define( 'LBFP_SLM_VERIFICATION_SECRET', '' );
}

spl_autoload_register( function ( string $class ): void {
	$prefix   = 'LicenseBridgeForProfilePress\\';
	$base_dir = LBFP_DIR . 'includes/';

	if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative   = substr( $class, strlen( $prefix ) );
	$parts      = explode( '\\', $relative );
	$class_name = array_pop( $parts );
	$file_name  = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';
	$sub_path   = $parts ? implode( '/', array_map( 'strtolower', $parts ) ) . '/' : '';
	$file       = $base_dir . $sub_path . $file_name;

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

LicenseBridgeForProfilePress\Migrator::maybe_migrate();

add_action( 'plugins_loaded', function (): void {
	if ( ! class_exists( 'ProfilePress\\Core\\Membership\\Models\\Subscription\\SubscriptionEntity' ) ) {
		add_action( 'admin_notices', function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) return;
			echo '<div class="notice notice-error"><p><strong>License Bridge for ProfilePress:</strong> ProfilePress is required.</p></div>';
		} );
		return;
	}

	LicenseBridgeForProfilePress\Bridge::get_instance();
}, 20 );
