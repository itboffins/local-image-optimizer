<?php
/**
 * Settings storage, defaults, and sanitisation.
 *
 * Everything lives in a single option array (IBOH_OPTION) with four top-level
 * groups: schedule, holidays, banner, labels.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin wrapper around the single options array.
 */
class IBOH_Settings {

	/**
	 * Maximum number of opening ranges allowed per day / per holiday.
	 */
	const MAX_RANGES = 6;

	/**
	 * Maximum number of stored holiday/special-date entries.
	 */
	const MAX_HOLIDAYS = 200;

	/**
	 * Default settings.
	 *
	 * Weekday keys are 0..6 = Sunday..Saturday, matching JavaScript's
	 * Date.prototype.getDay() so the PHP and JS evaluators agree without an
	 * off-by-one translation layer.
	 *
	 * @return array
	 */
	public static function defaults() {
		$nine_to_five = array(
			'closed' => 0,
			'ranges' => array( array( 'open' => '09:00', 'close' => '17:00' ) ),
		);

		return array(
			'schedule' => array(
				0 => array( 'closed' => 1, 'ranges' => array() ), // Sunday.
				1 => $nine_to_five,
				2 => $nine_to_five,
				3 => $nine_to_five,
				4 => $nine_to_five,
				5 => $nine_to_five,
				6 => array( 'closed' => 1, 'ranges' => array() ), // Saturday.
			),

			// Date overrides keyed by 'YYYY-MM-DD'. Each fully replaces that
			// weekday's hours for the given date.
			'holidays' => array(),

			'banner'   => array(
				'enabled'       => 1,
				'position'      => 'top',   // 'top' | 'bottom'.
				'dismissible'   => 1,
				'show_next'     => 1,       // Show "Closes at …" / "Opens …" line.
				'soon_mins'     => 60,      // Within N minutes -> "soon" wording.
				'colour_open'   => '#00794a',
				'colour_closed' => '#b3261e',
				'colour_text'   => '#ffffff',
			),

			// Editable, translatable display strings (British spelling).
			'labels'   => array(
				'open'         => __( "We're open", 'opening-hours-banner' ),
				'closed'       => __( "Sorry, we're closed", 'opening-hours-banner' ),
				'closes_at'    => __( 'Closes at %s', 'opening-hours-banner' ),
				'opens_today'  => __( 'Opens today at %s', 'opening-hours-banner' ),
				'opens_on'     => __( 'Opens %1$s at %2$s', 'opening-hours-banner' ),
				'opening_soon' => __( 'Opening soon', 'opening-hours-banner' ),
				'closing_soon' => __( 'Closing soon', 'opening-hours-banner' ),
			),
		);
	}

	/**
	 * Get the full settings array, merged over defaults.
	 *
	 * wp_parse_args() is shallow, so the nested groups are each merged
	 * explicitly. This keeps the structure complete (and forward-compatible:
	 * sub-keys added in future versions inherit their defaults automatically).
	 *
	 * @return array
	 */
	public static function all() {
		$defaults = self::defaults();
		$saved    = get_option( IBOH_OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		$out = wp_parse_args( $saved, $defaults );

		// Schedule: guarantee all seven weekdays with a complete shape.
		$schedule = array();
		foreach ( $defaults['schedule'] as $dow => $default_day ) {
			$day = isset( $saved['schedule'][ $dow ] ) && is_array( $saved['schedule'][ $dow ] )
				? $saved['schedule'][ $dow ]
				: array();
			$schedule[ $dow ] = array(
				'closed' => empty( $day['closed'] ) ? 0 : 1,
				'ranges' => isset( $day['ranges'] ) && is_array( $day['ranges'] ) ? array_values( $day['ranges'] ) : array(),
			);
		}
		$out['schedule'] = $schedule;

		// Holidays: keep as-is if it is an array, else reset.
		$out['holidays'] = ( isset( $saved['holidays'] ) && is_array( $saved['holidays'] ) ) ? $saved['holidays'] : array();

		// Banner + labels: deep-merge over defaults.
		$out['banner'] = wp_parse_args(
			( isset( $saved['banner'] ) && is_array( $saved['banner'] ) ) ? $saved['banner'] : array(),
			$defaults['banner']
		);
		$out['labels'] = wp_parse_args(
			( isset( $saved['labels'] ) && is_array( $saved['labels'] ) ) ? $saved['labels'] : array(),
			$defaults['labels']
		);

		return $out;
	}

	/**
	 * Get a single top-level setting group.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback if missing.
	 * @return mixed
	 */
	public static function get( $key, $default = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	/**
	 * Sanitise an incoming settings array (Settings API callback).
	 *
	 * Always returns a complete, normalised structure so a saved option is
	 * never left partial or malformed.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$out      = array();

		// --- Schedule (weekdays 0..6) ---------------------------------------
		$out['schedule'] = array();
		foreach ( array( 0, 1, 2, 3, 4, 5, 6 ) as $dow ) {
			$day             = isset( $input['schedule'][ $dow ] ) && is_array( $input['schedule'][ $dow ] ) ? $input['schedule'][ $dow ] : array();
			$closed          = empty( $day['closed'] ) ? 0 : 1;
			$ranges          = $closed ? array() : self::sanitize_ranges( isset( $day['ranges'] ) ? $day['ranges'] : array() );
			$out['schedule'][ $dow ] = array(
				'closed' => $closed,
				'ranges' => $ranges,
			);
		}

		// --- Holidays / special dates ---------------------------------------
		$out['holidays'] = array();
		$holidays        = isset( $input['holidays'] ) && is_array( $input['holidays'] ) ? $input['holidays'] : array();
		$count           = 0;
		foreach ( $holidays as $row ) {
			if ( $count >= self::MAX_HOLIDAYS ) {
				break;
			}
			if ( ! is_array( $row ) || empty( $row['date'] ) ) {
				continue;
			}
			$date = self::valid_date( $row['date'] );
			if ( null === $date ) {
				continue;
			}
			$closed = empty( $row['closed'] ) ? 0 : 1;
			$out['holidays'][ $date ] = array(
				'closed' => $closed,
				'ranges' => $closed ? array() : self::sanitize_ranges( isset( $row['ranges'] ) ? $row['ranges'] : array() ),
				'label'  => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
			);
			$count++;
		}
		ksort( $out['holidays'] );

		// --- Banner ---------------------------------------------------------
		$banner                  = isset( $input['banner'] ) && is_array( $input['banner'] ) ? $input['banner'] : array();
		$out['banner']           = array();
		$out['banner']['enabled']     = empty( $banner['enabled'] ) ? 0 : 1;
		$out['banner']['dismissible'] = empty( $banner['dismissible'] ) ? 0 : 1;
		$out['banner']['show_next']   = empty( $banner['show_next'] ) ? 0 : 1;
		$out['banner']['position']    = ( isset( $banner['position'] ) && 'bottom' === $banner['position'] ) ? 'bottom' : 'top';
		$out['banner']['soon_mins']   = isset( $banner['soon_mins'] ) ? max( 0, min( 720, absint( $banner['soon_mins'] ) ) ) : $defaults['banner']['soon_mins'];

		foreach ( array( 'colour_open', 'colour_closed', 'colour_text' ) as $ck ) {
			$colour            = isset( $banner[ $ck ] ) ? sanitize_hex_color( $banner[ $ck ] ) : '';
			$out['banner'][ $ck ] = $colour ? $colour : $defaults['banner'][ $ck ];
		}

		// --- Labels ---------------------------------------------------------
		$labels        = isset( $input['labels'] ) && is_array( $input['labels'] ) ? $input['labels'] : array();
		$out['labels'] = array();
		foreach ( $defaults['labels'] as $key => $default_label ) {
			$value               = isset( $labels[ $key ] ) ? sanitize_text_field( $labels[ $key ] ) : '';
			$out['labels'][ $key ] = ( '' === $value ) ? $default_label : $value;
		}

		return $out;
	}

	/**
	 * Sanitise a list of {open, close} ranges.
	 *
	 * Drops malformed or zero-length ranges, caps the count, and re-indexes.
	 *
	 * @param mixed $ranges Raw ranges array.
	 * @return array
	 */
	private static function sanitize_ranges( $ranges ) {
		if ( ! is_array( $ranges ) ) {
			return array();
		}
		$out = array();
		foreach ( $ranges as $range ) {
			if ( count( $out ) >= self::MAX_RANGES ) {
				break;
			}
			if ( ! is_array( $range ) ) {
				continue;
			}
			$open  = self::valid_time( isset( $range['open'] ) ? $range['open'] : '' );
			$close = self::valid_time( isset( $range['close'] ) ? $range['close'] : '' );
			if ( null === $open || null === $close ) {
				continue;
			}
			// Reject zero-length same-day ranges (open === close), except the
			// explicit 00:00 -> 24:00 "open 24 hours" case.
			if ( $open === $close && ! ( '00:00' === $open && '24:00' === $close ) ) {
				continue;
			}
			$out[] = array(
				'open'  => $open,
				'close' => $close,
			);
		}
		return array_values( $out );
	}

	/**
	 * Validate an 'HH:MM' 24-hour time string (allows 24:00 as end-of-day).
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Normalised 'HH:MM' or null if invalid.
	 */
	private static function valid_time( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		if ( preg_match( '/^([01]\d|2[0-4]):([0-5]\d)$/', $value, $m ) ) {
			if ( '24' === $m[1] && '00' !== $m[2] ) {
				return null; // 24:30 etc. is not valid.
			}
			return $value;
		}
		return null;
	}

	/**
	 * Validate a 'YYYY-MM-DD' date via a strict round-trip.
	 *
	 * @param mixed $value Raw value.
	 * @return string|null Canonical date or null if invalid.
	 */
	private static function valid_date( $value ) {
		$value = is_string( $value ) ? trim( $value ) : '';
		$dt    = DateTime::createFromFormat( '!Y-m-d', $value );
		if ( $dt && $dt->format( 'Y-m-d' ) === $value ) {
			return $value;
		}
		return null;
	}
}
