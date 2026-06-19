<?php
/**
 * Plugin Name:       Local Image Optimiser
 * Plugin URI:        https://itboffins.com/plugins/local-image-optimizer/
 * Description:       Compress your images and serve next-gen WebP using only the image tools already on your server (GD or Imagick). No external API, no account, no shell access required — works on any WordPress host.
 * Version:           1.0.2
 * Requires at least: 5.8
 * Requires PHP:      7.2
 * Author:            IT Boffins
 * Author URI:        https://itboffins.com/
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       local-image-optimizer
 * Domain Path:       /languages
 *
 * @package Local_Image_Optimizer
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'LIO_VERSION', '1.0.2' );
define( 'LIO_FILE', __FILE__ );
define( 'LIO_DIR', plugin_dir_path( __FILE__ ) );
define( 'LIO_URL', plugin_dir_url( __FILE__ ) );
define( 'LIO_BASENAME', plugin_basename( __FILE__ ) );

// Option key in wp_options and the per-attachment meta key.
define( 'LIO_OPTION', 'lio_settings' );
define( 'LIO_META', '_lio_stats' );

// Sub-folder inside /uploads where untouched originals are backed up.
define( 'LIO_BACKUP_DIR', 'lio-originals' );

require_once LIO_DIR . 'includes/class-lio-settings.php';
require_once LIO_DIR . 'includes/class-lio-capabilities.php';
require_once LIO_DIR . 'includes/class-lio-optimizer.php';
require_once LIO_DIR . 'includes/class-lio-frontend.php';
require_once LIO_DIR . 'includes/class-lio-admin.php';
require_once LIO_DIR . 'includes/class-lio-ajax.php';

/**
 * Main plugin bootstrap.
 */
final class Local_Image_Optimizer {

	/**
	 * Singleton instance.
	 *
	 * @var Local_Image_Optimizer|null
	 */
	private static $instance = null;

	/**
	 * Shared optimizer instance.
	 *
	 * @var LIO_Optimizer
	 */
	public $optimizer;

	/**
	 * Get the singleton instance.
	 *
	 * @return Local_Image_Optimizer
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
		$this->optimizer = new LIO_Optimizer();

		// Optimize images automatically as they are processed by WordPress.
		add_filter( 'wp_generate_attachment_metadata', array( $this->optimizer, 'maybe_auto_optimize' ), 20, 2 );

		// Front-end WebP delivery.
		add_action( 'init', array( new LIO_Frontend(), 'init' ) );

		// Admin UI + AJAX endpoints.
		if ( is_admin() ) {
			( new LIO_Admin( $this->optimizer ) )->init();
			( new LIO_Ajax( $this->optimizer ) )->init();
		}

		load_plugin_textdomain( 'local-image-optimizer', false, dirname( LIO_BASENAME ) . '/languages' );
	}
}

/**
 * Set sensible defaults on activation.
 */
function lio_activate() {
	if ( false === get_option( LIO_OPTION ) ) {
		add_option( LIO_OPTION, LIO_Settings::defaults() );
	}
}
register_activation_hook( __FILE__, 'lio_activate' );

// Go.
add_action( 'plugins_loaded', array( 'Local_Image_Optimizer', 'instance' ) );
