<?php
/**
 * Admin settings screen: schedule editor, holidays, banner, and labels.
 *
 * @package Opening_Hours_Banner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI.
 */
class IBOH_Admin {

	/**
	 * Admin page hook suffix.
	 */
	const PAGE = 'opening-hours-banner';

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . IBOH_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Add a "Settings" link on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'admin.php?page=' . self::PAGE ) ) . '">' . esc_html__( 'Settings', 'opening-hours-banner' ) . '</a>';
		array_unshift( $links, $settings );
		return $links;
	}

	/**
	 * Register the top-level admin menu.
	 */
	public function register_menu() {
		add_menu_page(
			__( 'Opening Hours', 'opening-hours-banner' ),
			__( 'Opening Hours', 'opening-hours-banner' ),
			'manage_options',
			self::PAGE,
			array( $this, 'render_settings_page' ),
			'dashicons-clock',
			81
		);
	}

	/**
	 * Register settings with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'iboh_settings_group',
			IBOH_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'IBOH_Settings', 'sanitize' ),
				'default'           => IBOH_Settings::defaults(),
			)
		);
	}

	/**
	 * Enqueue admin assets on our screen only.
	 *
	 * frontend.js is loaded too so the live preview can reuse the very same
	 * evaluator (window.IBOHEval) the front end runs; it stays inert here
	 * because window.IBOH_DATA is not defined in admin.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		if ( 'toplevel_page_' . self::PAGE !== $hook ) {
			return;
		}

		wp_enqueue_style( 'iboh-admin', IBOH_URL . 'assets/admin.css', array(), IBOH_VERSION );
		wp_enqueue_script( 'iboh-frontend', IBOH_URL . 'assets/frontend.js', array(), IBOH_VERSION, true );
		wp_enqueue_script( 'iboh-admin', IBOH_URL . 'assets/admin.js', array( 'iboh-frontend' ), IBOH_VERSION, true );

		wp_localize_script(
			'iboh-admin',
			'IBOH',
			array(
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'iboh_ajax' ),
				'tz'         => IBOH_Timezone::iana(),
				'offset'     => IBOH_Timezone::offset_minutes(),
				'timeFormat' => (string) get_option( 'time_format', 'H:i' ),
				'dayNames'   => IBOH_Config::day_names(),
				'i18n'       => array(
					'remove' => __( 'Remove', 'opening-hours-banner' ),
				),
			)
		);
	}

	/**
	 * Render the settings screen.
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = IBOH_Settings::all();
		$schedule = $settings['schedule'];
		$banner   = $settings['banner'];
		$labels   = $settings['labels'];
		$days     = IBOH_Config::day_names();
		$order    = array( 1, 2, 3, 4, 5, 6, 0 ); // Display Monday..Sunday.
		?>
		<div class="wrap iboh-wrap">
			<?php $this->brand_header(); ?>
			<h1><?php esc_html_e( 'Opening Hours', 'opening-hours-banner' ); ?></h1>

			<div class="iboh-card iboh-preview-card">
				<span class="iboh-eyebrow"><?php esc_html_e( 'Live preview', 'opening-hours-banner' ); ?></span>
				<div class="iboh-preview" id="iboh-preview">
					<span class="iboh-dot" aria-hidden="true"></span>
					<span class="iboh-preview-main" data-iboh-main>—</span>
					<span class="iboh-preview-sub" data-iboh-sub></span>
				</div>
				<p class="description"><?php esc_html_e( 'Reflects the form below in real time, in your site timezone. Save changes to apply them on the front end.', 'opening-hours-banner' ); ?></p>
			</div>

			<form method="post" action="options.php" id="iboh-form">
				<?php settings_fields( 'iboh_settings_group' ); ?>

				<div class="iboh-card">
					<h2><?php esc_html_e( 'Weekly hours', 'opening-hours-banner' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Add more than one row per day for split hours (e.g. a lunch break). For overnight hours, set a closing time earlier than the opening time (e.g. 22:00 – 02:00).', 'opening-hours-banner' ); ?></p>

					<div class="iboh-days">
						<?php foreach ( $order as $dow ) : ?>
							<?php $this->render_day_row( $dow, $schedule[ $dow ], isset( $days[ $dow ] ) ? $days[ $dow ] : '' ); ?>
						<?php endforeach; ?>
					</div>
					<p><button type="button" class="button" id="iboh-copy-weekdays"><?php esc_html_e( 'Copy Monday to weekdays', 'opening-hours-banner' ); ?></button></p>
				</div>

				<div class="iboh-card">
					<h2><?php esc_html_e( 'Special dates &amp; holidays', 'opening-hours-banner' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Override a specific date — close for a public holiday, or set special hours. These take priority over the weekly hours.', 'opening-hours-banner' ); ?></p>
					<div class="iboh-holidays" id="iboh-holidays">
						<?php
						$i = 0;
						foreach ( $settings['holidays'] as $date => $holiday ) {
							$this->render_holiday_row( $i, $date, $holiday );
							$i++;
						}
						?>
					</div>
					<p><button type="button" class="button" id="iboh-add-holiday"><?php esc_html_e( 'Add a date', 'opening-hours-banner' ); ?></button></p>
				</div>

				<div class="iboh-card">
					<h2><?php esc_html_e( 'Status banner', 'opening-hours-banner' ); ?></h2>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Show banner', 'opening-hours-banner' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][enabled]" value="1" <?php checked( $banner['enabled'] ); ?> />
									<?php esc_html_e( 'Display the live open/closed banner on the front end', 'opening-hours-banner' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="iboh-position"><?php esc_html_e( 'Position', 'opening-hours-banner' ); ?></label></th>
							<td>
								<select id="iboh-position" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][position]">
									<option value="top" <?php selected( $banner['position'], 'top' ); ?>><?php esc_html_e( 'Top of page', 'opening-hours-banner' ); ?></option>
									<option value="bottom" <?php selected( $banner['position'], 'bottom' ); ?>><?php esc_html_e( 'Bottom of page', 'opening-hours-banner' ); ?></option>
								</select>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Dismissible', 'opening-hours-banner' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][dismissible]" value="1" <?php checked( $banner['dismissible'] ); ?> />
									<?php esc_html_e( 'Let visitors close the banner', 'opening-hours-banner' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Next-change text', 'opening-hours-banner' ); ?></th>
							<td>
								<label>
									<input type="checkbox" data-iboh-banner-field="show_next" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][show_next]" value="1" <?php checked( $banner['show_next'] ); ?> />
									<?php esc_html_e( 'Show "Closes at …" / "Opens …" alongside the status', 'opening-hours-banner' ); ?>
								</label>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="iboh-soon"><?php esc_html_e( '"Soon" threshold', 'opening-hours-banner' ); ?></label></th>
							<td>
								<input type="number" min="0" max="720" id="iboh-soon" class="small-text" data-iboh-banner-field="soon_mins" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][soon_mins]" value="<?php echo esc_attr( $banner['soon_mins'] ); ?>" />
								<?php esc_html_e( 'minutes', 'opening-hours-banner' ); ?>
								<p class="description"><?php esc_html_e( 'Within this many minutes of opening or closing, the banner shows "Opening soon" / "Closing soon". Set to 0 to disable.', 'opening-hours-banner' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Colours', 'opening-hours-banner' ); ?></th>
							<td class="iboh-colours">
								<label><?php esc_html_e( 'Open', 'opening-hours-banner' ); ?><br><input type="color" data-iboh-banner-field="colour_open" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][colour_open]" value="<?php echo esc_attr( $banner['colour_open'] ); ?>" /></label>
								<label><?php esc_html_e( 'Closed', 'opening-hours-banner' ); ?><br><input type="color" data-iboh-banner-field="colour_closed" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][colour_closed]" value="<?php echo esc_attr( $banner['colour_closed'] ); ?>" /></label>
								<label><?php esc_html_e( 'Text', 'opening-hours-banner' ); ?><br><input type="color" data-iboh-banner-field="colour_text" name="<?php echo esc_attr( IBOH_OPTION ); ?>[banner][colour_text]" value="<?php echo esc_attr( $banner['colour_text'] ); ?>" /></label>
							</td>
						</tr>
					</table>
				</div>

				<div class="iboh-card">
					<h2><?php esc_html_e( 'Wording', 'opening-hours-banner' ); ?></h2>
					<p class="description"><?php esc_html_e( 'Customise the messages. Keep the %s, %1$s and %2$s placeholders — they are replaced with the time and weekday.', 'opening-hours-banner' ); ?></p>
					<table class="form-table" role="presentation">
						<?php
						$label_help = array(
							'open'         => __( 'Shown when open', 'opening-hours-banner' ),
							'closed'       => __( 'Shown when closed', 'opening-hours-banner' ),
							'closes_at'    => __( 'Open, with closing time (%s = time)', 'opening-hours-banner' ),
							'opens_today'  => __( 'Closed, opens later today (%s = time)', 'opening-hours-banner' ),
							'opens_on'     => __( 'Closed, opens another day (%1$s = day, %2$s = time)', 'opening-hours-banner' ),
							'opening_soon' => __( 'About to open', 'opening-hours-banner' ),
							'closing_soon' => __( 'About to close', 'opening-hours-banner' ),
						);
						foreach ( $labels as $key => $value ) :
							?>
							<tr>
								<th scope="row"><label for="iboh-label-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( isset( $label_help[ $key ] ) ? $label_help[ $key ] : $key ); ?></label></th>
								<td>
									<input type="text" class="regular-text" id="iboh-label-<?php echo esc_attr( $key ); ?>" data-iboh-label="<?php echo esc_attr( $key ); ?>" name="<?php echo esc_attr( IBOH_OPTION ); ?>[labels][<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $value ); ?>" />
								</td>
							</tr>
						<?php endforeach; ?>
					</table>
				</div>

				<?php submit_button(); ?>
			</form>

			<div class="iboh-card iboh-promo">
				<h2><?php esc_html_e( 'Show your hours anywhere', 'opening-hours-banner' ); ?></h2>
				<p><?php esc_html_e( 'Use the "Opening Hours" block, or the shortcode:', 'opening-hours-banner' ); ?></p>
				<p><code>[opening_hours show="table"]</code> · <code>[opening_hours show="status"]</code> · <code>[opening_hours show="both"]</code></p>
				<p>
					<?php
					printf(
						/* translators: %s: link to itboffins.com */
						esc_html__( 'More free plugins at %s', 'opening-hours-banner' ),
						'<a href="https://itboffins.com/" target="_blank" rel="noopener">itboffins.com</a>'
					);
					?>
				</p>
			</div>

			<?php $this->render_templates(); ?>
		</div>
		<?php
	}

	/**
	 * Render one weekday editor row.
	 *
	 * @param int    $dow  Weekday index 0..6.
	 * @param array  $day  Day plan { closed, ranges }.
	 * @param string $name Localised day name.
	 */
	private function render_day_row( $dow, $day, $name ) {
		$ranges_name = IBOH_OPTION . '[schedule][' . $dow . '][ranges]';
		$is_closed   = ! empty( $day['closed'] );
		?>
		<div class="iboh-day-row" data-iboh-day data-dow="<?php echo esc_attr( $dow ); ?>">
			<div class="iboh-day-name"><?php echo esc_html( $name ); ?></div>
			<label class="iboh-day-toggle">
				<input type="checkbox" class="iboh-closed-toggle" name="<?php echo esc_attr( IBOH_OPTION ); ?>[schedule][<?php echo esc_attr( $dow ); ?>][closed]" value="1" <?php checked( $is_closed ); ?> />
				<?php esc_html_e( 'Closed', 'opening-hours-banner' ); ?>
			</label>
			<div class="iboh-day-body" <?php echo $is_closed ? 'style="display:none;"' : ''; ?>>
				<div class="iboh-ranges" data-name="<?php echo esc_attr( $ranges_name ); ?>">
					<?php
					$i = 0;
					foreach ( $day['ranges'] as $range ) {
						$this->render_range_row( $ranges_name . '[' . $i . ']', $range );
						$i++;
					}
					?>
				</div>
				<button type="button" class="button-link iboh-add-range"><?php esc_html_e( '+ Add hours', 'opening-hours-banner' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Render a single open/close range row.
	 *
	 * @param string $name_prefix Full name prefix, e.g. "...[ranges][0]".
	 * @param array  $range       { open, close }.
	 */
	private function render_range_row( $name_prefix, $range ) {
		$open  = isset( $range['open'] ) ? $range['open'] : '';
		$close = isset( $range['close'] ) ? $range['close'] : '';
		?>
		<div class="iboh-range">
			<input type="time" name="<?php echo esc_attr( $name_prefix ); ?>[open]" value="<?php echo esc_attr( $open ); ?>" />
			<span class="iboh-range-sep">–</span>
			<input type="time" name="<?php echo esc_attr( $name_prefix ); ?>[close]" value="<?php echo esc_attr( $close ); ?>" />
			<button type="button" class="button-link iboh-remove-range"><?php esc_html_e( 'Remove', 'opening-hours-banner' ); ?></button>
		</div>
		<?php
	}

	/**
	 * Render one holiday / special-date row.
	 *
	 * @param int    $i       Row index.
	 * @param string $date    Date (YYYY-MM-DD).
	 * @param array  $holiday { closed, ranges, label }.
	 */
	private function render_holiday_row( $i, $date, $holiday ) {
		$base        = IBOH_OPTION . '[holidays][' . $i . ']';
		$ranges_name = $base . '[ranges]';
		$is_closed   = ! empty( $holiday['closed'] );
		?>
		<div class="iboh-holiday" data-iboh-holiday>
			<div class="iboh-holiday-top">
				<input type="date" name="<?php echo esc_attr( $base ); ?>[date]" value="<?php echo esc_attr( $date ); ?>" />
				<input type="text" class="regular-text iboh-holiday-label" name="<?php echo esc_attr( $base ); ?>[label]" value="<?php echo esc_attr( isset( $holiday['label'] ) ? $holiday['label'] : '' ); ?>" placeholder="<?php esc_attr_e( 'Label (optional)', 'opening-hours-banner' ); ?>" />
				<label class="iboh-day-toggle">
					<input type="checkbox" class="iboh-closed-toggle" name="<?php echo esc_attr( $base ); ?>[closed]" value="1" <?php checked( $is_closed ); ?> />
					<?php esc_html_e( 'Closed', 'opening-hours-banner' ); ?>
				</label>
				<button type="button" class="button-link iboh-remove-holiday"><?php esc_html_e( 'Remove', 'opening-hours-banner' ); ?></button>
			</div>
			<div class="iboh-day-body" <?php echo $is_closed ? 'style="display:none;"' : ''; ?>>
				<div class="iboh-ranges" data-name="<?php echo esc_attr( $ranges_name ); ?>">
					<?php
					$j      = 0;
					$ranges = isset( $holiday['ranges'] ) && is_array( $holiday['ranges'] ) ? $holiday['ranges'] : array();
					foreach ( $ranges as $range ) {
						$this->render_range_row( $ranges_name . '[' . $j . ']', $range );
						$j++;
					}
					?>
				</div>
				<button type="button" class="button-link iboh-add-range"><?php esc_html_e( '+ Add hours', 'opening-hours-banner' ); ?></button>
			</div>
		</div>
		<?php
	}

	/**
	 * Hidden <template> markup cloned by admin.js for new rows.
	 */
	private function render_templates() {
		?>
		<template id="iboh-range-tpl">
			<div class="iboh-range">
				<input type="time" data-field="open" />
				<span class="iboh-range-sep">–</span>
				<input type="time" data-field="close" />
				<button type="button" class="button-link iboh-remove-range"><?php esc_html_e( 'Remove', 'opening-hours-banner' ); ?></button>
			</div>
		</template>

		<template id="iboh-holiday-tpl">
			<div class="iboh-holiday" data-iboh-holiday>
				<div class="iboh-holiday-top">
					<input type="date" data-field="date" />
					<input type="text" class="regular-text iboh-holiday-label" data-field="label" placeholder="<?php esc_attr_e( 'Label (optional)', 'opening-hours-banner' ); ?>" />
					<label class="iboh-day-toggle">
						<input type="checkbox" class="iboh-closed-toggle" data-field="closed" value="1" />
						<?php esc_html_e( 'Closed', 'opening-hours-banner' ); ?>
					</label>
					<button type="button" class="button-link iboh-remove-holiday"><?php esc_html_e( 'Remove', 'opening-hours-banner' ); ?></button>
				</div>
				<div class="iboh-day-body">
					<div class="iboh-ranges" data-iboh-ranges></div>
					<button type="button" class="button-link iboh-add-range"><?php esc_html_e( '+ Add hours', 'opening-hours-banner' ); ?></button>
				</div>
			</div>
		</template>
		<?php
	}

	/**
	 * Output the IT Boffins branded header bar.
	 */
	private function brand_header() {
		?>
		<div class="iboh-brandbar">
			<span class="iboh-logo">
				<?php
				// Trusted, static, internally-defined SVG markup.
				echo $this->logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</span>
			<span class="iboh-brandbar-side">
				<span class="iboh-eyebrow"><?php esc_html_e( 'Free plugin', 'opening-hours-banner' ); ?></span>
				<span class="iboh-ver">v<?php echo esc_html( IBOH_VERSION ); ?></span>
			</span>
		</div>
		<?php
	}

	/**
	 * The IT Boffins flask-wordmark, static, with the flask liquid tinted brand
	 * green. Fill is currentColor so CSS controls the rest.
	 *
	 * @return string
	 */
	private function logo_svg() {
		return '<svg class="iboh-logo-svg" viewBox="0 0 497 99" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="IT Boffins" fill="currentColor">'
			. '<path d="M345.31,10.31c5.21-2.38,10.84-.54,12.7,4.57,1.43,3.92.46,9.48-4.49,11.55-3.93,1.64-9.53.85-12.01-4.07-1.76-3.49-1.28-9.73,3.8-12.04Z"/>'
			. '<path d="M342.51,45.38l-22.88-.03v44.63s-14.37,0-14.37,0v-44.62s-22.76,0-22.76,0v44.63s-14.36-.02-14.36-.02l.02-44.6-14.22-.03.03-11.72,14.48-.26c-.5-7.91-1.71-17.11,5.37-21.07,6.36-3.56,14.35-1.59,21.67-1.95l-.16,12.04c-4.37-.25-9.21-.78-11.56.49-2.18,1.17-1.24,7.2-1.15,10.62h22.97c-.53-7.79-1.68-16.43,4.71-20.77,6.01-4.09,13.98-2.03,21.2-2.36l-.17,12.02c-3.87-.26-8.59-.73-10.46.54-2.02,1.38-1.22,6.97-1.16,10.55l37.03.07v56.43s-14.24.03-14.24.03l.02-44.61Z"/>'
			. '<g><path d="M387.45,57.26l-.39,32.71-14.28-.02v-56.28s13.73-.24,13.73-.24l1.06,8.67c6.69-9.43,14.54-10.86,24.39-8.39,8.26,2.07,15.18,10.52,15.24,20.29l.21,35.96h-14.25c-.2-12.69.57-24.86-.67-37.21-.68-6.77-8.62-8.69-13.87-7.93-5.6.81-11.08,5.37-11.17,12.44Z"/>'
			. '<path d="M472.84,91.09c-14.41,2-29.69-2-33.12-17.69l13.17-3.6c1.71,8.97,9.39,11.74,17.65,10.06,2.71-.55,5.48-2.77,5.64-5.35s-2.38-5.07-5.2-5.79l-17.43-4.47c-8.27-2.12-12.61-9.67-11.52-17.68,1.07-7.86,8-13.02,16.35-14.12,12.19-1.6,24.62,1.27,29.63,14.47l-13.27,4.41c-1.49-6-5.06-7.64-9.93-8.02-3.91-.3-11.34,1.57-8.32,7.56,3.47,6.88,33.92,2.48,33.57,22.6-.16,9.38-7.12,16.21-17.21,17.61Z"/></g>'
			. '<g><polygon points="74.88 90.97 59.9 91.09 59.91 25 36.63 25 36.85 11.41 97.94 11.4 98.12 25.01 74.85 24.99 74.88 90.97"/>'
			. '<rect x="11.33" y="11.38" width="15" height="79.79"/></g>'
			. '<g><path d="M184.5,60.71c.19,10.94-3.14,20.95-11.73,26.5s-21.01,6.58-28.44-.8l-2.53-2.51c-.75-.75-2.85-1.03-2.81-.11l.29,6.07-14.27.12V10.56c4.99-.24,9.04-.21,14.25-.01l.2,30.78c8.5-10.08,19.28-11.48,29.97-6.94,9.87,4.2,14.87,14.92,15.07,26.32ZM170.28,60.48c-.45-10.6-7.42-16.84-17.18-16.08-9.09.71-14.25,8.13-14.18,17.59.08,10.06,6.31,17.42,16.12,17.1,9.85-.32,15.7-7.89,15.24-18.61Z"/>'
			. '<g><path d="M226.43,32.78c9.36,2.61,15.75,9.17,19.08,16.18,4.58,9.64,3.33,18.75-1.28,28.13-5.92,12.06-20.6,17.48-33.71,13.87-12.66-3.49-22.07-14.59-22.66-28.52s7.46-25.61,21.26-29.79c.55-9.86.87-19.09-.75-28.66l19.37.12c-1.72,10.19-1.21,18.35-1.31,28.68ZM224.49,33.9l-.11-27.78h-12.67s.14,28.14.14,28.14c-16.5,4.29-26.22,21.44-20.83,36.65s22.72,23.88,38.22,16.74c11.48-5.29,18.58-16.64,17.22-28.93s-9.4-22.05-21.96-24.82Z"/>'
			. '<path fill="#00B86B" d="M195.43,49.09c12.39,4.47,29.48.78,47.55,4.75,3.72,11.33-1.09,23.83-11,30.09-9.76,6.17-22.79,4.99-31.3-2.69-8.9-8.03-11.4-20.94-5.25-32.15Z"/></g></g>'
			. '</svg>';
	}
}
