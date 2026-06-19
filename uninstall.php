<?php
/**
 * Uninstall cleanup.
 *
 * Removes the plugin's settings and per-attachment stats. We deliberately do
 * NOT delete generated WebP files or the originals backup folder, so the site
 * keeps working and nothing irreversible happens on uninstall.
 *
 * @package Local_Image_Optimizer
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'lio_settings' );

// Remove the per-attachment stats meta.
delete_post_meta_by_key( '_lio_stats' );
