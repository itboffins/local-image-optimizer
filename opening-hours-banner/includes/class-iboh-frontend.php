<?php
/**
 * Front-end asset delivery and the live status banner.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers assets, localises the schedule config, and injects the banner.
 */
class IBOH_Frontend {

	/**
	 * Register front-end hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		$banner = IBOH_Settings::get( 'banner' );
		if ( empty( $banner['enabled'] ) ) {
			return;
		}

		if ( 'bottom' === $banner['position'] ) {
			add_action( 'wp_footer', array( $this, 'render_banner' ), 5 );
		} else {
			// Prefer wp_body_open (top of <body>); fall back to wp_footer on
			// themes that do not implement it.
			if ( did_action( 'wp_body_open' ) || has_action( 'wp_body_open' ) || function_exists( 'wp_body_open' ) ) {
				add_action( 'wp_body_open', array( $this, 'render_banner' ) );
			} else {
				add_action( 'wp_footer', array( $this, 'render_banner' ), 5 );
			}
		}
	}

	/**
	 * Register (and conditionally enqueue) the front-end assets.
	 *
	 * The schedule config is localised onto the registered handle so it is
	 * available whether the script is loaded for the banner or on demand by the
	 * shortcode / block.
	 */
	public function register_assets() {
		wp_register_style( 'iboh-frontend', IBOH_URL . 'assets/frontend.css', array(), IBOH_VERSION );
		wp_register_script( 'iboh-frontend', IBOH_URL . 'assets/frontend.js', array(), IBOH_VERSION, true );
		wp_localize_script( 'iboh-frontend', 'IBOH_DATA', IBOH_Config::data() );

		if ( ! empty( IBOH_Settings::get( 'banner' )['enabled'] ) ) {
			self::enqueue();
		}
	}

	/**
	 * Enqueue the registered front-end assets (safe to call repeatedly).
	 */
	public static function enqueue() {
		wp_enqueue_style( 'iboh-frontend' );
		wp_enqueue_script( 'iboh-frontend' );
	}

	/**
	 * Output the status banner with a server-rendered fallback inside.
	 */
	public function render_banner() {
		$banner = IBOH_Settings::get( 'banner' );
		if ( empty( $banner['enabled'] ) ) {
			return;
		}

		self::enqueue();

		$state = IBOH_Evaluator::state();
		$pos   = ( 'bottom' === $banner['position'] ) ? 'bottom' : 'top';
		$state_class = $state['open'] ? 'iboh-open' : 'iboh-closed';
		$bg    = $state['open'] ? $banner['colour_open'] : $banner['colour_closed'];

		// A signature so a dismissed banner re-appears when the message changes.
		$sig = substr( md5( wp_json_encode( array( $banner, IBOH_Settings::get( 'labels' ) ) ) ), 0, 8 );

		printf(
			'<div class="iboh-banner iboh-pos-%1$s %2$s" data-iboh-banner data-iboh-dismissible="%3$s" data-iboh-sig="%4$s" style="background:%5$s;color:%6$s;">',
			esc_attr( $pos ),
			esc_attr( $state_class ),
			esc_attr( empty( $banner['dismissible'] ) ? '0' : '1' ),
			esc_attr( $sig ),
			esc_attr( $bg ),
			esc_attr( $banner['colour_text'] )
		);

		echo '<div class="iboh-banner-inner">';
		echo '<span class="iboh-dot" aria-hidden="true"></span>';
		echo '<span class="iboh-banner-main" data-iboh-main>' . esc_html( $state['main'] ) . '</span>';
		echo ' <span class="iboh-banner-sub" data-iboh-sub>' . esc_html( $state['sub'] ) . '</span>';
		echo '</div>';

		if ( ! empty( $banner['dismissible'] ) ) {
			printf(
				'<button type="button" class="iboh-banner-close" data-iboh-close aria-label="%s">&times;</button>',
				esc_attr__( 'Dismiss', 'opening-hours-banner' )
			);
		}

		echo '</div>';
	}
}
