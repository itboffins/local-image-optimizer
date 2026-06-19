<?php
/**
 * Settings storage and defaults.
 *
 * @package Local_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the single options array.
 */
class LIO_Settings {

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'jpeg_quality'  => 82,   // 40-100, lossy re-encode quality for JPEG.
			'webp_enabled'  => 1,    // Generate .webp alongside images.
			'webp_quality'  => 80,   // 40-100, WebP encode quality.
			'auto_optimize' => 1,    // Optimize new uploads automatically.
			'serve_webp'    => 1,    // Swap <img> for <picture> on the front end.
			'keep_backup'   => 1,    // Keep an untouched copy of each original.
		);
	}

	/**
	 * Get the full settings array, merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( LIO_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	/**
	 * Get a single setting.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if missing.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		if ( array_key_exists( $key, $all ) ) {
			return $all[ $key ];
		}
		return $default;
	}

	/**
	 * Sanitize an incoming settings array (used by the Settings API).
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = array();

		$out['jpeg_quality'] = isset( $input['jpeg_quality'] )
			? max( 40, min( 100, (int) $input['jpeg_quality'] ) )
			: $defaults['jpeg_quality'];

		$out['webp_quality'] = isset( $input['webp_quality'] )
			? max( 40, min( 100, (int) $input['webp_quality'] ) )
			: $defaults['webp_quality'];

		$out['webp_enabled']  = empty( $input['webp_enabled'] ) ? 0 : 1;
		$out['auto_optimize'] = empty( $input['auto_optimize'] ) ? 0 : 1;
		$out['serve_webp']    = empty( $input['serve_webp'] ) ? 0 : 1;
		$out['keep_backup']   = empty( $input['keep_backup'] ) ? 0 : 1;

		return $out;
	}
}
