<?php
/**
 * Server-rendered "Opening Hours" block.
 *
 * Registered entirely in PHP (no build step). The editor script is hand-written
 * vanilla JS that relies on the WordPress packages already bundled with core
 * (wp.blocks, wp.element, wp.serverSideRender, wp.blockEditor, wp.components).
 * The block delegates rendering to the same code path as the shortcode.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block registration.
 */
class IBOH_Block {

	/**
	 * Register the block and its editor script.
	 */
	public function init() {
		if ( ! function_exists( 'register_block_type' ) ) {
			return; // WordPress < 5.0.
		}

		wp_register_script(
			'iboh-editor',
			IBOH_URL . 'assets/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			IBOH_VERSION,
			true
		);

		register_block_type(
			'iboh/opening-hours',
			array(
				'api_version'     => 2,
				'editor_script'   => 'iboh-editor',
				'attributes'      => array(
					'show' => array(
						'type'    => 'string',
						'default' => 'table',
					),
				),
				'render_callback' => array( __CLASS__, 'render' ),
			)
		);
	}

	/**
	 * Render callback — reuse the shortcode renderer for a single source of truth.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$show = isset( $attributes['show'] ) ? $attributes['show'] : 'table';
		return IBOH_Shortcode::render( array( 'show' => $show ) );
	}
}
