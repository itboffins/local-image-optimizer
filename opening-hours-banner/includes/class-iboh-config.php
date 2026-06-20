<?php
/**
 * Builds the canonical configuration consumed identically by the PHP fallback
 * evaluator and the client-side JavaScript evaluator.
 *
 * IMPORTANT: this payload contains only the schedule *definition* (which changes
 * only when the admin saves settings) — never a computed open/closed status.
 * The live status is always derived in the browser, which is what keeps the
 * banner correct under full-page caching.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical front-end config provider.
 */
class IBOH_Config {

	/**
	 * Build the config array.
	 *
	 * @return array
	 */
	public static function data() {
		$settings = IBOH_Settings::all();

		return array(
			'tz'         => IBOH_Timezone::iana(),
			'offset'     => IBOH_Timezone::offset_minutes(),
			'timeFormat' => (string) get_option( 'time_format', 'H:i' ),
			'dayNames'   => self::day_names(),
			'schedule'   => $settings['schedule'],
			'holidays'   => $settings['holidays'],
			'labels'     => $settings['labels'],
			'banner'     => $settings['banner'],
		);
	}

	/**
	 * Localised full weekday names, index 0..6 = Sunday..Saturday.
	 *
	 * @return array
	 */
	public static function day_names() {
		$names = array();
		if ( isset( $GLOBALS['wp_locale'] ) && is_object( $GLOBALS['wp_locale'] ) ) {
			for ( $i = 0; $i < 7; $i++ ) {
				$names[ $i ] = $GLOBALS['wp_locale']->get_weekday( $i );
			}
			return $names;
		}
		return array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
	}
}
