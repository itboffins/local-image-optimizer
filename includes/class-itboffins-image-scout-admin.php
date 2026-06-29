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

			<div class="itboffins-image-scout-card itboffins-image-scout-summary">
				<span class="itboffins-image-scout-eyebrow"><?php esc_html_e( 'Plain-English setup', 'itboffins-image-scout' ); ?></span>
				<h2><?php esc_html_e( 'What this does for your site', 'itboffins-image-scout' ); ?></h2>
				<p><?php esc_html_e( 'Image Scout helps keep pages lighter by shrinking photos, creating faster WebP copies when your host allows it, and finding images that page builders or themes may hide from the Media Library.', 'itboffins-image-scout' ); ?></p>
				<div class="itboffins-image-scout-benefits">
					<div class="itboffins-image-scout-benefit">
						<strong><?php esc_html_e( 'Pages feel faster', 'itboffins-image-scout' ); ?></strong>
						<span><?php esc_html_e( 'Smaller image files mean visitors wait less, especially on mobile.', 'itboffins-image-scout' ); ?></span>
					</div>
					<div class="itboffins-image-scout-benefit">
						<strong><?php esc_html_e( 'No accounts or credits', 'itboffins-image-scout' ); ?></strong>
						<span><?php esc_html_e( 'Everything happens on your own hosting, so images are not sent to another service.', 'itboffins-image-scout' ); ?></span>
					</div>
					<div class="itboffins-image-scout-benefit">
						<strong><?php esc_html_e( 'Builder images included', 'itboffins-image-scout' ); ?></strong>
						<span><?php esc_html_e( 'The scout can find upload-folder images used by themes and page builders.', 'itboffins-image-scout' ); ?></span>
					</div>
				</div>
			</div>

			<div class="itboffins-image-scout-card">
				<h2><?php echo esc_html( $caps['can_compress'] ? __( 'Your site is ready to help with images', 'itboffins-image-scout' ) : __( 'Your host needs one image tool enabled', 'itboffins-image-scout' ) ); ?></h2>
				<p><?php esc_html_e( 'This quick check shows what your web host can do. You do not need to install anything extra in WordPress.', 'itboffins-image-scout' ); ?></p>
				<table class="itboffins-image-scout-caps">
					<tr>
						<th><?php esc_html_e( 'Make images smaller', 'itboffins-image-scout' ); ?></th>
						<td>
							<?php echo wp_kses_post( $this->badge( $caps['can_compress'], esc_html__( 'Ready', 'itboffins-image-scout' ), esc_html__( 'Ask host', 'itboffins-image-scout' ) ) ); ?>
							<span class="itboffins-image-scout-hint"><?php echo esc_html( $caps['can_compress'] ? __( 'Your site can shrink JPEG and PNG uploads.', 'itboffins-image-scout' ) : __( 'Your host needs GD or Imagick enabled before images can be optimised.', 'itboffins-image-scout' ) ); ?></span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Create WebP copies', 'itboffins-image-scout' ); ?></th>
						<td>
							<?php echo wp_kses_post( $this->badge( $caps['can_webp'], esc_html__( 'Ready', 'itboffins-image-scout' ), esc_html__( 'Not on this host', 'itboffins-image-scout' ) ) ); ?>
							<span class="itboffins-image-scout-hint"><?php echo esc_html( $caps['can_webp'] ? __( 'Your site can make modern WebP versions for browsers that support them.', 'itboffins-image-scout' ) : __( 'Image Scout can still compress images, but WebP copies need WebP support from your host.', 'itboffins-image-scout' ) ); ?></span>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Image tool found', 'itboffins-image-scout' ); ?></th>
						<td><strong><?php echo esc_html( $caps['engine'] ); ?></strong><span class="itboffins-image-scout-hint"><?php esc_html_e( 'This is the built-in server tool WordPress will use behind the scenes.', 'itboffins-image-scout' ); ?></span></td>
					</tr>
				</table>
			</div>

			<form method="post" action="options.php" class="itboffins-image-scout-card itboffins-image-scout-settings-card">
				<?php settings_fields( 'itboffins_image_scout_settings_group' ); ?>
				<span class="itboffins-image-scout-eyebrow"><?php esc_html_e( 'Recommended for most sites', 'itboffins-image-scout' ); ?></span>
				<h2><?php esc_html_e( 'Choose what Image Scout should do', 'itboffins-image-scout' ); ?></h2>
				<p><?php esc_html_e( 'Leave the defaults on if you want the simplest setup. Each choice below explains the benefit in plain English.', 'itboffins-image-scout' ); ?></p>

				<div class="itboffins-image-scout-setting-list">
					<div class="itboffins-image-scout-setting">
						<div class="itboffins-image-scout-setting-copy">
							<h3><?php esc_html_e( 'Make every new upload smaller', 'itboffins-image-scout' ); ?></h3>
							<p><?php esc_html_e( 'Best for most sites. When you add a photo to WordPress, Image Scout shrinks it automatically before it can slow a page down.', 'itboffins-image-scout' ); ?></p>
						</div>
						<label class="itboffins-image-scout-toggle">
							<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[auto_optimize]" value="1" <?php checked( $settings['auto_optimize'] ); ?> />
							<span><?php esc_html_e( 'On for new images', 'itboffins-image-scout' ); ?></span>
						</label>
					</div>

					<div class="itboffins-image-scout-setting">
						<div class="itboffins-image-scout-setting-copy">
							<h3><?php esc_html_e( 'Photo quality', 'itboffins-image-scout' ); ?></h3>
							<p><?php esc_html_e( '82 is a good everyday balance: photos still look sharp, but the files are smaller. Raise it for image-heavy portfolios; lower it when speed matters more than maximum detail.', 'itboffins-image-scout' ); ?></p>
						</div>
						<label class="itboffins-image-scout-number" for="itboffins_image_scout_jpeg_quality">
							<span><?php esc_html_e( 'Quality', 'itboffins-image-scout' ); ?></span>
							<input type="number" min="40" max="100" id="itboffins_image_scout_jpeg_quality" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[jpeg_quality]" value="<?php echo esc_attr( $settings['jpeg_quality'] ); ?>" class="small-text" />
							<small><?php esc_html_e( '40 to 100', 'itboffins-image-scout' ); ?></small>
						</label>
					</div>

					<div class="itboffins-image-scout-setting">
						<div class="itboffins-image-scout-setting-copy">
							<h3><?php esc_html_e( 'Create faster WebP copies', 'itboffins-image-scout' ); ?></h3>
							<p><?php esc_html_e( 'WebP is a modern image format that is often much smaller than JPEG or PNG. Image Scout keeps the original and adds a smaller WebP copy where your host supports it.', 'itboffins-image-scout' ); ?></p>
							<?php if ( ! $caps['can_webp'] ) : ?>
								<p class="itboffins-image-scout-warning"><?php esc_html_e( 'Your host cannot create WebP images yet, so this is unavailable here. Normal compression still works.', 'itboffins-image-scout' ); ?></p>
							<?php endif; ?>
						</div>
						<label class="itboffins-image-scout-toggle">
							<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[webp_enabled]" value="1" <?php checked( $settings['webp_enabled'] ); ?> <?php disabled( ! $caps['can_webp'] ); ?> />
							<span><?php esc_html_e( 'Create WebP files', 'itboffins-image-scout' ); ?></span>
						</label>
					</div>

					<div class="itboffins-image-scout-setting">
						<div class="itboffins-image-scout-setting-copy">
							<h3><?php esc_html_e( 'WebP quality', 'itboffins-image-scout' ); ?></h3>
							<p><?php esc_html_e( '80 is a sensible starting point. Higher values keep more detail but make bigger files; lower values make smaller files.', 'itboffins-image-scout' ); ?></p>
						</div>
						<label class="itboffins-image-scout-number" for="itboffins_image_scout_webp_quality">
							<span><?php esc_html_e( 'Quality', 'itboffins-image-scout' ); ?></span>
							<input type="number" min="40" max="100" id="itboffins_image_scout_webp_quality" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[webp_quality]" value="<?php echo esc_attr( $settings['webp_quality'] ); ?>" class="small-text" />
							<small><?php esc_html_e( '40 to 100', 'itboffins-image-scout' ); ?></small>
						</label>
					</div>

					<div class="itboffins-image-scout-setting">
						<div class="itboffins-image-scout-setting-copy">
							<h3><?php esc_html_e( 'Show the faster version to visitors', 'itboffins-image-scout' ); ?></h3>
							<p><?php esc_html_e( 'When a visitor\'s browser supports WebP, they get the smaller WebP image automatically. Older browsers still get the normal image.', 'itboffins-image-scout' ); ?></p>
						</div>
						<label class="itboffins-image-scout-toggle">
							<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[serve_webp]" value="1" <?php checked( $settings['serve_webp'] ); ?> />
							<span><?php esc_html_e( 'Use WebP when possible', 'itboffins-image-scout' ); ?></span>
						</label>
					</div>

					<div class="itboffins-image-scout-setting">
						<div class="itboffins-image-scout-setting-copy">
							<h3><?php esc_html_e( 'Find images page builders hide from WordPress', 'itboffins-image-scout' ); ?></h3>
							<p><?php esc_html_e( 'Turn this on if you use Elementor, Divi, a custom theme, or imported pages. It checks the finished page for images that do not pass through the normal WordPress editor.', 'itboffins-image-scout' ); ?></p>
							<p class="itboffins-image-scout-hint"><?php esc_html_e( 'Leave it off for very simple sites that only use the block editor.', 'itboffins-image-scout' ); ?></p>
						</div>
						<label class="itboffins-image-scout-toggle">
							<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[full_page_webp]" value="1" <?php checked( $settings['full_page_webp'] ); ?> />
							<span><?php esc_html_e( 'Check the whole page', 'itboffins-image-scout' ); ?></span>
						</label>
					</div>

					<div class="itboffins-image-scout-setting">
						<div class="itboffins-image-scout-setting-copy">
							<h3><?php esc_html_e( 'Keep a restore copy', 'itboffins-image-scout' ); ?></h3>
							<p><?php esc_html_e( 'This uses more storage, but lets you put an image back exactly as it was before optimisation. Leave it off if saving disk space is more important.', 'itboffins-image-scout' ); ?></p>
							<p class="itboffins-image-scout-hint">
								<?php
								printf(
									/* translators: %s: backup folder name */
									esc_html__( 'Restore copies are stored in a protected uploads folder named %s.', 'itboffins-image-scout' ),
									esc_html( $settings['backup_dir'] )
								);
								?>
							</p>
						</div>
						<label class="itboffins-image-scout-toggle">
							<input type="checkbox" name="<?php echo esc_attr( ITBOFFINS_IMAGE_SCOUT_OPTION ); ?>[keep_backup]" value="1" <?php checked( $settings['keep_backup'] ); ?> />
							<span><?php esc_html_e( 'Keep originals', 'itboffins-image-scout' ); ?></span>
						</label>
					</div>
				</div>

				<?php submit_button( __( 'Save Image Scout settings', 'itboffins-image-scout' ) ); ?>
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
	 * @param bool   $ok             Condition.
	 * @param string $label          Optional positive label override.
	 * @param string $negative_label Optional negative label override.
	 * @return string
	 */
	private function badge( $ok, $label = '', $negative_label = '' ) {
		if ( $ok ) {
			$text = $label ? $label : __( 'Ready', 'itboffins-image-scout' );
			return '<span class="itboffins-image-scout-badge itboffins-image-scout-badge-yes">' . esc_html( $text ) . '</span>';
		}
		$text = $negative_label ? $negative_label : __( 'Not available', 'itboffins-image-scout' );
		return '<span class="itboffins-image-scout-badge itboffins-image-scout-badge-no">' . esc_html( $text ) . '</span>';
	}
}
