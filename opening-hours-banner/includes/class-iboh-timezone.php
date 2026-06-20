<?php
/**
 * Timezone helpers — the single source of truth for "what time is it on the
 * site" used by both the PHP fallback and the JavaScript evaluator.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Resolve the site timezone in the two forms the front end needs.
 */
class IBOH_Timezone {

	/**
	 * The site's IANA timezone name (e.g. "Europe/London"), or '' when the site
	 * is configured with only a fixed UTC offset (e.g. "UTC+5:30").
	 *
	 * When an IANA name is available the JS evaluator uses Intl.DateTimeFormat,
	 * which is DST-correct. The empty-string case falls back to a fixed offset,
	 * which is correct precisely because such sites do not model DST.
	 *
	 * @return string
	 */
	public static function iana() {
		$tz = wp_timezone_string();

		// Manual offsets come back like "+05:30" / "-08:00"; not IANA names.
		if ( '' === $tz || preg_match( '/^[+-]\d{2}:\d{2}$/', $tz ) ) {
			return '';
		}
		return $tz;
	}

	/**
	 * Current UTC offset in minutes (DST-aware for the present instant). Used
	 * only as the fallback when iana() is empty.
	 *
	 * @return int
	 */
	public static function offset_minutes() {
		$now = new DateTimeImmutable( 'now', wp_timezone() );
		return (int) ( $now->getOffset() / 60 );
	}

	/**
	 * "Now" as a DateTimeImmutable in the site timezone (for the PHP fallback).
	 *
	 * @return DateTimeImmutable
	 */
	public static function now() {
		return new DateTimeImmutable( 'now', wp_timezone() );
	}
}
