<?php
/**
 * AJAX endpoints for single + bulk optimization and restore.
 *
 * @package Local_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * AJAX handlers.
 */
class LIO_Ajax {

	/**
	 * Optimizer instance.
	 *
	 * @var LIO_Optimizer
	 */
	private $optimizer;

	/**
	 * Constructor.
	 *
	 * @param LIO_Optimizer $optimizer Optimizer.
	 */
	public function __construct( LIO_Optimizer $optimizer ) {
		$this->optimizer = $optimizer;
	}

	/**
	 * Register AJAX actions.
	 */
	public function init() {
		add_action( 'wp_ajax_lio_get_ids', array( $this, 'get_ids' ) );
		add_action( 'wp_ajax_lio_optimize', array( $this, 'optimize' ) );
		add_action( 'wp_ajax_lio_restore', array( $this, 'restore' ) );
	}

	/**
	 * Shared guard: valid nonce + capability.
	 */
	private function guard() {
		if ( ! check_ajax_referer( 'lio_ajax', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed. Please reload and try again.', 'local-image-optimizer' ) ), 403 );
		}
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do this.', 'local-image-optimizer' ) ), 403 );
		}
	}

	/**
	 * Return all optimizable attachment IDs.
	 */
	public function get_ids() {
		$this->guard();

		$ids = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_mime_type' => array( 'image/jpeg', 'image/png' ),
				'post_status'    => 'inherit',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);

		// Return the filename alongside each ID so the progress window can show
		// which file is being optimised rather than just a running count.
		$items = array();
		foreach ( $ids as $id ) {
			$file    = get_attached_file( $id );
			$items[] = array(
				'id'   => (int) $id,
				'name' => $file ? wp_basename( $file ) : get_the_title( $id ),
			);
		}

		wp_send_json_success(
			array(
				'items' => $items,
				'total' => count( $items ),
			)
		);
	}

	/**
	 * Optimize a single attachment.
	 */
	public function optimize() {
		$this->guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid image ID.', 'local-image-optimizer' ) ) );
		}

		$result = $this->optimizer->optimize_attachment( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'id'      => $id,
					'message' => $result->get_error_message(),
				)
			);
		}

		$file = get_attached_file( $id );

		wp_send_json_success(
			array(
				'id'      => $id,
				'name'    => $file ? wp_basename( $file ) : get_the_title( $id ),
				'saved'   => (int) $result['saved'],
				'percent' => (float) $result['percent'],
				'webp'    => (int) $result['webp'],
				'human'   => size_format( $result['saved'] ),
			)
		);
	}

	/**
	 * Restore a single attachment from backup.
	 */
	public function restore() {
		$this->guard();

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid image ID.', 'local-image-optimizer' ) ) );
		}

		$result = $this->optimizer->restore_attachment( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'id'      => $id,
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success( array( 'id' => $id ) );
	}
}
