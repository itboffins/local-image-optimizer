<?php
/**
 * Admin screens: settings, bulk optimiser, and the Media library column.
 *
 * @package ITBoffins_Image_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin UI.
 */
class ITBOFFINS_IMAGE_SCOUT_Admin {

	/**
	 * Optimizer instance.
	 *
	 * @var ITBOFFINS_IMAGE_SCOUT_Optimizer
	 */
	private $optimizer;

	/**
	 * Constructor.
	 *
	 * @param ITBOFFINS_IMAGE_SCOUT_Optimizer $optimizer Optimizer.
	 */
	public function __construct( ITBOFFINS_IMAGE_SCOUT_Optimizer $optimizer ) {
		$this->optimizer = $optimizer;
	}

	/**
	 * Register admin hooks.
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menus' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
		add_filter( 'plugin_action_links_' . ITBOFFINS_IMAGE_SCOUT_BASENAME, array( $this, 'action_links' ) );

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
		$settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=itboffins-image-scout' ) ) . '">' . esc_html__( 'Settings', 'itboffins-image-scout' ) . '</a>';
		$bulk     = '<a href="' . esc_url( admin_url( 'upload.php?page=itboffins-image-scout-bulk' ) ) . '">' . esc_html__( 'Bulk Optimise', 'itboffins-image-scout' ) . '</a>';
		array_unshift( $links, $settings, $bulk );
		return $links;
	}

	/**
	 * Register the two admin pages.
	 */
	public function register_menus() {
		add_options_page(
			__( 'ITBoffins Image Scout', 'itboffins-image-scout' ),
			__( 'Image Scout', 'itboffins-image-scout' ),
			'manage_options',
			'itboffins-image-scout',
			array( $this, 'render_settings_page' )
		);

		add_media_page(
			__( 'Bulk Image Scout', 'itboffins-image-scout' ),
			__( 'Bulk Optimise', 'itboffins-image-scout' ),
			'manage_options',
			'itboffins-image-scout-bulk',
			array( $this, 'render_bulk_page' )
		);
	}

	/**
	 * Register settings with the Settings API.
	 */
	public function register_settings() {
		register_setting(
			'itboffins_image_scout_settings_group',
			ITBOFFINS_IMAGE_SCOUT_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'ITBOFFINS_IMAGE_SCOUT_Settings', 'sanitize' ),
				'default'           => ITBOFFINS_IMAGE_SCOUT_Settings::defaults(),
			)
		);
	}

	/**
	 * Enqueue admin assets on our screens and the Media library.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue( $hook ) {
		$screens = array( 'settings_page_itboffins-image-scout', 'media_page_itboffins-image-scout-bulk', 'upload.php' );
		if ( ! in_array( $hook, $screens, true ) ) {
			return;
		}

		// No remote assets: the brand look is carried by the CSS, and the font
		// stacks fall back to system fonts (keeping the plugin's "nothing leaves
		// your server" privacy promise and WordPress.org asset guidelines).
		wp_enqueue_style( 'itboffins-image-scout-admin', ITBOFFINS_IMAGE_SCOUT_URL . 'assets/admin.css', array(), ITBOFFINS_IMAGE_SCOUT_VERSION );
		wp_enqueue_script( 'itboffins-image-scout-admin', ITBOFFINS_IMAGE_SCOUT_URL . 'assets/admin.js', array(), ITBOFFINS_IMAGE_SCOUT_VERSION, true );

		wp_localize_script(
			'itboffins-image-scout-admin',
			'ITBoffinsImageScout',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'itboffins_image_scout_ajax' ),
				'i18n'    => array(
					'optimising'     => __( 'Optimising…', 'itboffins-image-scout' ),
					'restoring'      => __( 'Restoring…', 'itboffins-image-scout' ),
					'optimised'      => __( 'Optimised', 'itboffins-image-scout' ),
					'restored'       => __( 'Restored original', 'itboffins-image-scout' ),
					'failed'         => __( 'Failed', 'itboffins-image-scout' ),
					'done'           => __( 'All done!', 'itboffins-image-scout' ),
					'fetching'       => __( 'Fetching image list…', 'itboffins-image-scout' ),
					/* translators: %d: Number of images found. */
					'found'          => __( 'Found %d images.', 'itboffins-image-scout' ),
					'saved'          => __( 'saved', 'itboffins-image-scout' ),
					'webpCreated'    => __( 'WebP created', 'itboffins-image-scout' ),
					'noImages'       => __( 'No images found to optimise.', 'itboffins-image-scout' ),
					'confirmRestore' => __( 'Restore the original, un-optimised image? This will undo the compression for this item.', 'itboffins-image-scout' ),
					'scanning'       => __( 'Scanning…', 'itboffins-image-scout' ),
					'scanDone'       => __( 'Scan complete.', 'itboffins-image-scout' ),
					'scanFailed'     => __( 'Scan stopped after repeated errors.', 'itboffins-image-scout' ),
					'scanned'        => __( 'scanned', 'itboffins-image-scout' ),
					'createdWord'    => __( 'WebP created', 'itboffins-image-scout' ),
					'skippedWord'    => __( 'skipped', 'itboffins-image-scout' ),
					'recompressedWord' => __( 'recompressed', 'itboffins-image-scout' ),
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
		$caps     = ITBOFFINS_IMAGE_SCOUT_Capabilities::get();
		$settings = ITBOFFINS_IMAGE_SCOUT_Settings::all();
		?>
		<div class="wrap itboffins-image-scout-wrap">
			<?php $this->brand_header(); ?>
			<h1><?php esc_html_e( 'ITBoffins Image Scout', 'itboffins-image-scout' ); ?></h1>

			<div class="itboffins-image-scout-card">
				<h2><?php esc_html_e( 'Your server', 'itboffins-image-scout' ); ?></h2>
				<table class="itboffins-image-scout-caps">
					<tr>
						<th><?php esc_html_e( 'Active image engine', 'itboffins-image-scout' ); ?></th>
						<td><strong><?php echo esc_html( $caps['engine'] ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Image compression', 'itboffins-image-scout' ); ?></th>
						<td><?php echo wp_kses_post( $this->badge( $caps['can_compress'] ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WebP conversion', 'itboffins-image-scout' ); ?></th>
						<td>
							<?php echo wp_kses_post( $this->badge( $caps['can_webp'] ) ); ?>
							<?php if ( ! $caps['can_webp'] ) : ?>
								<span class="itboffins-image-scout-hint"><?php esc_html_e( 'Your server\'s image library was built without WebP support. Images will still be compressed; WebP files just won\'t be generated.', 'itboffins-image-scout' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>GD</th>
						<td><?php echo wp_kses_post( $this->badge( $caps['gd'] ) ); ?> <?php echo $caps['gd'] ? wp_kses_post( $this->badge( $caps['gd_webp'], esc_html__( 'WebP', 'itboffins-image-scout' ) ) ) : ''; ?></td>
					</tr>
					<tr>
						<th>Imagick</th>
						<td><?php echo wp_kses_post( $this->badge( $caps['imagick'] ) ); ?> <?php echo $caps['imagick'] ? wp_kses_post( $this->badge( $caps['imagick_webp'], esc_html__( 'WebP', 'itboffins-image-scout' ) ) ) : ''; ?></td>
					</tr>
				</table>
			</div>

			<form method="post" action="options.php" class="itboffins-image-scout-card">
				<?php settings_fields( 'itboffins_image_scout_settings_group' ); ?>
				<h2><?php esc_html_e( 'Settings', 'itboffins-image-scout' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Optimise new uploads', 'itboffins-image-scout' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[auto_optimize]" value="1" <?php checked( $settings['auto_optimize'] ); ?> />
								<?php esc_html_e( 'Automatically optimise images as they are uploaded', 'itboffins-image-scout' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="itboffins_image_scout_jpeg_quality"><?php esc_html_e( 'JPEG quality', 'itboffins-image-scout' ); ?></label></th>
						<td>
							<input type="number" min="40" max="100" id="itboffins_image_scout_jpeg_quality" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[jpeg_quality]" value="<?php echo esc_attr( $settings['jpeg_quality'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( '40–100. Around 82 is visually lossless for most photos. PNGs are kept lossless.', 'itboffins-image-scout' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WebP', 'itboffins-image-scout' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[webp_enabled]" value="1" <?php checked( $settings['webp_enabled'] ); ?> <?php disabled( ! $caps['can_webp'] ); ?> />
								<?php esc_html_e( 'Generate WebP copies of images', 'itboffins-image-scout' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="itboffins_image_scout_webp_quality"><?php esc_html_e( 'WebP quality', 'itboffins-image-scout' ); ?></label></th>
						<td>
							<input type="number" min="40" max="100" id="itboffins_image_scout_webp_quality" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[webp_quality]" value="<?php echo esc_attr( $settings['webp_quality'] ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Serve WebP', 'itboffins-image-scout' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[serve_webp]" value="1" <?php checked( $settings['serve_webp'] ); ?> />
								<?php esc_html_e( 'Deliver WebP to supported browsers automatically (via <picture>)', 'itboffins-image-scout' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rewrite images everywhere', 'itboffins-image-scout' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[full_page_webp]" value="1" <?php checked( $settings['full_page_webp'] ); ?> />
								<?php esc_html_e( 'Rewrite every image on the page, including those added by page builders (Elementor, Divi) and theme templates', 'itboffins-image-scout' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Recommended if your pages are built with a page builder. Uses whole-page output buffering. Leave off if you only use the block/classic editor. (CSS background images still cannot be converted.)', 'itboffins-image-scout' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Keep originals', 'itboffins-image-scout' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[keep_backup]" value="1" <?php checked( $settings['keep_backup'] ); ?> />
								<?php esc_html_e( 'Keep an untouched backup of every original so you can restore it later', 'itboffins-image-scout' ); ?>
							</label>
							<p class="description">
								<?php
								printf(
									/* translators: %s: backup folder name */
									esc_html__( 'Backups are off by default for new installs. When enabled, originals are stored in a randomised uploads subfolder (%s) with deny files where supported.', 'itboffins-image-scout' ),
									esc_html( $settings['backup_dir'] )
								);
								?>
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
			</form>

			<div class="itboffins-image-scout-card itboffins-image-scout-promo">
				<p>
					<?php
					printf(
						/* translators: %s: link to bulk optimiser */
						esc_html__( 'Already have images in your library? Run the %s to compress them all.', 'itboffins-image-scout' ),
						'<a href="' . esc_url( admin_url( 'upload.php?page=itboffins-image-scout-bulk' ) ) . '">' . esc_html__( 'Image Scout Bulk Tool', 'itboffins-image-scout' ) . '</a>'
					);
					?>
				</p>
				<p>
					<?php
					printf(
						/* translators: %s: link to itboffins.com */
						esc_html__( 'More free plugins at %s', 'itboffins-image-scout' ),
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
		$caps = ITBOFFINS_IMAGE_SCOUT_Capabilities::get();
		?>
		<div class="wrap itboffins-image-scout-wrap">
			<?php $this->brand_header(); ?>
			<h1><?php esc_html_e( 'Bulk Image Scout', 'itboffins-image-scout' ); ?></h1>

			<?php if ( ! $caps['can_compress'] ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'No usable image library (GD or Imagick) was detected on this server, so images cannot be optimised here.', 'itboffins-image-scout' ); ?></p></div>
			<?php else : ?>
				<div class="itboffins-image-scout-card">
					<p><?php esc_html_e( 'This will compress every JPEG and PNG in your Media Library and, where supported, generate WebP copies. You can keep using your site while it runs.', 'itboffins-image-scout' ); ?></p>

					<p>
						<button type="button" class="button button-primary button-hero" id="itboffins-image-scout-bulk-start"><?php esc_html_e( 'Start optimising', 'itboffins-image-scout' ); ?></button>
						<button type="button" class="button" id="itboffins-image-scout-bulk-stop" style="display:none;"><?php esc_html_e( 'Stop', 'itboffins-image-scout' ); ?></button>
					</p>

					<div id="itboffins-image-scout-progress" class="itboffins-image-scout-progress" style="display:none;">
						<div class="itboffins-image-scout-bar"><span id="itboffins-image-scout-bar-fill"></span></div>
						<p id="itboffins-image-scout-current" class="itboffins-image-scout-current"></p>
						<p id="itboffins-image-scout-progress-text"></p>
						<p id="itboffins-image-scout-savings" class="itboffins-image-scout-savings"></p>
					</div>

					<div id="itboffins-image-scout-log" class="itboffins-image-scout-log" style="display:none;"></div>
				</div>

				<div class="itboffins-image-scout-card">
					<span class="itboffins-image-scout-eyebrow"><?php esc_html_e( 'Advanced', 'itboffins-image-scout' ); ?></span>
					<h2><?php esc_html_e( 'Scan entire uploads folder', 'itboffins-image-scout' ); ?></h2>
					<p><?php esc_html_e( 'Generates WebP for every JPEG and PNG in your uploads folder — including images added by page builders (Elementor, Divi) or your theme that are not in the Media Library. Files that already have an up-to-date WebP are skipped.', 'itboffins-image-scout' ); ?></p>

					<p>
						<label>
							<input type="checkbox" id="itboffins-image-scout-scan-recompress" />
							<?php esc_html_e( 'Also recompress the original JPEGs while scanning', 'itboffins-image-scout' ); ?>
						</label>
					</p>

					<p>
						<button type="button" class="button button-primary button-hero" id="itboffins-image-scout-scan-start"><?php esc_html_e( 'Scan uploads folder', 'itboffins-image-scout' ); ?></button>
						<button type="button" class="button" id="itboffins-image-scout-scan-stop" style="display:none;"><?php esc_html_e( 'Stop', 'itboffins-image-scout' ); ?></button>
					</p>

					<div id="itboffins-image-scout-scan-progress" class="itboffins-image-scout-progress" style="display:none;">
						<div class="itboffins-image-scout-bar"><span id="itboffins-image-scout-scan-bar-fill"></span></div>
						<p id="itboffins-image-scout-scan-current" class="itboffins-image-scout-current"></p>
						<p id="itboffins-image-scout-scan-text"></p>
						<p id="itboffins-image-scout-scan-savings" class="itboffins-image-scout-savings"></p>
					</div>

					<div id="itboffins-image-scout-scan-log" class="itboffins-image-scout-log" style="display:none;"></div>
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
		$cols['itboffins-image-scout'] = __( 'Optimiser', 'itboffins-image-scout' );
		return $cols;
	}

	/**
	 * Render the Media list-table column for one attachment.
	 *
	 * @param string $column_name Column key.
	 * @param int    $post_id     Attachment ID.
	 */
	public function media_column_content( $column_name, $post_id ) {
		if ( 'itboffins-image-scout' !== $column_name ) {
			return;
		}

		$mime = get_post_mime_type( $post_id );
		if ( ! in_array( $mime, array( 'image/jpeg', 'image/png' ), true ) ) {
			echo '<span class="itboffins-image-scout-na">—</span>';
			return;
		}

		$stats = get_post_meta( $post_id, ITBOFFINS_IMAGE_SCOUT_META, true );
		echo '<div class="itboffins-image-scout-cell" data-id="' . esc_attr( $post_id ) . '">';

		if ( is_array( $stats ) && ! empty( $stats['optimized'] ) ) {
			printf(
				'<span class="itboffins-image-scout-status itboffins-image-scout-ok">%s</span><br><small>%s · %s</small>',
				esc_html__( 'Optimised', 'itboffins-image-scout' ),
				esc_html( sprintf( /* translators: %s percent saved */ __( '%s%% smaller', 'itboffins-image-scout' ), $stats['percent'] ) ),
				esc_html( size_format( $stats['saved'] ) )
			);
			if ( $this->optimizer->has_backup( $post_id ) ) {
				echo '<br><button type="button" class="button-link itboffins-image-scout-restore-btn" data-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Restore original', 'itboffins-image-scout' ) . '</button>';
			}
		} else {
			echo '<button type="button" class="button button-small itboffins-image-scout-optimize-btn" data-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Optimise', 'itboffins-image-scout' ) . '</button>';
		}

		echo '<span class="itboffins-image-scout-msg"></span>';
		echo '</div>';
	}

	/**
	 * Output the IT Boffins branded header bar.
	 */
	private function brand_header() {
		?>
		<div class="itboffins-image-scout-brandbar">
			<span class="itboffins-image-scout-logo">
				<img class="itboffins-image-scout-logo-img" src="<?php echo esc_url( add_query_arg( 'ver', ITBOFFINS_IMAGE_SCOUT_VERSION, ITBOFFINS_IMAGE_SCOUT_URL . 'assets/logo.png' ) ); ?>" alt="<?php esc_attr_e( 'IT Boffins', 'itboffins-image-scout' ); ?>" />
			</span>
			<span class="itboffins-image-scout-brandbar-side">
				<span class="itboffins-image-scout-eyebrow"><?php esc_html_e( 'Free plugin', 'itboffins-image-scout' ); ?></span>
				<span class="itboffins-image-scout-ver">v<?php echo esc_html( ITBOFFINS_IMAGE_SCOUT_VERSION ); ?></span>
			</span>
		</div>
		<?php
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
			$text = $label ? $label : __( 'Available', 'itboffins-image-scout' );
			return '<span class="itboffins-image-scout-badge itboffins-image-scout-badge-yes">' . esc_html( $text ) . '</span>';
		}
		$text = $label ? $label : __( 'Not available', 'itboffins-image-scout' );
		return '<span class="itboffins-image-scout-badge itboffins-image-scout-badge-no">' . esc_html( $text ) . '</span>';
	}
}
