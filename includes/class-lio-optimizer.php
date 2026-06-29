<?php
/**
 * Core optimization engine.
 *
 * Uses WordPress's own WP_Image_Editor abstraction, which transparently picks
 * Imagick or GD depending on what the host provides. That means we never call
 * exec(), never shell out to cwebp/jpegoptim, and never talk to an external
 * API — so it runs anywhere WordPress runs.
 *
 * @package Local_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Optimizer.
 */
class LIO_Optimizer {

	/**
	 * Re-entrancy guard so regenerating metadata during a restore does not
	 * immediately re-optimize the file we just restored.
	 *
	 * @var bool
	 */
	private static $busy = false;

	/**
	 * Hooked to wp_generate_attachment_metadata. Optimizes brand-new uploads.
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Unmodified metadata (we only touch files on disk).
	 */
	public function maybe_auto_optimize( $metadata, $attachment_id ) {
		if ( self::$busy ) {
			return $metadata;
		}
		if ( ! LIO_Settings::get( 'auto_optimize' ) ) {
			return $metadata;
		}
		if ( ! $this->is_supported_attachment( $attachment_id ) ) {
			return $metadata;
		}

		$this->optimize_attachment( $attachment_id, $metadata );

		return $metadata;
	}

	/**
	 * Optimize every file belonging to one attachment (full size, the original
	 * unscaled copy, and all generated thumbnail sizes).
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Optional pre-fetched metadata.
	 * @return array|WP_Error Stats on success.
	 */
	public function optimize_attachment( $attachment_id, $metadata = null ) {
		$caps = LIO_Capabilities::get();
		if ( ! $caps['can_compress'] ) {
			return new WP_Error( 'lio_no_editor', __( 'No usable image library (GD or Imagick) was found on this server.', 'local-image-optimizer' ) );
		}
		if ( ! $this->is_supported_attachment( $attachment_id ) ) {
			return new WP_Error( 'lio_unsupported', __( 'This attachment is not a supported image type.', 'local-image-optimizer' ) );
		}

		$main_file = get_attached_file( $attachment_id );
		if ( ! $main_file || ! file_exists( $main_file ) ) {
			return new WP_Error( 'lio_missing', __( 'The image file could not be found on disk.', 'local-image-optimizer' ) );
		}

		// Back up the canonical original once, before we ever rewrite it.
		if ( LIO_Settings::get( 'keep_backup' ) ) {
			$this->backup( $main_file );
		}

		$files        = $this->get_attachment_files( $attachment_id, $metadata );
		$bytes_before = 0;
		$bytes_after  = 0;
		$webp_made    = 0;

		$jpeg_quality = (int) LIO_Settings::get( 'jpeg_quality' );
		$webp_enabled = (bool) LIO_Settings::get( 'webp_enabled' ) && $caps['can_webp'];
		$webp_quality = (int) LIO_Settings::get( 'webp_quality' );

		foreach ( $files as $file ) {
			clearstatcache( true, $file );
			$bytes_before += (int) filesize( $file );

			$this->recompress( $file, $jpeg_quality );

			if ( $webp_enabled && $this->make_webp( $file, $webp_quality ) ) {
				$webp_made++;
			}

			clearstatcache( true, $file );
			$bytes_after += (int) filesize( $file );
		}

		$saved = max( 0, $bytes_before - $bytes_after );

		$stats = array(
			'optimized'    => true,
			'bytes_before' => $bytes_before,
			'bytes_after'  => $bytes_after,
			'saved'        => $saved,
			'percent'      => $bytes_before > 0 ? round( ( $saved / $bytes_before ) * 100, 1 ) : 0,
			'webp'         => $webp_made,
			'files'        => count( $files ),
			'time'         => time(),
		);

		update_post_meta( $attachment_id, LIO_META, $stats );

		return $stats;
	}

	/**
	 * Restore an attachment from its backed-up original and regenerate sizes.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return true|WP_Error
	 */
	public function restore_attachment( $attachment_id ) {
		$main_file = get_attached_file( $attachment_id );
		if ( ! $main_file ) {
			return new WP_Error( 'lio_missing', __( 'The image file could not be found.', 'local-image-optimizer' ) );
		}

		$backup = $this->backup_path( $main_file );
		if ( ! $backup || ! file_exists( $backup ) ) {
			$backup = $this->backup_path( $main_file, LIO_BACKUP_DIR );
		}
		if ( ! $backup || ! file_exists( $backup ) ) {
			return new WP_Error( 'lio_no_backup', __( 'No backup of the original is available to restore.', 'local-image-optimizer' ) );
		}

		// Remove WebP siblings for every current file.
		foreach ( $this->get_attachment_files( $attachment_id ) as $file ) {
			if ( file_exists( $file . '.webp' ) ) {
				wp_delete_file( $file . '.webp' );
			}
		}

		// Put the pristine original back.
		copy( $backup, $main_file );

		// Rebuild thumbnails without triggering auto-optimization.
		self::$busy = true;
		$new_meta   = wp_generate_attachment_metadata( $attachment_id, $main_file );
		if ( ! is_wp_error( $new_meta ) && ! empty( $new_meta ) ) {
			wp_update_attachment_metadata( $attachment_id, $new_meta );
		}
		self::$busy = false;

		delete_post_meta( $attachment_id, LIO_META );

		return true;
	}

	/**
	 * Does this attachment have a restorable original backup?
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	public function has_backup( $attachment_id ) {
		$main_file = get_attached_file( $attachment_id );
		if ( ! $main_file ) {
			return false;
		}

		foreach ( array( null, LIO_BACKUP_DIR ) as $dir_name ) {
			$backup = $this->backup_path( $main_file, $dir_name );
			if ( $backup && file_exists( $backup ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Re-encode an image at the configured quality, keeping the result only if
	 * it is actually smaller than what we started with.
	 *
	 * @param string $file         Absolute path.
	 * @param int    $jpeg_quality JPEG quality.
	 * @return bool True if the file was replaced with a smaller version.
	 */
	private function recompress( $file, $jpeg_quality ) {
		$mime = $this->mime_for_path( $file );
		if ( ! $mime ) {
			return false;
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return false;
		}

		// PNG quality in WP_Image_Editor maps to a 0-9 deflate level, so keep
		// PNG lossless (max compression) and only apply lossy quality to JPEG.
		if ( 'image/jpeg' === $mime ) {
			$editor->set_quality( $jpeg_quality );
		}

		$info = pathinfo( $file );
		$ext  = isset( $info['extension'] ) ? '.' . $info['extension'] : '';
		$tmp  = $info['dirname'] . '/' . $info['filename'] . '-lio-tmp' . $ext;

		$saved = $editor->save( $tmp, $mime );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
			return false;
		}

		$tmp_path = $saved['path'];
		$valid    = $this->is_valid_image( $tmp_path, array( IMAGETYPE_JPEG, IMAGETYPE_PNG ) );
		$smaller  = filesize( $tmp_path ) > 0 && filesize( $tmp_path ) < filesize( $file );

		// Never replace a good original with a smaller-but-corrupt re-encode.
		if ( $smaller && $valid ) {
			// copy() preserves the destination's ownership/permissions better
			// than rename() across some hosts.
			copy( $tmp_path, $file );
		}
		wp_delete_file( $tmp_path );

		return $smaller && $valid;
	}

	/**
	 * Create a WebP sibling next to a source image (foo.jpg -> foo.jpg.webp).
	 *
	 * The appended-extension naming is collision-free and makes the front-end
	 * lookup a simple "does <path>.webp exist?" check.
	 *
	 * @param string $file         Absolute path to the source image.
	 * @param int    $webp_quality WebP quality.
	 * @return bool True if a usable WebP now exists.
	 */
	private function make_webp( $file, $webp_quality ) {
		$webp = $file . '.webp';

		// Reuse an existing WebP only if it is fresh AND actually decodes. A
		// stale or corrupt sibling is removed so it gets regenerated below —
		// this is what lets a re-run repair previously-broken WebP files.
		if ( file_exists( $webp ) ) {
			if ( filemtime( $webp ) >= filemtime( $file ) && $this->is_valid_image( $webp, array( IMAGETYPE_WEBP ) ) ) {
				return true;
			}
			wp_delete_file( $webp );
		}

		// First attempt: WordPress's chosen editor (GD or Imagick).
		$editor = wp_get_image_editor( $file );
		if ( ! is_wp_error( $editor ) ) {
			$editor->set_quality( $webp_quality );
			$saved = $editor->save( $webp, 'image/webp' );
			// Some editors normalise the filename; copy it where we expect.
			if ( ! is_wp_error( $saved ) && ! empty( $saved['path'] ) && $saved['path'] !== $webp ) {
				if ( copy( $saved['path'], $webp ) ) {
					wp_delete_file( $saved['path'] );
				}
			}
		}

		// If that produced nothing that decodes, fall back to a direct GD encode
		// that flattens palette (indexed) images to truecolor first. Indexed
		// PNGs — logos especially — are a common source of corrupt WebP from
		// some builds of libgd, which shows as a broken image on the front end.
		if ( ! $this->is_valid_image( $webp, array( IMAGETYPE_WEBP ) ) ) {
			if ( file_exists( $webp ) ) {
				wp_delete_file( $webp );
			}
			$this->gd_encode_webp( $file, $webp, $webp_quality );
		}

		// Only keep a WebP that actually decodes...
		if ( ! $this->is_valid_image( $webp, array( IMAGETYPE_WEBP ) ) ) {
			if ( file_exists( $webp ) ) {
				wp_delete_file( $webp );
			}
			return false;
		}

		// ...and that is genuinely smaller than the original.
		if ( filesize( $webp ) >= filesize( $file ) ) {
			wp_delete_file( $webp );
			return false;
		}

		return true;
	}

	/**
	 * Validate that a file is a decodable raster image of an allowed type.
	 *
	 * @param string $path          Absolute path.
	 * @param int[]  $allowed_types IMAGETYPE_* constants to accept.
	 * @return bool
	 */
	private function is_valid_image( $path, $allowed_types ) {
		if ( ! file_exists( $path ) || filesize( $path ) < 12 ) {
			return false;
		}
		$info = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( ! $info || empty( $info[0] ) || empty( $info[1] ) ) {
			return false;
		}
		$type = isset( $info[2] ) ? $info[2] : 0;
		return in_array( $type, $allowed_types, true );
	}

	/**
	 * Encode WebP directly with GD, flattening indexed/palette images to
	 * truecolor and preserving transparency. Returns false if GD is unavailable
	 * or cannot read the source.
	 *
	 * @param string $file    Source image path.
	 * @param string $webp    Destination .webp path.
	 * @param int    $quality WebP quality (0-100).
	 * @return bool
	 */
	private function gd_encode_webp( $file, $webp, $quality ) {
		if ( ! function_exists( 'imagewebp' ) ) {
			return false;
		}

		$info = @getimagesize( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$type = $info && isset( $info[2] ) ? $info[2] : 0;

		switch ( $type ) {
			case IMAGETYPE_JPEG:
				$img = @imagecreatefromjpeg( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			case IMAGETYPE_PNG:
				$img = @imagecreatefrompng( $file ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				break;
			default:
				return false;
		}

		if ( ! $img ) {
			return false;
		}

		// Flatten palette images: libgd's imagewebp can emit corrupt output from
		// indexed sources, so promote them to truecolor first.
		if ( function_exists( 'imageistruecolor' ) && ! imageistruecolor( $img ) && function_exists( 'imagepalettetotruecolor' ) ) {
			imagepalettetotruecolor( $img );
		}
		imagealphablending( $img, false );
		imagesavealpha( $img, true );

		$ok = imagewebp( $img, $webp, (int) $quality );
		imagedestroy( $img );

		return (bool) $ok;
	}

	/**
	 * Collect every on-disk file for an attachment.
	 *
	 * @param int        $attachment_id Attachment ID.
	 * @param array|null $metadata      Optional metadata.
	 * @return string[] Absolute, existing, de-duplicated paths.
	 */
	private function get_attachment_files( $attachment_id, $metadata = null ) {
		$main = get_attached_file( $attachment_id );
		$dir  = trailingslashit( dirname( $main ) );
		$out  = array( $main );

		$meta = is_array( $metadata ) ? $metadata : wp_get_attachment_metadata( $attachment_id );

		if ( ! empty( $meta['sizes'] ) && is_array( $meta['sizes'] ) ) {
			foreach ( $meta['sizes'] as $size ) {
				if ( ! empty( $size['file'] ) ) {
					$out[] = $dir . $size['file'];
				}
			}
		}

		// The untouched original kept when WordPress scales large uploads.
		if ( ! empty( $meta['original_image'] ) ) {
			$out[] = $dir . $meta['original_image'];
		}

		$out = array_values( array_unique( array_filter( $out, 'file_exists' ) ) );

		return $out;
	}

	/**
	 * Is this attachment an image type we handle? (We skip GIF to preserve
	 * animation, and SVG because it is not a raster format.)
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return bool
	 */
	private function is_supported_attachment( $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return false;
		}
		$mime = get_post_mime_type( $attachment_id );
		return in_array( $mime, array( 'image/jpeg', 'image/png' ), true );
	}

	/**
	 * Map a file path to its MIME type for re-encoding.
	 *
	 * @param string $file Path.
	 * @return string|false
	 */
	private function mime_for_path( $file ) {
		$ext = strtolower( pathinfo( $file, PATHINFO_EXTENSION ) );
		switch ( $ext ) {
			case 'jpg':
			case 'jpeg':
				return 'image/jpeg';
			case 'png':
				return 'image/png';
			default:
				return false;
		}
	}

	/**
	 * Copy a file into the backup folder, mirroring its uploads-relative path.
	 *
	 * @param string $file Absolute path to back up.
	 * @return bool
	 */
	private function backup( $file ) {
		$dest = $this->backup_path( $file );
		if ( ! $dest ) {
			return false;
		}
		if ( file_exists( $dest ) ) {
			return true;
		}
		if ( ! $this->ensure_backup_dir_protection( $this->backup_root() ) ) {
			return false;
		}
		wp_mkdir_p( dirname( $dest ) );
		return copy( $file, $dest );
	}

	/**
	 * Work out where the backup of a given file lives.
	 *
	 * @param string $file Absolute path inside the uploads directory.
	 * @return string|false
	 */
	private function backup_path( $file, $dir_name = null ) {
		$uploads   = wp_get_upload_dir();
		$base_real = realpath( $uploads['basedir'] );
		$file_real = realpath( $file );

		if ( false === $base_real || false === $file_real ) {
			return false;
		}

		$base = trailingslashit( wp_normalize_path( $base_real ) );
		$path = wp_normalize_path( $file_real );
		if ( 0 !== strpos( $path, $base ) ) {
			return false;
		}

		$relative = ltrim( substr( $path, strlen( $base ) ), '/\\' );
		return trailingslashit( $this->backup_root( $dir_name ) ) . $relative;
	}

	/**
	 * Root backup directory for new or legacy backups.
	 *
	 * @param string|null $dir_name Optional directory name.
	 * @return string
	 */
	private function backup_root( $dir_name = null ) {
		$uploads  = wp_get_upload_dir();
		$dir_name = null === $dir_name ? LIO_Settings::backup_dir_name() : sanitize_file_name( $dir_name );
		return trailingslashit( $uploads['basedir'] ) . $dir_name;
	}

	/**
	 * Create deny files before placing originals in the public uploads tree.
	 *
	 * @param string $dir Backup root directory.
	 * @return bool
	 */
	private function ensure_backup_dir_protection( $dir ) {
		wp_mkdir_p( $dir );
		if ( ! is_dir( $dir ) || ! wp_is_writable( $dir ) ) {
			return false;
		}

		$files = array(
			'index.php'  => "<?php\n// Silence is golden.\n",
			'.htaccess'  => "# Local Image Optimizer backup protection\n"
				. "<IfModule mod_authz_core.c>\n"
				. "Require all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "Deny from all\n"
				. "</IfModule>\n",
			'web.config' => "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
				. "<configuration>\n"
				. "  <system.webServer>\n"
				. "    <handlers accessPolicy=\"None\" />\n"
				. "    <security>\n"
				. "      <authorization>\n"
				. "        <remove users=\"*\" roles=\"\" verbs=\"\" />\n"
				. "        <add accessType=\"Deny\" users=\"*\" />\n"
				. "      </authorization>\n"
				. "    </security>\n"
				. "  </system.webServer>\n"
				. "</configuration>\n",
		);

		foreach ( $files as $file => $contents ) {
			if ( ! $this->write_protection_file( trailingslashit( $dir ) . $file, $contents ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Write or refresh a backup-protection file.
	 *
	 * @param string $path     Destination path.
	 * @param string $contents File contents.
	 * @return bool
	 */
	private function write_protection_file( $path, $contents ) {
		return false !== file_put_contents( $path, $contents, LOCK_EX );
	}

	/* ---------------------------------------------------------------------
	 * Path-based entry points used by the whole-uploads folder scanner.
	 * These let us process files that are NOT Media Library attachments
	 * (e.g. page-builder/theme images), reusing the same validated,
	 * palette-safe encoder as attachment optimisation.
	 * ------------------------------------------------------------------ */

	/**
	 * Absolute path to the uploads directory, with a trailing slash.
	 *
	 * @return string
	 */
	public function uploads_basedir() {
		$uploads = wp_get_upload_dir();
		return trailingslashit( $uploads['basedir'] );
	}

	/**
	 * Cheap eligibility classifier for the folder scanner. Returns false when a
	 * file should be processed, or a short string reason when it should be
	 * skipped. Deliberately avoids realpath() so it stays fast inside a walk;
	 * webpify_path() performs the authoritative path-safety check.
	 *
	 * @param string $file Absolute path.
	 * @return false|string
	 */
	public function should_skip_path( $file ) {
		$normalized = wp_normalize_path( $file );

		foreach ( LIO_Settings::backup_dir_names() as $backup_dir ) {
			if ( false !== strpos( $normalized, '/' . $backup_dir . '/' ) ) {
				return 'backup';
			}
		}
		$ext = strtolower( pathinfo( $normalized, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png' ), true ) ) {
			return 'not_image';
		}
		return false;
	}

	/**
	 * Generate a WebP for an arbitrary file path under /uploads. Used by the
	 * folder scanner for files that may not be Media Library attachments.
	 *
	 * @param string   $file         Absolute path.
	 * @param int|null $webp_quality Optional quality override.
	 * @return true|WP_Error
	 */
	public function webpify_path( $file, $webp_quality = null ) {
		$caps = LIO_Capabilities::get();
		if ( ! $caps['can_webp'] || ! LIO_Settings::get( 'webp_enabled' ) ) {
			return new WP_Error( 'lio_no_webp', __( 'WebP generation is unavailable or disabled.', 'local-image-optimizer' ) );
		}

		$real = realpath( $file );
		if ( false === $real ) {
			return new WP_Error( 'lio_missing', __( 'File not found.', 'local-image-optimizer' ) );
		}
		if ( ! $this->is_under_uploads( $real ) ) {
			return new WP_Error( 'lio_outside', __( 'File is outside the uploads directory.', 'local-image-optimizer' ) );
		}
		$skip = $this->should_skip_path( $real );
		if ( false !== $skip ) {
			return new WP_Error( 'lio_skipped', $skip );
		}
		if ( ! $this->is_valid_image( $real, array( IMAGETYPE_JPEG, IMAGETYPE_PNG ) ) ) {
			return new WP_Error( 'lio_unreadable', __( 'File is not a readable JPEG or PNG.', 'local-image-optimizer' ) );
		}

		$quality = ( null === $webp_quality ) ? (int) LIO_Settings::get( 'webp_quality' ) : (int) $webp_quality;

		if ( $this->make_webp( $real, $quality ) ) {
			return true;
		}
		return new WP_Error( 'lio_encode_failed', __( 'No smaller, valid WebP could be created for this file.', 'local-image-optimizer' ) );
	}

	/**
	 * Recompress an arbitrary original file under /uploads (optional scan step).
	 *
	 * @param string   $file         Absolute path.
	 * @param int|null $jpeg_quality Optional quality override.
	 * @return bool True if the original was replaced with a smaller version.
	 */
	public function recompress_path( $file, $jpeg_quality = null ) {
		$real = realpath( $file );
		if ( false === $real || ! $this->is_under_uploads( $real ) ) {
			return false;
		}
		if ( false !== $this->should_skip_path( $real ) ) {
			return false;
		}
		$quality = ( null === $jpeg_quality ) ? (int) LIO_Settings::get( 'jpeg_quality' ) : (int) $jpeg_quality;
		return $this->recompress( $real, $quality );
	}

	/**
	 * Does a fresh, valid WebP already exist for this file? Mirrors the reuse
	 * condition inside make_webp(), so the scanner can report "already up to
	 * date" rather than counting it as a new conversion.
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	public function has_fresh_webp( $file ) {
		$webp = $file . '.webp';
		return file_exists( $webp )
			&& file_exists( $file )
			&& filemtime( $webp ) >= filemtime( $file )
			&& $this->is_valid_image( $webp, array( IMAGETYPE_WEBP ) );
	}

	/**
	 * Is an absolute path genuinely inside the uploads directory?
	 * realpath-based, so it also defeats symlink escapes and traversal.
	 *
	 * @param string $file Absolute path (ideally already realpath()'d).
	 * @return bool
	 */
	private function is_under_uploads( $file ) {
		$base = realpath( $this->uploads_basedir() );
		if ( false === $base ) {
			return false;
		}
		$base = trailingslashit( wp_normalize_path( $base ) );
		$path = wp_normalize_path( $file );
		return 0 === strpos( $path, $base );
	}
}
