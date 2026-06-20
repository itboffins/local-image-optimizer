<?php
/**
 * Server-side open/closed evaluation.
 *
 * This mirrors the JavaScript evaluator (assets/frontend.js) and exists purely
 * to render a sensible initial / no-JavaScript status. The live status is always
 * recomputed in the browser, so a cached copy of this server-rendered markup is
 * still corrected on load. Never treat this output as the authoritative state.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pure-ish evaluator over IBOH_Config::data().
 */
class IBOH_Evaluator {

	/**
	 * Compute the current status text.
	 *
	 * @param array|null $cfg Optional pre-built config (defaults to IBOH_Config::data()).
	 * @return array { open: bool, main: string, sub: string }
	 */
	public static function state( $cfg = null ) {
		if ( null === $cfg ) {
			$cfg = IBOH_Config::data();
		}

		$now     = IBOH_Timezone::now();
		$ymd     = $now->format( 'Y-m-d' );
		$dow     = (int) $now->format( 'w' ); // 0..6, Sun..Sat.
		$minutes = (int) $now->format( 'G' ) * 60 + (int) $now->format( 'i' );

		$open     = false;
		$closes_at = null;

		$today = self::day_plan( $cfg, $ymd, $dow );
		if ( empty( $today['closed'] ) ) {
			foreach ( $today['ranges'] as $r ) {
				$o = self::to_min( $r['open'] );
				$c = self::to_min( $r['close'] );
				if ( $c > $o ) {
					if ( $minutes >= $o && $minutes < $c ) {
						$open = true;
						if ( null === $closes_at || $c > $closes_at ) {
							$closes_at = $c;
						}
					}
				} elseif ( $minutes >= $o ) { // Crosses midnight.
					$open      = true;
					$closes_at = 1440;
				}
			}
		}

		// Tail of yesterday's overnight range.
		if ( ! $open ) {
			$yesterday = self::shift_ymd( $ymd, -1 );
			$yplan     = self::day_plan( $cfg, $yesterday['ymd'], $yesterday['dow'] );
			if ( empty( $yplan['closed'] ) ) {
				foreach ( $yplan['ranges'] as $r ) {
					$o = self::to_min( $r['open'] );
					$c = self::to_min( $r['close'] );
					if ( $c <= $o && $minutes < $c ) {
						$open = true;
						if ( null === $closes_at || $c > $closes_at ) {
							$closes_at = $c;
						}
					}
				}
			}
		}

		$next_open = $open ? null : self::find_next_open( $cfg, $ymd, $dow, $minutes );

		return self::build_status( $cfg, $open, $closes_at, $next_open, $minutes );
	}

	/**
	 * Find the next opening as ['dayOffset'=>int,'minute'=>int,'dow'=>int] or null.
	 *
	 * @param array  $cfg     Config.
	 * @param string $ymd     Today's date.
	 * @param int    $dow     Today's weekday.
	 * @param int    $minutes Minutes since midnight now.
	 * @return array|null
	 */
	private static function find_next_open( $cfg, $ymd, $dow, $minutes ) {
		$today = self::day_plan( $cfg, $ymd, $dow );
		if ( empty( $today['closed'] ) ) {
			$cands = array();
			foreach ( $today['ranges'] as $r ) {
				$o = self::to_min( $r['open'] );
				if ( $o > $minutes ) {
					$cands[] = $o;
				}
			}
			if ( $cands ) {
				sort( $cands );
				return array( 'dayOffset' => 0, 'minute' => $cands[0], 'dow' => $dow );
			}
		}
		for ( $i = 1; $i <= 7; $i++ ) {
			$d    = self::shift_ymd( $ymd, $i );
			$plan = self::day_plan( $cfg, $d['ymd'], $d['dow'] );
			if ( empty( $plan['closed'] ) && ! empty( $plan['ranges'] ) ) {
				$opens = array();
				foreach ( $plan['ranges'] as $r ) {
					$opens[] = self::to_min( $r['open'] );
				}
				sort( $opens );
				return array( 'dayOffset' => $i, 'minute' => $opens[0], 'dow' => $d['dow'] );
			}
		}
		return null;
	}

	/**
	 * Compose the human-readable status from the computed state.
	 *
	 * @param array    $cfg       Config.
	 * @param bool     $open      Open now?
	 * @param int|null $closes_at Minute today the current range ends.
	 * @param array|null $next_open Next opening descriptor.
	 * @param int      $minutes   Minutes since midnight now.
	 * @return array
	 */
	private static function build_status( $cfg, $open, $closes_at, $next_open, $minutes ) {
		$labels = $cfg['labels'];
		$soon   = (int) $cfg['banner']['soon_mins'];
		$show   = ! empty( $cfg['banner']['show_next'] );

		if ( $open ) {
			$main = $labels['open'];
			$sub  = '';
			if ( null !== $closes_at ) {
				if ( $show ) {
					$sub = sprintf( $labels['closes_at'], self::fmt_time( $closes_at ) );
				}
				if ( $soon > 0 && ( $closes_at - $minutes ) <= $soon ) {
					$main = $labels['closing_soon'];
				}
			}
			return array( 'open' => true, 'main' => $main, 'sub' => $sub );
		}

		$main = $labels['closed'];
		$sub  = '';
		if ( $next_open ) {
			$time = self::fmt_time( $next_open['minute'] );
			if ( $show ) {
				if ( 0 === $next_open['dayOffset'] ) {
					$sub = sprintf( $labels['opens_today'], $time );
				} else {
					$sub = sprintf( $labels['opens_on'], self::day_name( $cfg, $next_open['dow'] ), $time );
				}
			}
			if ( $soon > 0 && 0 === $next_open['dayOffset'] && ( $next_open['minute'] - $minutes ) <= $soon ) {
				$main = $labels['opening_soon'];
			}
		}
		return array( 'open' => false, 'main' => $main, 'sub' => $sub );
	}

	/**
	 * Resolve the effective plan for a date (holiday override wins).
	 *
	 * @param array  $cfg Config.
	 * @param string $ymd Date.
	 * @param int    $dow Weekday.
	 * @return array { closed: bool, ranges: array }
	 */
	private static function day_plan( $cfg, $ymd, $dow ) {
		if ( isset( $cfg['holidays'][ $ymd ] ) ) {
			$h = $cfg['holidays'][ $ymd ];
			return array(
				'closed' => ! empty( $h['closed'] ),
				'ranges' => empty( $h['closed'] ) && isset( $h['ranges'] ) ? $h['ranges'] : array(),
			);
		}
		$d = isset( $cfg['schedule'][ $dow ] ) ? $cfg['schedule'][ $dow ] : array( 'closed' => 1, 'ranges' => array() );
		return array(
			'closed' => ! empty( $d['closed'] ),
			'ranges' => isset( $d['ranges'] ) ? $d['ranges'] : array(),
		);
	}

	/**
	 * Shift a 'YYYY-MM-DD' by a number of days, returning date + weekday.
	 *
	 * @param string $ymd   Date.
	 * @param int    $delta Days to add (may be negative).
	 * @return array { ymd: string, dow: int }
	 */
	private static function shift_ymd( $ymd, $delta ) {
		$dt = new DateTimeImmutable( $ymd . ' 00:00:00', new DateTimeZone( 'UTC' ) );
		$dt = $dt->modify( ( $delta >= 0 ? '+' : '' ) . $delta . ' days' );
		return array(
			'ymd' => $dt->format( 'Y-m-d' ),
			'dow' => (int) $dt->format( 'w' ),
		);
	}

	/**
	 * Minutes since midnight for an 'HH:MM' string.
	 *
	 * @param string $hhmm Time.
	 * @return int
	 */
	private static function to_min( $hhmm ) {
		$parts = explode( ':', (string) $hhmm );
		return ( (int) $parts[0] ) * 60 + ( isset( $parts[1] ) ? (int) $parts[1] : 0 );
	}

	/**
	 * Format a minute-of-day using the site's time format.
	 *
	 * @param int $minutes Minutes since midnight (0..1440).
	 * @return string
	 */
	private static function fmt_time( $minutes ) {
		$minutes = ( ( $minutes % 1440 ) + 1440 ) % 1440;
		$base    = new DateTimeImmutable( IBOH_Timezone::now()->format( 'Y-m-d' ) . ' 00:00:00', wp_timezone() );
		$ts      = $base->getTimestamp() + $minutes * 60;
		return wp_date( (string) get_option( 'time_format', 'H:i' ), $ts );
	}

	/**
	 * Localised weekday name for an index.
	 *
	 * @param array $cfg Config.
	 * @param int   $dow Weekday index 0..6.
	 * @return string
	 */
	private static function day_name( $cfg, $dow ) {
		return isset( $cfg['dayNames'][ $dow ] ) ? $cfg['dayNames'][ $dow ] : '';
	}
}
