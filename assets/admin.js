/* ITBoffins Image Scout — admin JS (vanilla, no jQuery dependency) */
( function () {
	'use strict';

	var i18n = ( window.ITBoffinsImageScout && window.ITBoffinsImageScout.i18n ) || {};

	/**
	 * POST to admin-ajax and return a Promise of the parsed JSON.
	 */
	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', window.ITBoffinsImageScout.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );

		return fetch( window.ITBoffinsImageScout.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} ).then( function ( r ) {
			return r.json();
		} );
	}

	function humanBytes( bytes ) {
		if ( ! bytes ) {
			return '0 B';
		}
		var units = [ 'B', 'KB', 'MB', 'GB' ];
		var i = Math.floor( Math.log( bytes ) / Math.log( 1024 ) );
		return ( bytes / Math.pow( 1024, i ) ).toFixed( 1 ) + ' ' + units[ i ];
	}

	function sprintf1( template, value ) {
		return String( template ).replace( '%d', value ).replace( '%s', value );
	}

	/* ---------------------------------------------------------------------
	 * Bulk optimiser screen
	 * ------------------------------------------------------------------ */
	function initBulk() {
		var startBtn = document.getElementById( 'itboffins-image-scout-bulk-start' );
		if ( ! startBtn ) {
			return;
		}

		var stopBtn = document.getElementById( 'itboffins-image-scout-bulk-stop' );
		var progress = document.getElementById( 'itboffins-image-scout-progress' );
		var barFill = document.getElementById( 'itboffins-image-scout-bar-fill' );
		var currentEl = document.getElementById( 'itboffins-image-scout-current' );
		var progressText = document.getElementById( 'itboffins-image-scout-progress-text' );
		var savingsEl = document.getElementById( 'itboffins-image-scout-savings' );
		var logEl = document.getElementById( 'itboffins-image-scout-log' );

		var queue = [];
		var total = 0;
		var done = 0;
		var totalSaved = 0;
		var totalWebp = 0;
		var stopped = false;

		function log( msg, cls ) {
			var line = document.createElement( 'div' );
			if ( cls ) {
				line.className = cls;
			}
			line.textContent = msg;
			logEl.appendChild( line );
			logEl.scrollTop = logEl.scrollHeight;
		}

		function update() {
			var pct = total ? Math.round( ( done / total ) * 100 ) : 0;
			barFill.style.width = pct + '%';
			progressText.textContent = done + ' / ' + total + ' (' + pct + '%)';
			savingsEl.textContent =
				humanBytes( totalSaved ) + ' ' + ( i18n.saved || 'saved' ) +
				' · ' + totalWebp + ' ' + ( i18n.webpCreated || 'WebP created' );
		}

		function finish() {
			startBtn.disabled = false;
			startBtn.style.display = '';
			stopBtn.style.display = 'none';
			currentEl.textContent = '';
			log( i18n.done || 'All done!', 'ok' );
		}

		function next() {
			if ( stopped || ! queue.length ) {
				finish();
				return;
			}
			var item = queue.shift();
			var name = item.name || ( '#' + item.id );

			// Show the filename currently being optimised.
			currentEl.textContent = ( i18n.optimising || 'Optimising…' ) + ' ' + name;

			post( 'itboffins_image_scout_optimize', { id: item.id } )
				.then( function ( res ) {
					done++;
					if ( res && res.success ) {
						totalSaved += res.data.saved || 0;
						totalWebp += res.data.webp || 0;
						log(
							( res.data.name || name ) + ' ✓ ' + res.data.human +
								' (' + res.data.percent + '%)',
							'ok'
						);
					} else {
						var m = res && res.data ? res.data.message : 'error';
						log( name + ' ✗ ' + m, 'err' );
					}
					update();
					next();
				} )
				.catch( function () {
					done++;
					log( name + ' ✗ request failed', 'err' );
					update();
					next();
				} );
		}

		startBtn.addEventListener( 'click', function () {
			startBtn.disabled = true;
			stopped = false;
			logEl.style.display = 'block';
			progress.style.display = 'block';
			stopBtn.style.display = '';
			currentEl.textContent = i18n.fetching || 'Fetching image list…';

			post( 'itboffins_image_scout_get_ids', {} ).then( function ( res ) {
				if ( ! res || ! res.success || ! res.data.total ) {
					currentEl.textContent = '';
					log( i18n.noImages || 'No images found.', 'err' );
					finish();
					return;
				}
				queue = res.data.items;
				total = res.data.total;
				done = 0;
				totalSaved = 0;
				totalWebp = 0;
				update();
				log( sprintf1( i18n.found || 'Found %d images.', total ) );
				next();
			} );
		} );

		stopBtn.addEventListener( 'click', function () {
			stopped = true;
			stopBtn.style.display = 'none';
		} );
	}

	/* ---------------------------------------------------------------------
	 * Media library single-item buttons
	 * ------------------------------------------------------------------ */
	function initMediaButtons() {
		document.addEventListener( 'click', function ( e ) {
			var optBtn = e.target.closest( '.itboffins-image-scout-optimize-btn' );
			var resBtn = e.target.closest( '.itboffins-image-scout-restore-btn' );

			if ( optBtn ) {
				e.preventDefault();
				handleSingle( optBtn, 'itboffins_image_scout_optimize', i18n.optimising );
			} else if ( resBtn ) {
				e.preventDefault();
				if ( ! window.confirm( i18n.confirmRestore || 'Restore original?' ) ) {
					return;
				}
				handleSingle( resBtn, 'itboffins_image_scout_restore', i18n.restoring );
			}
		} );
	}

	function handleSingle( btn, action, busyText ) {
		var cell = btn.closest( '.itboffins-image-scout-cell' );
		var msg = cell ? cell.querySelector( '.itboffins-image-scout-msg' ) : null;
		var id = btn.getAttribute( 'data-id' );

		btn.disabled = true;
		if ( msg ) {
			msg.className = 'itboffins-image-scout-msg';
			msg.textContent = busyText || '…';
		}

		post( action, { id: id } ).then( function ( res ) {
			if ( res && res.success ) {
				if ( msg ) {
					msg.className = 'itboffins-image-scout-msg ok';
					if ( action === 'itboffins_image_scout_optimize' ) {
						msg.textContent =
							( i18n.optimised || 'Optimised' ) +
							' · ' + res.data.human + ' (' + res.data.percent + '%)';
					} else {
						msg.textContent = i18n.restored || 'Restored';
					}
				}
				// Reload after a beat so the column reflects new state.
				setTimeout( function () {
					window.location.reload();
				}, 900 );
			} else {
				btn.disabled = false;
				if ( msg ) {
					msg.className = 'itboffins-image-scout-msg err';
					msg.textContent =
						( i18n.failed || 'Failed' ) +
						': ' + ( res && res.data ? res.data.message : '' );
				}
			}
		} ).catch( function () {
			btn.disabled = false;
			if ( msg ) {
				msg.className = 'itboffins-image-scout-msg err';
				msg.textContent = i18n.failed || 'Failed';
			}
		} );
	}

	/* ---------------------------------------------------------------------
	 * Uploads-folder scan (whole /uploads, including non-library files)
	 * ------------------------------------------------------------------ */
	function initScan() {
		var startBtn = document.getElementById( 'itboffins-image-scout-scan-start' );
		if ( ! startBtn ) {
			return;
		}

		var stopBtn = document.getElementById( 'itboffins-image-scout-scan-stop' );
		var progress = document.getElementById( 'itboffins-image-scout-scan-progress' );
		var barFill = document.getElementById( 'itboffins-image-scout-scan-bar-fill' );
		var currentEl = document.getElementById( 'itboffins-image-scout-scan-current' );
		var textEl = document.getElementById( 'itboffins-image-scout-scan-text' );
		var tallyEl = document.getElementById( 'itboffins-image-scout-scan-savings' );
		var logEl = document.getElementById( 'itboffins-image-scout-scan-log' );
		var recompressEl = document.getElementById( 'itboffins-image-scout-scan-recompress' );

		var cursor = '';
		var total = null;
		var processed = 0;
		var made = 0;
		var skipped = 0;
		var recompressed = 0;
		var stopped = false;
		var fails = 0;
		var stalls = 0;

		function log( msg, cls ) {
			var line = document.createElement( 'div' );
			if ( cls ) {
				line.className = cls;
			}
			line.textContent = msg;
			logEl.appendChild( line );
			logEl.scrollTop = logEl.scrollHeight;
		}

		function update() {
			if ( total ) {
				var pct = Math.min( 100, Math.round( ( processed / total ) * 100 ) );
				barFill.style.width = pct + '%';
				barFill.classList.remove( 'itboffins-image-scout-indeterminate' );
				textEl.textContent = processed + ' / ' + total + ' (' + pct + '%)';
			} else {
				barFill.style.width = '100%';
				barFill.classList.add( 'itboffins-image-scout-indeterminate' );
				textEl.textContent = processed + ' ' + ( i18n.scanned || 'scanned' );
			}
			var t = made + ' ' + ( i18n.createdWord || 'WebP created' ) +
				' · ' + skipped + ' ' + ( i18n.skippedWord || 'skipped' );
			if ( recompressEl && recompressEl.checked ) {
				t += ' · ' + recompressed + ' ' + ( i18n.recompressedWord || 'recompressed' );
			}
			tallyEl.textContent = t;
		}

		function finish() {
			startBtn.disabled = false;
			stopBtn.style.display = 'none';
			currentEl.textContent = '';
			barFill.classList.remove( 'itboffins-image-scout-indeterminate' );
			log( i18n.scanDone || 'Scan complete.', 'ok' );
		}

		function batch() {
			if ( stopped ) {
				finish();
				return;
			}
			var startCursor = cursor;
			post( 'itboffins_image_scout_scan_batch', {
				cursor: cursor,
				batch: 40,
				recompress: ( recompressEl && recompressEl.checked ) ? 1 : 0
			} ).then( function ( res ) {
				if ( ! res || ! res.success ) {
					// A bad file is recorded server-side and skipped on retry.
					fails++;
					if ( fails > 4 ) {
						log( i18n.scanFailed || 'Scan stopped after repeated errors.', 'err' );
						finish();
						return;
					}
					batch();
					return;
				}
				fails = 0;
				var d = res.data;
				cursor = d.cursor;
				processed += d.processed;
				made += d.made;
				skipped += d.skipped;
				recompressed += d.recompressed || 0;

				( d.items || [] ).forEach( function ( it ) {
					if ( it.made ) {
						log( it.name + ' ✓', 'ok' );
					} else {
						log( it.name + ' — ' + ( it.reason || 'skipped' ) );
					}
				} );

				currentEl.textContent = cursor ? ( ( i18n.scanning || 'Scanning…' ) + ' ' + cursor ) : '';
				update();

				// No forward progress (cursor didn't move) — on a very large tree
				// the resume re-walk alone can exhaust the budget. Stop gracefully
				// instead of looping forever; WebP already created are kept.
				if ( ! d.done && d.cursor === startCursor ) {
					stalls++;
					if ( stalls >= 2 ) {
						log( i18n.scanFailed || 'Scan stopped after repeated errors.', 'err' );
						finish();
						return;
					}
				} else {
					stalls = 0;
				}

				if ( d.done ) {
					finish();
				} else {
					batch();
				}
			} ).catch( function () {
				fails++;
				if ( fails > 4 ) {
					log( i18n.scanFailed || 'Scan stopped after repeated errors.', 'err' );
					finish();
					return;
				}
				batch();
			} );
		}

		startBtn.addEventListener( 'click', function () {
			startBtn.disabled = true;
			stopped = false;
			cursor = '';
			processed = 0;
			made = 0;
			skipped = 0;
			recompressed = 0;
			fails = 0;
			stalls = 0;
			progress.style.display = 'block';
			logEl.style.display = 'block';
			stopBtn.style.display = '';
			currentEl.textContent = i18n.scanning || 'Scanning…';

			post( 'itboffins_image_scout_scan_count', {} ).then( function ( res ) {
				total = ( res && res.success && res.data.total ) ? res.data.total : null;
				update();
				batch();
			} );
		} );

		stopBtn.addEventListener( 'click', function () {
			stopped = true;
			stopBtn.style.display = 'none';
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initBulk();
		initScan();
		initMediaButtons();
	} );
} )();
