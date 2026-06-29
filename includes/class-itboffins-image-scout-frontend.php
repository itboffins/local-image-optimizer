<?php
/**
 * Front-end WebP delivery.
 *
 * Rather than rely on .htaccess rewrites (Apache-only) we wrap each <img> in a
 * <picture> element with a WebP <source>. That is pure HTML, so it works on
 * Apache, Nginx, LiteSpeed, IIS — any server — and browsers that do not support
 * WebP simply fall back to the original <img>.
 *
 * Hooks the usual content/thumbnail filters. The uploads-folder scout handles
 * images that are not Media Library attachments, without rewriting the entire
 * front-end response.
 *
 * @package ITBoffins_Image_Scout
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites image markup on output.
 */
class ITBOFFINS_IMAGE_SCOUT_Frontend {

	/**
	 * Uploads base URL, e.g. https://www.example.com/wp-content/uploads.
	 *
	 * @var string
	 */
	private $base_url = '';

	/**
	 * Uploads base directory (with trailing slash).
	 *
	 * @var string
	 */
	private $base_dir = '';

	/**
	 * Path component of the uploads URL, e.g. /wp-content/uploads.
	 *
	 * Matching on the path (instead of the full URL) makes delivery tolerant of
	 * www vs non-www and http vs https mismatches — a common reason WebP files
	 * that exist on disk were not being served.
	 *
	 * @var string
	 */
	private $base_path = '';


	/**
	 * Register output filters.
	 */
	public function init() {
		if ( is_admin() || ! ITBOFFINS_IMAGE_SCOUT_Settings::get( 'serve_webp' ) ) {
			return;
		}

		$uploads        = wp_get_upload_dir();
		$this->base_url = untrailingslashit( $uploads['baseurl'] );
		$this->base_dir = trailingslashit( $uploads['basedir'] );
		$this->base_path = (string) wp_parse_url( $this->base_url, PHP_URL_PATH );

		if ( '' === $this->base_path ) {
			return;
		}

		add_filter( 'the_content', array( $this, 'filter_html' ), 20 );
		add_filter( 'post_thumbnail_html', array( $this, 'filter_html' ), 20 );
		add_filter( 'wp_get_attachment_image', array( $this, 'filter_html' ), 20 );
		add_filter( 'widget_text', array( $this, 'filter_html' ), 20 );
	}

	/**
	 * Wrap eligible <img> tags in <picture> with a WebP source.
	 *
	 * @param string $html Markup.
	 * @return string
	 */
	public function filter_html( $html ) {
		if ( ! is_string( $html ) || '' === $html || is_feed() || false === stripos( $html, '<img' ) ) {
			return $html;
		}
		return preg_replace_callback( '/<img\b[^>]*>/i', array( $this, 'replace_img' ), $html );
	}

	/**
	 * Callback for a single <img> match.
	 *
	 * @param array $match Regex match.
	 * @return string
	 */
	private function replace_img( $match ) {
		$img = $match[0];

		// Allow opting an image out with a data attribute or class.
		if ( false !== strpos( $img, 'data-no-webp' ) || false !== strpos( $img, 'no-webp' ) ) {
			return $img;
		}

		// Build the WebP srcset from whichever source attributes exist.
		$webp_srcset = '';
		if ( preg_match( '/\ssrcset=("|\')(.*?)\1/i', $img, $m ) ) {
			$webp_srcset = $this->webp_srcset( $m[2] );
		}
		if ( '' === $webp_srcset && preg_match( '/\ssrc=("|\')(.*?)\1/i', $img, $m ) ) {
			$single = $this->webp_url( $m[2] );
			if ( $single ) {
				$webp_srcset = $single;
			}
		}

		if ( '' === $webp_srcset ) {
			return $img;
		}

		$source = '<source srcset="' . esc_attr( $webp_srcset ) . '" type="image/webp"';
		if ( preg_match( '/\ssizes=("|\')(.*?)\1/i', $img, $m ) ) {
			$source .= ' sizes="' . esc_attr( $m[2] ) . '"';
		}
		$source .= ' />';

		return '<picture>' . $source . $img . '</picture>';
	}

	/**
	 * Convert a srcset string to its WebP equivalent, keeping only entries
	 * whose .webp file actually exists on disk.
	 *
	 * @param string $srcset Original srcset value.
	 * @return string
	 */
	private function webp_srcset( $srcset ) {
		$out = array();
		foreach ( explode( ',', $srcset ) as $part ) {
			$part = trim( $part );
			if ( '' === $part ) {
				continue;
			}
			$bits       = preg_split( '/\s+/', $part, 2 );
			$url        = $bits[0];
			$descriptor = isset( $bits[1] ) ? ' ' . $bits[1] : '';
			$webp       = $this->webp_url( $url );
			if ( $webp ) {
				$out[] = $webp . $descriptor;
			}
		}
		return implode( ', ', $out );
	}

	/**
	 * Return a canonical WebP URL for an image URL if the .webp exists, else ''.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	private function webp_url( $url ) {
		$relative = $this->relative_uploads_path( $url );
		if ( null === $relative ) {
			return '';
		}
		if ( file_exists( $this->base_dir . $relative . '.webp' ) ) {
			// Rebuild from the canonical uploads URL so www/scheme always match.
			return $this->base_url . '/' . $relative . '.webp';
		}
		return '';
	}

	/**
	 * Map an image URL to its path relative to the uploads dir, or null if the
	 * URL is not a local upload. Matches on the path only, so www/non-www and
	 * http/https variants all resolve correctly.
	 *
	 * @param string $url Image URL.
	 * @return string|null
	 */
	private function relative_uploads_path( $url ) {
		$url  = preg_replace( '/[?#].*$/', '', $url );
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return null;
		}

		$pos = strpos( $path, $this->base_path );
		if ( false === $pos ) {
			return null;
		}

		$relative = ltrim( substr( $path, $pos + strlen( $this->base_path ) ), '/' );
		if ( '' === $relative ) {
			return null;
		}

		// Decode for the filesystem check; WordPress stores ASCII-safe names.
		return rawurldecode( $relative );
	}
}
