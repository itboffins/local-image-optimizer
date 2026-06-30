<?php
/**
 * Detects what the current server can actually do.
 *
 * The whole point of this plugin is to work on any host, so every action is
 * gated behind a runtime capability check rather than assuming a binary or
 * extension is present.
 *
 * @package ITBoffins_Image_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Capability probe.
 */
class ITBOFFINS_IMAGE_SCOUT_Capabilities {

	/**
	 * Cached result for this request.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Report what image tooling is available.
	 *
	 * @return array {
	 *     @type bool   $gd            GD extension present.
	 *     @type bool   $gd_webp       GD can write WebP.
	 *     @type bool   $imagick       Imagick extension present.
	 *     @type bool   $imagick_webp  Imagick can write WebP.
	 *     @type bool   $can_compress  At least one editor is usable.
	 *     @type bool   $can_webp      WordPress can write WebP with this server.
	 *     @type string $engine        Human label of the active editor.
	 * }
	 */
	public static function get() {
		if ( null !== self::$cache ) {
			return self::$cache;
		}

		$gd      = function_exists( 'gd_info' );
		$gd_webp = $gd && function_exists( 'imagewebp' );

		$imagick      = extension_loaded( 'imagick' ) && class_exists( 'Imagick' );
		$imagick_webp = false;
		if ( $imagick ) {
			try {
				$formats      = (array) ( new Imagick() )->queryFormats( 'WEBP' );
				$imagick_webp = in_array( 'WEBP', array_map( 'strtoupper', $formats ), true );
			} catch ( Exception $e ) {
				$imagick_webp = false;
			}
		}

		// Let WordPress decide which editor it will actually use.
		$can_compress = (bool) wp_image_editor_supports( array( 'methods' => array( 'resize', 'save' ) ) );
		$can_webp     = (bool) wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );

		// Work out which implementation WordPress prefers, for display only.
		$engine = __( 'None available', 'local-image-optimiser' );
		$chosen = function_exists( '_wp_image_editor_choose' ) ? _wp_image_editor_choose() : false;
		if ( 'WP_Image_Editor_Imagick' === $chosen ) {
			$engine = 'Imagick';
		} elseif ( 'WP_Image_Editor_GD' === $chosen ) {
			$engine = 'GD';
		} elseif ( $imagick ) {
			$engine = 'Imagick';
		} elseif ( $gd ) {
			$engine = 'GD';
		}

		self::$cache = compact(
			'gd',
			'gd_webp',
			'imagick',
			'imagick_webp',
			'can_compress',
			'can_webp',
			'engine'
		);

		return self::$cache;
	}
}
