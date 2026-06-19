<?php
/**
 * Whole-uploads-folder WebP scanner.
 *
 * Walks /uploads on disk (not via the Media Library) so it also creates WebP for
 * page-builder and theme images that are not WordPress attachments. The walk is
 * deterministic (sorted), memory-safe (a PHP generator — one directory in memory
 * at a time), resumable (a cursor = relative path of the last processed file),
 * and bounded per request (a file limit and a wall-clock budget). Encoding reuses
 * LIO_Optimizer's validated, palette-safe pipeline.
 *
 * @package Local_Image_Optimizer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Folder scanner.
 */
class LIO_Scanner {

	/**
	 * Transient key recording the file currently being encoded, so a file that
	 * hard-crashes a worker can be skipped when the batch is retried.
	 */
	const POISON = 'lio_scan_poison';

	/**
	 * Optimizer.
	 *
	 * @var LIO_Optimizer
	 */
	private $optimizer;

	/**
	 * Raw uploads basedir, no trailing slash (for scandir).
	 *
	 * @var string
	 */
	private $base_raw;

	/**
	 * Normalised uploads basedir, trailing slash (for relative paths).
	 *
	 * @var string
	 */
	private $base;

	/**
	 * Constructor.
	 *
	 * @param LIO_Optimizer $optimizer Optimizer.
	 */
	public function __construct( LIO_Optimizer $optimizer ) {
		$this->optimizer = $optimizer;
		$this->base_raw  = untrailingslashit( $optimizer->uploads_basedir() );
		$this->base      = trailingslashit( wp_normalize_path( $this->base_raw ) );
	}

	/**
	 * Count eligible (jpg/jpeg/png, non-backup) files, capped.
	 *
	 * @param int $cap Stop counting at this many.
	 * @return array { total:int, capped:bool }
	 */
	public function count_eligible( $cap = 50000 ) {
		$n      = 0;
		$capped = false;
		foreach ( $this->walk( $this->base_raw ) as $entry ) {
			if ( false === $this->optimizer->should_skip_path( $entry[0] ) ) {
				$n++;
				if ( $n >= $cap ) {
					$capped = true;
					break;
				}
			}
		}
		return array(
			'total'  => $n,
			'capped' => $capped,
		);
	}

	/**
	 * Process one batch of files after $cursor.
	 *
	 * @param string $cursor         Relative path of the last processed file ('' to start).
	 * @param int    $limit          Max eligible files to process this batch.
	 * @param float  $budget_seconds Wall-clock budget for this batch.
	 * @param bool   $recompress     Also recompress originals.
	 * @return array
	 */
	public function run_batch( $cursor, $limit, $budget_seconds, $recompress ) {
		$cursor   = (string) $cursor;
		$limit    = max( 1, min( 100, (int) $limit ) );
		$deadline = microtime( true ) + max( 3.0, (float) $budget_seconds );
		$poison   = get_transient( self::POISON );

		$made         = 0;
		$recompressed = 0;
		$skipped      = 0;
		$processed    = 0;
		$items        = array();
		$new_cursor   = $cursor;
		$done         = true;

		foreach ( $this->walk( $this->base_raw ) as $entry ) {
			list( $abs, $rel ) = $entry;

			// Resume: skip everything up to and including the cursor. Uses
			// per-segment comparison so it matches the directory-by-directory
			// walk order exactly — a flat strcmp would disagree at dir/file
			// boundaries (e.g. "gallery/x.png" vs "gallery-1.png") and could
			// silently skip eligible files.
			if ( '' !== $cursor && $this->path_cmp( $rel, $cursor ) <= 0 ) {
				continue;
			}

			// Wall-clock guard (also covers long runs of skipped files).
			if ( microtime( true ) >= $deadline ) {
				$done = false;
				break;
			}

			// Cheap eligibility (the walk already pruned backups/symlinks).
			if ( false !== $this->optimizer->should_skip_path( $abs ) ) {
				$new_cursor = $rel;
				continue;
			}

			// A file that previously crashed a worker: skip it once, then clear.
			if ( $poison && $rel === $poison ) {
				delete_transient( self::POISON );
				$poison       = false;
				$processed++;
				$skipped++;
				$items[]      = array(
					'name'   => $this->short( $rel ),
					'made'   => false,
					'reason' => 'error',
				);
				$new_cursor = $rel;
				if ( $processed >= $limit ) {
					$done = false;
					break;
				}
				continue;
			}

			$processed++;
			set_transient( self::POISON, $rel, 600 );

			try {
				// Recompress the original FIRST (matches optimize_attachment), so
				// the WebP is encoded from — and stays newer than — the final
				// original and is not needlessly regenerated on the next scan.
				if ( $recompress && $this->optimizer->recompress_path( $abs ) ) {
					$recompressed++;
				}

				if ( $this->optimizer->has_fresh_webp( $abs ) ) {
					// Already up to date: count as skipped, not "created".
					$skipped++;
					$items[] = array(
						'name'   => $this->short( $rel ),
						'made'   => false,
						'reason' => 'fresh',
					);
				} else {
					$result = $this->optimizer->webpify_path( $abs );
					if ( is_wp_error( $result ) ) {
						$skipped++;
						$reason  = ( 'lio_encode_failed' === $result->get_error_code() ) ? 'no_gain' : 'skipped';
						$items[] = array(
							'name'   => $this->short( $rel ),
							'made'   => false,
							'reason' => $reason,
						);
					} else {
						$made++;
						$items[] = array(
							'name' => $this->short( $rel ),
							'made' => true,
						);
					}
				}
			} catch ( \Throwable $e ) {
				$skipped++;
				$items[] = array(
					'name'   => $this->short( $rel ),
					'made'   => false,
					'reason' => 'error',
				);
			}

			delete_transient( self::POISON );
			$new_cursor = $rel;

			// Stop on the file limit, or once the wall-clock budget is spent
			// (re-checked here so a slow encode ends the batch promptly).
			if ( $processed >= $limit || microtime( true ) >= $deadline ) {
				$done = false;
				break;
			}
		}

		// Keep the log payload small.
		if ( count( $items ) > 60 ) {
			$items = array_slice( $items, -60 );
		}

		return array(
			'cursor'       => $new_cursor,
			'done'         => $done,
			'processed'    => $processed,
			'made'         => $made,
			'recompressed' => $recompressed,
			'skipped'      => $skipped,
			'items'        => $items,
		);
	}

	/**
	 * Deterministic, memory-safe recursive walk yielding [ absolute, relative ].
	 *
	 * @param string $dir Absolute directory to walk.
	 * @return Generator
	 */
	private function walk( $dir ) {
		$entries = @scandir( $dir ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		if ( false === $entries ) {
			return;
		}
		sort( $entries, SORT_STRING );

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;

			// Never follow symlinks (prevents loops and tree escapes).
			if ( is_link( $path ) ) {
				continue;
			}

			if ( is_dir( $path ) ) {
				$norm = trailingslashit( wp_normalize_path( $path ) );

				// Prune the backup subtree entirely.
				if ( false !== strpos( $norm, '/' . LIO_BACKUP_DIR . '/' ) ) {
					continue;
				}
				// Stay strictly within the uploads tree.
				$real = realpath( $path );
				if ( false === $real || 0 !== strpos( trailingslashit( wp_normalize_path( $real ) ), $this->base ) ) {
					continue;
				}

				foreach ( $this->walk( $path ) as $sub ) {
					yield $sub;
				}
			} elseif ( is_file( $path ) ) {
				$rel = ltrim( substr( wp_normalize_path( $path ), strlen( $this->base ) ), '/' );
				yield array( $path, $rel );
			}
		}
	}

	/**
	 * Compare two relative paths segment by segment, matching the order the
	 * sorted depth-first walk yields them (per-directory SORT_STRING). A flat
	 * strcmp would disagree because '/' (0x2F) sorts after '-'/'.' etc.
	 *
	 * @param string $a Relative path.
	 * @param string $b Relative path.
	 * @return int <0, 0, or >0.
	 */
	private function path_cmp( $a, $b ) {
		$as = explode( '/', $a );
		$bs = explode( '/', $b );
		$n  = min( count( $as ), count( $bs ) );
		for ( $i = 0; $i < $n; $i++ ) {
			$c = strcmp( $as[ $i ], $bs[ $i ] );
			if ( 0 !== $c ) {
				return $c;
			}
		}
		return count( $as ) - count( $bs );
	}

	/**
	 * Shorten a relative path for the on-screen log.
	 *
	 * @param string $rel Relative path.
	 * @return string
	 */
	private function short( $rel ) {
		return ( strlen( $rel ) > 60 ) ? '…' . substr( $rel, -57 ) : $rel;
	}
}
