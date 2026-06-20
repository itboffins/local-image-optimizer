<?php
/**
 * [opening_hours] shortcode and the shared table/status renderers used by both
 * the shortcode and the block.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shortcode + reusable front-end renderers.
 */
class IBOH_Shortcode {

	/**
	 * Register the shortcode.
	 */
	public function init() {
		add_shortcode( 'opening_hours', array( __CLASS__, 'render' ) );
	}

	/**
	 * Render the shortcode / block output.
	 *
	 * @param array $atts Shortcode attributes. Supported: show = table|status|both.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'show' => 'table',
			),
			$atts,
			'opening_hours'
		);

		$show = in_array( $atts['show'], array( 'table', 'status', 'both' ), true ) ? $atts['show'] : 'table';

		// Ensure assets load even when the banner is disabled.
		IBOH_Frontend::enqueue();

		$out = '<div class="iboh-widget">';
		if ( 'status' === $show || 'both' === $show ) {
			$out .= self::status_html();
		}
		if ( 'table' === $show || 'both' === $show ) {
			$out .= self::table_html();
		}
		$out .= '</div>';

		return $out;
	}

	/**
	 * Inline status element (filled live by JavaScript, server-rendered fallback).
	 *
	 * @return string
	 */
	public static function status_html() {
		$state = IBOH_Evaluator::state();
		$class = $state['open'] ? 'iboh-open' : 'iboh-closed';

		$html  = '<p class="iboh-status ' . esc_attr( $class ) . '" data-iboh-status>';
		$html .= '<span class="iboh-dot" aria-hidden="true"></span>';
		$html .= '<span class="iboh-status-main" data-iboh-main>' . esc_html( $state['main'] ) . '</span>';
		$html .= ' <span class="iboh-status-sub" data-iboh-sub>' . esc_html( $state['sub'] ) . '</span>';
		$html .= '</p>';

		return $html;
	}

	/**
	 * Weekly hours table. Static (cache-safe); JavaScript only adds the
	 * "today" highlight, which would otherwise go stale in a cached page.
	 *
	 * @return string
	 */
	public static function table_html() {
		$schedule = IBOH_Settings::get( 'schedule' );
		$labels   = IBOH_Settings::get( 'labels' );
		$days     = IBOH_Config::day_names();

		// Display order Monday..Sunday (store/key order is 0..6 = Sun..Sat).
		$order = array( 1, 2, 3, 4, 5, 6, 0 );

		$html = '<table class="iboh-hours" data-iboh-table><tbody>';
		foreach ( $order as $dow ) {
			$day    = isset( $schedule[ $dow ] ) ? $schedule[ $dow ] : array( 'closed' => 1, 'ranges' => array() );
			$name   = isset( $days[ $dow ] ) ? $days[ $dow ] : '';
			$hours  = self::format_day_hours( $day, $labels );

			$html .= '<tr data-iboh-dow="' . esc_attr( $dow ) . '">';
			$html .= '<th scope="row" class="iboh-day">' . esc_html( $name ) . '</th>';
			$html .= '<td class="iboh-times">' . esc_html( $hours ) . '</td>';
			$html .= '</tr>';
		}
		$html .= '</tbody></table>';

		return $html;
	}

	/**
	 * Human string for a day's ranges, e.g. "9:00 am – 5:00 pm" or "Closed".
	 *
	 * @param array $day    Day plan { closed, ranges }.
	 * @param array $labels Label strings (unused placeholders reserved).
	 * @return string
	 */
	private static function format_day_hours( $day, $labels ) {
		unset( $labels );

		if ( ! empty( $day['closed'] ) || empty( $day['ranges'] ) ) {
			return __( 'Closed', 'opening-hours-banner' );
		}

		// Whole-day open shortcut.
		if ( 1 === count( $day['ranges'] ) && '00:00' === $day['ranges'][0]['open'] && '24:00' === $day['ranges'][0]['close'] ) {
			return __( 'Open 24 hours', 'opening-hours-banner' );
		}

		$parts = array();
		foreach ( $day['ranges'] as $r ) {
			$parts[] = self::fmt( $r['open'] ) . ' – ' . self::fmt( $r['close'] );
		}
		return implode( ', ', $parts );
	}

	/**
	 * Format an 'HH:MM' string using the site's time format.
	 *
	 * @param string $hhmm Time.
	 * @return string
	 */
	private static function fmt( $hhmm ) {
		if ( '24:00' === $hhmm ) {
			$hhmm = '23:59'; // Display only; treated as end-of-day elsewhere.
		}
		$parts   = explode( ':', $hhmm );
		$minutes = (int) $parts[0] * 60 + ( isset( $parts[1] ) ? (int) $parts[1] : 0 );
		$base    = new DateTimeImmutable( IBOH_Timezone::now()->format( 'Y-m-d' ) . ' 00:00:00', wp_timezone() );
		return wp_date( (string) get_option( 'time_format', 'H:i' ), $base->getTimestamp() + $minutes * 60 );
	}
}
