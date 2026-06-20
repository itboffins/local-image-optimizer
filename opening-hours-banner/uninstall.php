<?php
/**
 * Uninstall cleanup: remove the plugin's single option.
 *
 * @package Opening_Hours_Banner
 */

// Exit if not called by WordPress during uninstall.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'iboh_settings' );
