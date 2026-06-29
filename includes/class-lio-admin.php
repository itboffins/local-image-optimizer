<?php
/**
 * Admin screens: settings, bulk optimiser, and the Media library column.
 *
 * @package Local_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI.
 */
class LIO_Admin {

	/**
	 * Optimizer instance.
	 *
	 * @var LIO_Optimizer
	 */
	private $optimizer;

	/**
	 * Constructor.
	 *
	 * @param LIO_Optimizer $optimizer Optimizer.
	 */
	public function __construct( LIO_Optimizer $optimizer ) {
		$this->optimizer = $optimizer;
	}

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . LIO_BASENAME, array( $this, 'action_links' ) );

		// Media library list-table column.
		add_filter( 'manage_media_columns', array( $this, 'media_column' ) );
		add_action( 'manage_media_custom_column', array( $this, 'media_column_content' ), 10, 2 );
	}

	/**
	 * Add "Settings" + "Bulk Optimise" links on the Plugins screen.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public function action_links( $links ) {
		$settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=local-image-optimiser' ) ) . '">' . esc_html__( 'Settings', 'local-image-optimiser' ) . '</a>';
		$bulk     = '<a href="' . esc_url( admin_url( 'upload.php?page=lio-bulk' ) ) . '">' . esc_html__( 'Bulk Optimise', 'local-image-optimiser' ) . '</a>';
		array_unshift( $links, $settings, $bulk );
		return $links;
	}

	/**
	 * Register the two admin pages.
	 */
	public function register_menus() {
		add_options_page(
			__( 'Local Image Optimiser', 'local-image-optimiser' ),
			__( 'Image Optimiser', 'local-image-optimiser' ),
			'manage_options',
			'local-image-optimiser',
			array( $this, 'render_settings_page' )
		);

		add_media_page(
			__( 'Bulk Image Optimiser', 'local-image-optimiser' ),
			__( 'Bulk Optimise', 'local-image-optimiser' ),
			'manage_options',
			'lio-bulk',
			array( $this, 'render_bulk_page' )
		);
	}

	/**
	 * Register settings with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'lio_settings_group',
			LIO_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'LIO_Settings', 'sanitize' ),
				'default'           => LIO_Settings::defaults(),
			)
		);
	}

	/**
	 * Enqueue admin assets on our screens and the Media library.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		$screens = array( 'settings_page_local-image-optimiser', 'media_page_lio-bulk', 'upload.php' );
		if ( ! in_array( $hook, $screens, true ) ) {
			return;
		}

		// No remote assets: the brand look is carried by the CSS, and the font
		// stacks fall back to system fonts (keeping the plugin's "nothing leaves
		// your server" privacy promise and WordPress.org asset guidelines).
		wp_enqueue_style( 'lio-admin', LIO_URL . 'assets/admin.css', array(), LIO_VERSION );
		wp_enqueue_script( 'lio-admin', LIO_URL . 'assets/admin.js', array(), LIO_VERSION, true );

		wp_localize_script(
			'lio-admin',
			'LIO',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lio_ajax' ),
				'i18n'    => array(
					'optimising'     => __( 'Optimising…', 'local-image-optimiser' ),
					'restoring'      => __( 'Restoring…', 'local-image-optimiser' ),
					'optimised'      => __( 'Optimised', 'local-image-optimiser' ),
					'restored'       => __( 'Restored original', 'local-image-optimiser' ),
					'failed'         => __( 'Failed', 'local-image-optimiser' ),
					'done'           => __( 'All done!', 'local-image-optimiser' ),
					'fetching'       => __( 'Fetching image list…', 'local-image-optimiser' ),
					/* translators: %d: Number of images found. */
					'found'          => __( 'Found %d images.', 'local-image-optimiser' ),
					'saved'          => __( 'saved', 'local-image-optimiser' ),
					'webpCreated'    => __( 'WebP created', 'local-image-optimiser' ),
					'noImages'       => __( 'No images found to optimise.', 'local-image-optimiser' ),
					'confirmRestore' => __( 'Restore the original, un-optimised image? This will undo the compression for this item.', 'local-image-optimiser' ),
					'scanning'       => __( 'Scanning…', 'local-image-optimiser' ),
					'scanDone'       => __( 'Scan complete.', 'local-image-optimiser' ),
					'scanFailed'     => __( 'Scan stopped after repeated errors.', 'local-image-optimiser' ),
					'scanned'        => __( 'scanned', 'local-image-optimiser' ),
					'createdWord'    => __( 'WebP created', 'local-image-optimiser' ),
					'skippedWord'    => __( 'skipped', 'local-image-optimiser' ),
					'recompressedWord' => __( 'recompressed', 'local-image-optimiser' ),
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
		$caps     = LIO_Capabilities::get();
		$settings = LIO_Settings::all();
		?>
		<div class="wrap lio-wrap">
			<?php $this->brand_header(); ?>
			<h1><?php esc_html_e( 'Local Image Optimiser', 'local-image-optimiser' ); ?></h1>

			<div class="lio-card">
				<h2><?php esc_html_e( 'Your server', 'local-image-optimiser' ); ?></h2>
				<table class="lio-caps">
					<tr>
						<th><?php esc_html_e( 'Active image engine', 'local-image-optimiser' ); ?></th>
						<td><strong><?php echo esc_html( $caps['engine'] ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Image compression', 'local-image-optimiser' ); ?></th>
						<td><?php echo wp_kses_post( $this->badge( $caps['can_compress'] ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WebP conversion', 'local-image-optimiser' ); ?></th>
						<td>
							<?php echo wp_kses_post( $this->badge( $caps['can_webp'] ) ); ?>
							<?php if ( ! $caps['can_webp'] ) : ?>
								<span class="lio-hint"><?php esc_html_e( 'Your server\'s image library was built without WebP support. Images will still be compressed; WebP files just won\'t be generated.', 'local-image-optimiser' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>GD</th>
						<td><?php echo wp_kses_post( $this->badge( $caps['gd'] ) ); ?> <?php echo $caps['gd'] ? wp_kses_post( $this->badge( $caps['gd_webp'], esc_html__( 'WebP', 'local-image-optimiser' ) ) ) : ''; ?></td>
					</tr>
					<tr>
						<th>Imagick</th>
						<td><?php echo wp_kses_post( $this->badge( $caps['imagick'] ) ); ?> <?php echo $caps['imagick'] ? wp_kses_post( $this->badge( $caps['imagick_webp'], esc_html__( 'WebP', 'local-image-optimiser' ) ) ) : ''; ?></td>
					</tr>
				</table>
			</div>

			<form method="post" action="options.php" class="lio-card">
				<?php settings_fields( 'lio_settings_group' ); ?>
				<h2><?php esc_html_e( 'Settings', 'local-image-optimiser' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Optimise new uploads', 'local-image-optimiser' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[auto_optimize]" value="1" <?php checked( $settings['auto_optimize'] ); ?> />
								<?php esc_html_e( 'Automatically optimise images as they are uploaded', 'local-image-optimiser' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lio_jpeg_quality"><?php esc_html_e( 'JPEG quality', 'local-image-optimiser' ); ?></label></th>
						<td>
							<input type="number" min="40" max="100" id="lio_jpeg_quality" name="<?php echo esc_attr( LIO_OPTION ); ?>[jpeg_quality]" value="<?php echo esc_attr( $settings['jpeg_quality'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( '40–100. Around 82 is visually lossless for most photos. PNGs are kept lossless.', 'local-image-optimiser' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WebP', 'local-image-optimiser' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[webp_enabled]" value="1" <?php checked( $settings['webp_enabled'] ); ?> <?php disabled( ! $caps['can_webp'] ); ?> />
								<?php esc_html_e( 'Generate WebP copies of images', 'local-image-optimiser' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lio_webp_quality"><?php esc_html_e( 'WebP quality', 'local-image-optimiser' ); ?></label></th>
						<td>
							<input type="number" min="40" max="100" id="lio_webp_quality" name="<?php echo esc_attr( LIO_OPTION ); ?>[webp_quality]" value="<?php echo esc_attr( $settings['webp_quality'] ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Serve WebP', 'local-image-optimiser' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[serve_webp]" value="1" <?php checked( $settings['serve_webp'] ); ?> />
								<?php esc_html_e( 'Deliver WebP to supported browsers automatically (via <picture>)', 'local-image-optimiser' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rewrite images everywhere', 'local-image-optimiser' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[full_page_webp]" value="1" <?php checked( $settings['full_page_webp'] ); ?> />
								<?php esc_html_e( 'Rewrite every image on the page, including those added by page builders (Elementor, Divi) and theme templates', 'local-image-optimiser' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Recommended if your pages are built with a page builder. Uses whole-page output buffering. Leave off if you only use the block/classic editor. (CSS background images still cannot be converted.)', 'local-image-optimiser' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Keep originals', 'local-image-optimiser' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[keep_backup]" value="1" <?php checked( $settings['keep_backup'] ); ?> />
								<?php esc_html_e( 'Keep an untouched backup of every original so you can restore it later', 'local-image-optimiser' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %s: backup folder name */
									esc_html__( 'Backups are off by default for new installs. When enabled, originals are stored in a randomised uploads subfolder (%s) with deny files where supported.', 'local-image-optimiser' ),
									esc_html( $settings['backup_dir'] )
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="lio-card lio-promo">
				<p>
					<?php
					printf(
						/* translators: %s: link to bulk optimiser */
						esc_html__( 'Already have images in your library? Run the %s to compress them all.', 'local-image-optimiser' ),
						'<a href="' . esc_url( admin_url( 'upload.php?page=lio-bulk' ) ) . '">' . esc_html__( 'Bulk Optimiser', 'local-image-optimiser' ) . '</a>'
					);
					?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: link to itboffins.com */
						esc_html__( 'More free plugins at %s', 'local-image-optimiser' ),
						'<a href="https://itboffins.com/" target="_blank" rel="noopener">itboffins.com</a>'
					);
					?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the bulk optimiser screen.
	 */
	public function render_bulk_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$caps = LIO_Capabilities::get();
		?>
		<div class="wrap lio-wrap">
			<?php $this->brand_header(); ?>
			<h1><?php esc_html_e( 'Bulk Image Optimiser', 'local-image-optimiser' ); ?></h1>

			<?php if ( ! $caps['can_compress'] ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'No usable image library (GD or Imagick) was detected on this server, so images cannot be optimised here.', 'local-image-optimiser' ); ?></p></div>
			<?php else : ?>
				<div class="lio-card">
					<p><?php esc_html_e( 'This will compress every JPEG and PNG in your Media Library and, where supported, generate WebP copies. You can keep using your site while it runs.', 'local-image-optimiser' ); ?></p>

					<p>
						<button type="button" class="button button-primary button-hero" id="lio-bulk-start"><?php esc_html_e( 'Start optimising', 'local-image-optimiser' ); ?></button>
						<button type="button" class="button" id="lio-bulk-stop" style="display:none;"><?php esc_html_e( 'Stop', 'local-image-optimiser' ); ?></button>
					</p>

					<div id="lio-progress" class="lio-progress" style="display:none;">
						<div class="lio-bar"><span id="lio-bar-fill"></span></div>
						<p id="lio-current" class="lio-current"></p>
						<p id="lio-progress-text"></p>
						<p id="lio-savings" class="lio-savings"></p>
					</div>

					<div id="lio-log" class="lio-log" style="display:none;"></div>
				</div>

				<div class="lio-card">
					<span class="lio-eyebrow"><?php esc_html_e( 'Advanced', 'local-image-optimiser' ); ?></span>
					<h2><?php esc_html_e( 'Scan entire uploads folder', 'local-image-optimiser' ); ?></h2>
					<p><?php esc_html_e( 'Generates WebP for every JPEG and PNG in your uploads folder — including images added by page builders (Elementor, Divi) or your theme that are not in the Media Library. Files that already have an up-to-date WebP are skipped.', 'local-image-optimiser' ); ?></p>

					<p>
						<label>
							<input type="checkbox" id="lio-scan-recompress" />
							<?php esc_html_e( 'Also recompress the original JPEGs while scanning', 'local-image-optimiser' ); ?>
						</label>
					</p>

					<p>
						<button type="button" class="button button-primary button-hero" id="lio-scan-start"><?php esc_html_e( 'Scan uploads folder', 'local-image-optimiser' ); ?></button>
						<button type="button" class="button" id="lio-scan-stop" style="display:none;"><?php esc_html_e( 'Stop', 'local-image-optimiser' ); ?></button>
					</p>

					<div id="lio-scan-progress" class="lio-progress" style="display:none;">
						<div class="lio-bar"><span id="lio-scan-bar-fill"></span></div>
						<p id="lio-scan-current" class="lio-current"></p>
						<p id="lio-scan-text"></p>
						<p id="lio-scan-savings" class="lio-savings"></p>
					</div>

					<div id="lio-scan-log" class="lio-log" style="display:none;"></div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Add an "Optimiser" column to the Media list table.
	 *
	 * @param array $cols Columns.
	 * @return array
	 */
	public function media_column( $cols ) {
		$cols['lio'] = __( 'Optimiser', 'local-image-optimiser' );
		return $cols;
	}

	/**
	 * Render the Media list-table column for one attachment.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id     Attachment ID.
	 */
	public function media_column_content( $column_name, $post_id ) {
		if ( 'lio' !== $column_name ) {
			return;
		}

		$mime = get_post_mime_type( $post_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			echo '<span class="lio-na">—</span>';
			return;
		}

		$stats = get_post_meta( $post_id, LIO_META, true );
		echo '<div class="lio-cell" data-id="' . esc_attr( $post_id ) . '">';

		if ( is_array( $stats ) && ! empty( $stats['optimized'] ) ) {
			printf(
				'<span class="lio-status lio-ok">%s</span><br><small>%s · %s</small>',
				esc_html__( 'Optimised', 'local-image-optimiser' ),
				esc_html( sprintf( /* translators: %s percent saved */ __( '%s%% smaller', 'local-image-optimiser' ), $stats['percent'] ) ),
				esc_html( size_format( $stats['saved'] ) )
			);
			if ( $this->optimizer->has_backup( $post_id ) ) {
				echo '<br><button type="button" class="button-link lio-restore-btn" data-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Restore original', 'local-image-optimiser' ) . '</button>';
			}
		} else {
			echo '<button type="button" class="button button-small lio-optimize-btn" data-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Optimise', 'local-image-optimiser' ) . '</button>';
		}

		echo '<span class="lio-msg"></span>';
		echo '</div>';
	}

	/**
	 * Output the IT Boffins branded header bar.
	 */
	private function brand_header() {
		?>
		<div class="lio-brandbar">
			<span class="lio-logo">
				<?php
				// Trusted, static, internally-defined SVG markup.
				echo $this->logo_svg(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				?>
			</span>
			<span class="lio-brandbar-side">
				<span class="lio-eyebrow"><?php esc_html_e( 'Free plugin', 'local-image-optimiser' ); ?></span>
				<span class="lio-ver">v<?php echo esc_html( LIO_VERSION ); ?></span>
			</span>
		</div>
		<?php
	}

	/**
	 * The IT Boffins flask-wordmark, static (animation stripped) with the flask
	 * liquid tinted brand green. Fill is currentColor so CSS controls the rest.
	 *
	 * @return string
	 */
	private function logo_svg() {
		return '<svg class="lio-logo-svg" viewBox="0 0 497 99" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="IT Boffins" fill="currentColor">'
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

	/**
	 * Render a small yes/no badge.
	 *
	 * @param bool   $ok    Condition.
	 * @param string $label Optional label override.
	 * @return string
	 */
	private function badge( $ok, $label = '' ) {
		if ( $ok ) {
			$text = $label ? $label : __( 'Available', 'local-image-optimiser' );
			return '<span class="lio-badge lio-badge-yes">' . esc_html( $text ) . '</span>';
		}
		$text = $label ? $label : __( 'Not available', 'local-image-optimiser' );
		return '<span class="lio-badge lio-badge-no">' . esc_html( $text ) . '</span>';
	}
}
