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
		$settings = '<a href="' . esc_url( admin_url( 'options-general.php?page=local-image-optimizer' ) ) . '">' . esc_html__( 'Settings', 'local-image-optimizer' ) . '</a>';
		$bulk     = '<a href="' . esc_url( admin_url( 'upload.php?page=lio-bulk' ) ) . '">' . esc_html__( 'Bulk Optimise', 'local-image-optimizer' ) . '</a>';
		array_unshift( $links, $settings, $bulk );
		return $links;
	}

	/**
	 * Register the two admin pages.
	 */
	public function register_menus() {
		add_options_page(
			__( 'Local Image Optimiser', 'local-image-optimizer' ),
			__( 'Image Optimiser', 'local-image-optimizer' ),
			'manage_options',
			'local-image-optimizer',
			array( $this, 'render_settings_page' )
		);

		add_media_page(
			__( 'Bulk Image Optimiser', 'local-image-optimizer' ),
			__( 'Bulk Optimise', 'local-image-optimizer' ),
			'upload_files',
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
		$screens = array( 'settings_page_local-image-optimizer', 'media_page_lio-bulk', 'upload.php' );
		if ( ! in_array( $hook, $screens, true ) ) {
			return;
		}

		wp_enqueue_style( 'lio-admin', LIO_URL . 'assets/admin.css', array(), LIO_VERSION );
		wp_enqueue_script( 'lio-admin', LIO_URL . 'assets/admin.js', array(), LIO_VERSION, true );

		wp_localize_script(
			'lio-admin',
			'LIO',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'lio_ajax' ),
				'i18n'    => array(
					'optimising'     => __( 'Optimising…', 'local-image-optimizer' ),
					'restoring'      => __( 'Restoring…', 'local-image-optimizer' ),
					'optimised'      => __( 'Optimised', 'local-image-optimizer' ),
					'restored'       => __( 'Restored original', 'local-image-optimizer' ),
					'failed'         => __( 'Failed', 'local-image-optimizer' ),
					'done'           => __( 'All done!', 'local-image-optimizer' ),
					'fetching'       => __( 'Fetching image list…', 'local-image-optimizer' ),
					'found'          => __( 'Found %d images.', 'local-image-optimizer' ),
					'saved'          => __( 'saved', 'local-image-optimizer' ),
					'webpCreated'    => __( 'WebP created', 'local-image-optimizer' ),
					'noImages'       => __( 'No images found to optimise.', 'local-image-optimizer' ),
					'confirmRestore' => __( 'Restore the original, un-optimised image? This will undo the compression for this item.', 'local-image-optimizer' ),
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
			<h1><?php esc_html_e( 'Local Image Optimiser', 'local-image-optimizer' ); ?></h1>

			<div class="lio-card">
				<h2><?php esc_html_e( 'Your server', 'local-image-optimizer' ); ?></h2>
				<table class="lio-caps">
					<tr>
						<th><?php esc_html_e( 'Active image engine', 'local-image-optimizer' ); ?></th>
						<td><strong><?php echo esc_html( $caps['engine'] ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Image compression', 'local-image-optimizer' ); ?></th>
						<td><?php echo $this->badge( $caps['can_compress'] ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'WebP conversion', 'local-image-optimizer' ); ?></th>
						<td>
							<?php echo $this->badge( $caps['can_webp'] ); ?>
							<?php if ( ! $caps['can_webp'] ) : ?>
								<span class="lio-hint"><?php esc_html_e( 'Your server\'s image library was built without WebP support. Images will still be compressed; WebP files just won\'t be generated.', 'local-image-optimizer' ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th>GD</th>
						<td><?php echo $this->badge( $caps['gd'] ); ?> <?php echo $caps['gd'] ? $this->badge( $caps['gd_webp'], __( 'WebP', 'local-image-optimizer' ) ) : ''; ?></td>
					</tr>
					<tr>
						<th>Imagick</th>
						<td><?php echo $this->badge( $caps['imagick'] ); ?> <?php echo $caps['imagick'] ? $this->badge( $caps['imagick_webp'], __( 'WebP', 'local-image-optimizer' ) ) : ''; ?></td>
					</tr>
				</table>
			</div>

			<form method="post" action="options.php" class="lio-card">
				<?php settings_fields( 'lio_settings_group' ); ?>
				<h2><?php esc_html_e( 'Settings', 'local-image-optimizer' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Optimise new uploads', 'local-image-optimizer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[auto_optimize]" value="1" <?php checked( $settings['auto_optimize'] ); ?> />
								<?php esc_html_e( 'Automatically optimise images as they are uploaded', 'local-image-optimizer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lio_jpeg_quality"><?php esc_html_e( 'JPEG quality', 'local-image-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="40" max="100" id="lio_jpeg_quality" name="<?php echo esc_attr( LIO_OPTION ); ?>[jpeg_quality]" value="<?php echo esc_attr( $settings['jpeg_quality'] ); ?>" class="small-text" />
							<p class="description"><?php esc_html_e( '40–100. Around 82 is visually lossless for most photos. PNGs are kept lossless.', 'local-image-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WebP', 'local-image-optimizer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[webp_enabled]" value="1" <?php checked( $settings['webp_enabled'] ); ?> <?php disabled( ! $caps['can_webp'] ); ?> />
								<?php esc_html_e( 'Generate WebP copies of images', 'local-image-optimizer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="lio_webp_quality"><?php esc_html_e( 'WebP quality', 'local-image-optimizer' ); ?></label></th>
						<td>
							<input type="number" min="40" max="100" id="lio_webp_quality" name="<?php echo esc_attr( LIO_OPTION ); ?>[webp_quality]" value="<?php echo esc_attr( $settings['webp_quality'] ); ?>" class="small-text" />
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Serve WebP', 'local-image-optimizer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[serve_webp]" value="1" <?php checked( $settings['serve_webp'] ); ?> />
								<?php esc_html_e( 'Deliver WebP to supported browsers automatically (via <picture>)', 'local-image-optimizer' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Rewrite images everywhere', 'local-image-optimizer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[full_page_webp]" value="1" <?php checked( $settings['full_page_webp'] ); ?> />
								<?php esc_html_e( 'Rewrite every image on the page, including those added by page builders (Elementor, Divi) and theme templates', 'local-image-optimizer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Recommended if your pages are built with a page builder. Uses whole-page output buffering. Leave off if you only use the block/classic editor. (CSS background images still cannot be converted.)', 'local-image-optimizer' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Keep originals', 'local-image-optimizer' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( LIO_OPTION ); ?>[keep_backup]" value="1" <?php checked( $settings['keep_backup'] ); ?> />
								<?php esc_html_e( 'Keep an untouched backup of every original so you can restore it later', 'local-image-optimizer' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'Backups live in /wp-content/uploads/lio-originals. Uses extra disk space but lets you undo.', 'local-image-optimizer' ); ?></p>
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
						esc_html__( 'Already have images in your library? Run the %s to compress them all.', 'local-image-optimizer' ),
						'<a href="' . esc_url( admin_url( 'upload.php?page=lio-bulk' ) ) . '">' . esc_html__( 'Bulk Optimiser', 'local-image-optimizer' ) . '</a>'
					);
					?>
				</p>
				<p><?php printf( esc_html__( 'More free plugins at %s', 'local-image-optimizer' ), '<a href="https://itboffins.com/" target="_blank" rel="noopener">itboffins.com</a>' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the bulk optimiser screen.
	 */
	public function render_bulk_page() {
		if ( ! current_user_can( 'upload_files' ) ) {
			return;
		}
		$caps = LIO_Capabilities::get();
		?>
		<div class="wrap lio-wrap">
			<h1><?php esc_html_e( 'Bulk Image Optimiser', 'local-image-optimizer' ); ?></h1>

			<?php if ( ! $caps['can_compress'] ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'No usable image library (GD or Imagick) was detected on this server, so images cannot be optimised here.', 'local-image-optimizer' ); ?></p></div>
			<?php else : ?>
				<div class="lio-card">
					<p><?php esc_html_e( 'This will compress every JPEG and PNG in your Media Library and, where supported, generate WebP copies. You can keep using your site while it runs.', 'local-image-optimizer' ); ?></p>

					<p>
						<button type="button" class="button button-primary button-hero" id="lio-bulk-start"><?php esc_html_e( 'Start optimising', 'local-image-optimizer' ); ?></button>
						<button type="button" class="button" id="lio-bulk-stop" style="display:none;"><?php esc_html_e( 'Stop', 'local-image-optimizer' ); ?></button>
					</p>

					<div id="lio-progress" class="lio-progress" style="display:none;">
						<div class="lio-bar"><span id="lio-bar-fill"></span></div>
						<p id="lio-current" class="lio-current"></p>
						<p id="lio-progress-text"></p>
						<p id="lio-savings" class="lio-savings"></p>
					</div>

					<div id="lio-log" class="lio-log" style="display:none;"></div>
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
		$cols['lio'] = __( 'Optimiser', 'local-image-optimizer' );
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
				esc_html__( 'Optimised', 'local-image-optimizer' ),
				esc_html( sprintf( /* translators: %s percent saved */ __( '%s%% smaller', 'local-image-optimizer' ), $stats['percent'] ) ),
				esc_html( size_format( $stats['saved'] ) )
			);
			echo '<br><button type="button" class="button-link lio-restore-btn" data-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Restore original', 'local-image-optimizer' ) . '</button>';
		} else {
			echo '<button type="button" class="button button-small lio-optimize-btn" data-id="' . esc_attr( $post_id ) . '">' . esc_html__( 'Optimise', 'local-image-optimizer' ) . '</button>';
		}

		echo '<span class="lio-msg"></span>';
		echo '</div>';
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
			$text = $label ? $label : __( 'Available', 'local-image-optimizer' );
			return '<span class="lio-badge lio-badge-yes">' . esc_html( $text ) . '</span>';
		}
		$text = $label ? $label : __( 'Not available', 'local-image-optimizer' );
		return '<span class="lio-badge lio-badge-no">' . esc_html( $text ) . '</span>';
	}
}
