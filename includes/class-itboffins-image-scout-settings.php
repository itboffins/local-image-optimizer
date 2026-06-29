<?php
/**
 * Settings storage and defaults.
 *
 * @package ITBoffins_Image_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the single options array.
 */
class ITBOFFINS_IMAGE_SCOUT_Settings {

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
			'auto_optimize' => 1,    // Optimise new uploads automatically.
			'serve_webp'    => 1,    // Swap <img> for <picture> on the front end.
			'keep_backup'   => 0,    // Keep originals only when explicitly enabled.
			'backup_dir'    => self::default_backup_dir(),
		);
	}

	/**
	 * Get the current protected backup directory name.
	 *
	 * @return string
	 */
	public static function backup_dir_name() {
		return self::sanitize_backup_dir( self::get( 'backup_dir', self::default_backup_dir() ) );
	}

	/**
	 * Directory names that must be treated as backup storage.
	 *
	 * @return string[]
	 */
	public static function backup_dir_names() {
		return array_values( array_unique( array_filter( array( self::backup_dir_name(), ITBOFFINS_IMAGE_SCOUT_BACKUP_DIR ) ) ) );
	}

	/**
	 * Sanitize an internally generated backup directory name.
	 *
	 * @param string $dir Directory name.
	 * @return string
	 */
	private static function sanitize_backup_dir( $dir ) {
		$dir     = sanitize_file_name( (string) $dir );
		$pattern = '/^' . preg_quote( ITBOFFINS_IMAGE_SCOUT_BACKUP_DIR, '/' ) . '-[a-f0-9]{12}$/';
		return preg_match( $pattern, $dir ) ? $dir : self::default_backup_dir();
	}

	/**
	 * Build a stable, non-public backup directory name for this site.
	 *
	 * @return string
	 */
	private static function default_backup_dir() {
		$site = function_exists( 'home_url' ) ? home_url( '/' ) : '';
		$salt = function_exists( 'wp_salt' ) ? wp_salt( 'auth' ) : '';

		if ( '' === $salt ) {
			foreach ( array( 'AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT' ) as $constant ) {
				if ( defined( $constant ) ) {
					$salt .= constant( $constant );
				}
			}
		}

		$base = defined( 'ABSPATH' ) ? ABSPATH : __DIR__;
		$hash = substr( hash( 'sha256', $salt . '|' . $site . '|' . $base ), 0, 12 );
		return ITBOFFINS_IMAGE_SCOUT_BACKUP_DIR . '-' . $hash;
	}

	/**
	 * Get the full settings array, merged over defaults.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( ITBOFFINS_IMAGE_SCOUT_OPTION, array() );
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
		$current  = get_option( ITBOFFINS_IMAGE_SCOUT_OPTION, array() );
		$current  = is_array( $current ) ? $current : array();
		$out      = array();

		$out['jpeg_quality'] = isset( $input['jpeg_quality'] )
			? max( 40, min( 100, (int) $input['jpeg_quality'] ) )
			: $defaults['jpeg_quality'];

		$out['webp_quality'] = isset( $input['webp_quality'] )
			? max( 40, min( 100, (int) $input['webp_quality'] ) )
			: $defaults['webp_quality'];

		$out['webp_enabled']   = empty( $input['webp_enabled'] ) ? 0 : 1;
		$out['auto_optimize']  = empty( $input['auto_optimize'] ) ? 0 : 1;
		$out['serve_webp']     = empty( $input['serve_webp'] ) ? 0 : 1;
		$out['keep_backup']    = empty( $input['keep_backup'] ) ? 0 : 1;
		$out['backup_dir']     = self::sanitize_backup_dir( isset( $current['backup_dir'] ) ? $current['backup_dir'] : $defaults['backup_dir'] );

		return $out;
	}
}
