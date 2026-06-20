<?php
/**
 * AJAX endpoints (admin only).
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handlers.
 */
class IBOH_Ajax {

	/**
	 * Register AJAX actions.
	 */
	public function init() {
		add_action( 'wp_ajax_iboh_preview', array( $this, 'preview' ) );
	}

	/**
	 * Shared guard: valid nonce + capability.
	 */
	private function guard() {
		if ( ! check_ajax_referer( 'iboh_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload and try again.', 'opening-hours-banner' ) ), 403 );
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'opening-hours-banner' ) ), 403 );
		}
	}

	/**
	 * Return the current saved config so the admin preview can evaluate it with
	 * the same client-side logic the front end uses.
	 */
	public function preview() {
		$this->guard();
		wp_send_json_success( IBOH_Config::data() );
	}
}
