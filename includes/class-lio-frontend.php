<?php
/**
 * Front-end WebP delivery.
 *
 * Rather than rely on .htaccess rewrites (Apache-only) we wrap each <img> in a
 * <picture> element with a WebP <source>. That is pure HTML, so it works on
 * Apache, Nginx, LiteSpeed, IIS — any server — and browsers that do not support
 * WebP simply fall back to the original <img>.
 *
 * @package Local_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rewrites image markup on output.
 */
class LIO_Frontend {

	/**
	 * Uploads base URL (scheme-normalised).
	 *
	 * @var string
	 */
	private $base_url = '';

	/**
	 * Uploads base directory.
	 *
	 * @var string
	 */
	private $base_dir = '';

	/**
	 * Register output filters.
	 */
	public function init() {
		if ( is_admin() || ! LIO_Settings::get( 'serve_webp' ) ) {
			return;
		}

		$uploads        = wp_get_upload_dir();
		$this->base_url = set_url_scheme( $uploads['baseurl'] );
		$this->base_dir = trailingslashit( $uploads['basedir'] );

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
		if ( '' === $html || is_feed() || false === strpos( $html, '<img' ) ) {
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
	 * Return the WebP URL for an image URL if the file exists, else ''.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	private function webp_url( $url ) {
		$path = $this->url_to_path( $url );
		if ( '' !== $path && file_exists( $path . '.webp' ) ) {
			return $url . '.webp';
		}
		return '';
	}

	/**
	 * Map an uploads URL to an absolute path, or '' if it is not local.
	 *
	 * @param string $url Image URL.
	 * @return string
	 */
	private function url_to_path( $url ) {
		$url = preg_replace( '/[?#].*$/', '', $url );
		$url = set_url_scheme( $url );

		if ( 0 !== strpos( $url, $this->base_url ) ) {
			return '';
		}
		$relative = ltrim( substr( $url, strlen( $this->base_url ) ), '/' );
		return $this->base_dir . $relative;
	}
}
