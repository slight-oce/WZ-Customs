<?php
/**
 * Plugin Name:       WZ Customs
 * Plugin URI:        https://github.com/slight-oce/wz-customs-plugin
 * Description:       Renders the Warzone customs rank review — promotions, rank bands and per-player breakdowns — from the published data.json.
 * Version:           0.1.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            slight-oce
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       wz-customs
 *
 * @package WZCustoms
 */

defined( 'ABSPATH' ) || exit;

define( 'WZC_VERSION', '0.1.0' );
define( 'WZC_FILE', __FILE__ );
define( 'WZC_DIR', plugin_dir_path( __FILE__ ) );
define( 'WZC_URL', plugin_dir_url( __FILE__ ) );

require_once WZC_DIR . 'includes/class-wzc-privacy.php';
require_once WZC_DIR . 'includes/class-wzc-source.php';
require_once WZC_DIR . 'includes/class-wzc-data.php';
require_once WZC_DIR . 'includes/class-wzc-render.php';
require_once WZC_DIR . 'includes/class-wzc-shortcodes.php';
require_once WZC_DIR . 'includes/class-wzc-settings.php';

/**
 * Boot the plugin once WordPress has loaded.
 */
function wzc_init() {
	WZC_Shortcodes::register();

	if ( is_admin() ) {
		WZC_Settings::register();
	}
}
add_action( 'init', 'wzc_init' );

/**
 * Register front-end assets. They are only enqueued when a shortcode
 * actually renders, so a page with no customs content ships no CSS.
 */
function wzc_register_assets() {
	wp_register_style(
		'wz-customs',
		WZC_URL . 'assets/wz-customs.css',
		array(),
		WZC_VERSION
	);
	wp_register_script(
		'wz-customs',
		WZC_URL . 'assets/wz-customs.js',
		array(),
		WZC_VERSION,
		true
	);
}
add_action( 'wp_enqueue_scripts', 'wzc_register_assets' );

/**
 * Clear the cached payload when the plugin is deactivated, so a site that
 * turns the plugin off is not sitting on a stale copy of the data.
 */
function wzc_deactivate() {
	WZC_Source::flush();
}
register_deactivation_hook( __FILE__, 'wzc_deactivate' );
