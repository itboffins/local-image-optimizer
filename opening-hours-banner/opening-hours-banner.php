<?php
/**
 * Plugin Name:       Opening Hours by IT Boffins
 * Plugin URI:        https://itboffins.com/plugins/opening-hours-banner/
 * Description:       Show your opening hours and a live "We're open / Closed" banner that stays correct even with page caching. No external services, no account — works on any WordPress host.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            IT Boffins
 * Author URI:        https://itboffins.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       opening-hours-banner
 * Domain Path:       /languages
 *
 * @package Opening_Hours_Banner
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'IBOH_VERSION', '1.0.0' );
define( 'IBOH_FILE', __FILE__ );
define( 'IBOH_DIR', plugin_dir_path( __FILE__ ) );
define( 'IBOH_URL', plugin_dir_url( __FILE__ ) );
define( 'IBOH_BASENAME', plugin_basename( __FILE__ ) );

// Single option key in wp_options.
define( 'IBOH_OPTION', 'iboh_settings' );

require_once IBOH_DIR . 'includes/class-iboh-settings.php';
require_once IBOH_DIR . 'includes/class-iboh-timezone.php';
require_once IBOH_DIR . 'includes/class-iboh-config.php';
require_once IBOH_DIR . 'includes/class-iboh-evaluator.php';
require_once IBOH_DIR . 'includes/class-iboh-frontend.php';
require_once IBOH_DIR . 'includes/class-iboh-shortcode.php';
require_once IBOH_DIR . 'includes/class-iboh-block.php';
require_once IBOH_DIR . 'includes/class-iboh-admin.php';
require_once IBOH_DIR . 'includes/class-iboh-ajax.php';

/**
 * Main plugin bootstrap.
 */
final class IT_Boffins_Opening_Hours {

	/**
	 * Singleton instance.
	 *
	 * @var IT_Boffins_Opening_Hours|null
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return IT_Boffins_Opening_Hours
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire up the plugin.
	 */
	private function __construct() {
		// Front-end banner + asset delivery.
		( new IBOH_Frontend() )->init();

		// Shortcode and block (both safe to register everywhere).
		add_action( 'init', array( new IBOH_Shortcode(), 'init' ) );
		add_action( 'init', array( new IBOH_Block(), 'init' ) );

		// Admin UI + AJAX endpoints.
		if ( is_admin() ) {
			( new IBOH_Admin() )->init();
			( new IBOH_Ajax() )->init();
		}

		load_plugin_textdomain( 'opening-hours-banner', false, dirname( IBOH_BASENAME ) . '/languages' );
	}
}

/**
 * Set sensible defaults on activation.
 */
function iboh_activate() {
	if ( false === get_option( IBOH_OPTION ) ) {
		add_option( IBOH_OPTION, IBOH_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'iboh_activate' );

// Go.
add_action( 'plugins_loaded', array( 'IT_Boffins_Opening_Hours', 'instance' ) );
