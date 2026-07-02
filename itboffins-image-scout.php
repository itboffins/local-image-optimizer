<?php
/**
 * Plugin Name:       ITBoffins Image Scout
 * Plugin URI:        https://itboffins.com/plugins/itboffins-image-scout/
 * Description:       Find images your Media Library misses, then compress JPEG/PNG and create WebP locally for Media Library, page-builder, and theme images. No external API, account, or shell access.
 * Version:           1.0.8
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            IT Boffins
 * Author URI:        https://itboffins.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       itboffins-image-scout
 * Domain Path:       /languages
 *
 * @package ITBoffins_Image_Scout
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ITBOFFINS_IMAGE_SCOUT_VERSION', '1.0.8' );
define( 'ITBOFFINS_IMAGE_SCOUT_FILE', __FILE__ );
define( 'ITBOFFINS_IMAGE_SCOUT_DIR', plugin_dir_path( __FILE__ ) );
define( 'ITBOFFINS_IMAGE_SCOUT_URL', plugin_dir_url( __FILE__ ) );
define( 'ITBOFFINS_IMAGE_SCOUT_BASENAME', plugin_basename( __FILE__ ) );

// Option key in wp_options and the per-attachment meta key.
define( 'ITBOFFINS_IMAGE_SCOUT_OPTION', 'itboffins_image_scout_settings' );
define( 'ITBOFFINS_IMAGE_SCOUT_META', '_itboffins_image_scout_stats' );

// Sub-folder inside /uploads where untouched originals are backed up.
define( 'ITBOFFINS_IMAGE_SCOUT_BACKUP_DIR', 'itboffins-image-scout-originals' );

require_once ITBOFFINS_IMAGE_SCOUT_DIR . 'includes/class-itboffins-image-scout-settings.php';
require_once ITBOFFINS_IMAGE_SCOUT_DIR . 'includes/class-itboffins-image-scout-capabilities.php';
require_once ITBOFFINS_IMAGE_SCOUT_DIR . 'includes/class-itboffins-image-scout-optimizer.php';
require_once ITBOFFINS_IMAGE_SCOUT_DIR . 'includes/class-itboffins-image-scout-scanner.php';
require_once ITBOFFINS_IMAGE_SCOUT_DIR . 'includes/class-itboffins-image-scout-frontend.php';
require_once ITBOFFINS_IMAGE_SCOUT_DIR . 'includes/class-itboffins-image-scout-admin.php';
require_once ITBOFFINS_IMAGE_SCOUT_DIR . 'includes/class-itboffins-image-scout-ajax.php';

/**
 * Main plugin bootstrap.
 */
final class ITBoffins_Image_Scout {

	/**
	 * Singleton instance.
	 *
	 * @var ITBoffins_Image_Scout|null
	 */
	private static $instance = null;

	/**
	 * Shared optimizer instance.
	 *
	 * @var ITBOFFINS_IMAGE_SCOUT_Optimizer
	 */
	public $optimizer;

	/**
	 * Get the singleton instance.
	 *
	 * @return ITBoffins_Image_Scout
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
		$this->optimizer = new ITBOFFINS_IMAGE_SCOUT_Optimizer();

		// Optimize images automatically as they are processed by WordPress.
		add_filter( 'wp_generate_attachment_metadata', array( $this->optimizer, 'maybe_auto_optimize' ), 20, 2 );

		// Front-end WebP delivery.
		add_action( 'init', array( new ITBOFFINS_IMAGE_SCOUT_Frontend(), 'init' ) );

		// Admin UI + AJAX endpoints.
		if ( is_admin() ) {
			( new ITBOFFINS_IMAGE_SCOUT_Admin( $this->optimizer ) )->init();
			( new ITBOFFINS_IMAGE_SCOUT_Ajax( $this->optimizer ) )->init();
		}
	}
}

/**
 * Set sensible defaults on activation.
 */
function itboffins_image_scout_activate() {
	if ( false === get_option( ITBOFFINS_IMAGE_SCOUT_OPTION ) ) {
		add_option( ITBOFFINS_IMAGE_SCOUT_OPTION, ITBOFFINS_IMAGE_SCOUT_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'itboffins_image_scout_activate' );

// Go.
add_action( 'plugins_loaded', array( 'ITBoffins_Image_Scout', 'instance' ) );
