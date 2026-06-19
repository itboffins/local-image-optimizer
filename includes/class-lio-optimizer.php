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
			'time'         => current_time( 'timestamp' ),
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
		if ( ! file_exists( $backup ) ) {
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
		$smaller  = filesize( $tmp_path ) > 0 && filesize( $tmp_path ) < filesize( $file );

		if ( $smaller ) {
			// copy() preserves the destination's ownership/permissions better
			// than rename() across some hosts.
			copy( $tmp_path, $file );
		}
		wp_delete_file( $tmp_path );

		return $smaller;
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

		// Skip if a fresh WebP already exists.
		if ( file_exists( $webp ) && filemtime( $webp ) >= filemtime( $file ) ) {
			return true;
		}

		$editor = wp_get_image_editor( $file );
		if ( is_wp_error( $editor ) ) {
			return false;
		}
		$editor->set_quality( $webp_quality );

		$saved = $editor->save( $webp, 'image/webp' );
		if ( is_wp_error( $saved ) || empty( $saved['path'] ) || ! file_exists( $saved['path'] ) ) {
			return false;
		}

		// Some editors normalise the filename; make sure it lives where we expect.
		if ( $saved['path'] !== $webp ) {
			@rename( $saved['path'], $webp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}

		// A WebP that is bigger than the original helps nobody — drop it.
		if ( file_exists( $webp ) && filesize( $webp ) >= filesize( $file ) ) {
			wp_delete_file( $webp );
			return false;
		}

		return file_exists( $webp );
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
		if ( ! $dest || file_exists( $dest ) ) {
			return file_exists( (string) $dest );
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
	private function backup_path( $file ) {
		$uploads = wp_get_upload_dir();
		$basedir = trailingslashit( $uploads['basedir'] );

		// Only back up files that live under /uploads.
		if ( 0 !== strpos( $file, $uploads['basedir'] ) ) {
			return false;
		}

		$relative = ltrim( substr( $file, strlen( $basedir ) ), '/\\' );
		return $basedir . LIO_BACKUP_DIR . '/' . $relative;
	}
}
