/* Local Image Optimizer — admin JS (vanilla, no jQuery dependency) */
( function () {
	'use strict';

	var i18n = ( window.LIO && window.LIO.i18n ) || {};

	/**
	 * POST to admin-ajax and return a Promise of the parsed JSON.
	 */
	function post( action, data ) {
		var body = new URLSearchParams();
		body.append( 'action', action );
		body.append( 'nonce', window.LIO.nonce );
		Object.keys( data || {} ).forEach( function ( k ) {
			body.append( k, data[ k ] );
		} );

		return fetch( window.LIO.ajaxUrl, {
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

	/* ---------------------------------------------------------------------
	 * Bulk optimizer screen
	 * ------------------------------------------------------------------ */
	function initBulk() {
		var startBtn = document.getElementById( 'lio-bulk-start' );
		if ( ! startBtn ) {
			return;
		}

		var stopBtn = document.getElementById( 'lio-bulk-stop' );
		var progress = document.getElementById( 'lio-progress' );
		var barFill = document.getElementById( 'lio-bar-fill' );
		var progressText = document.getElementById( 'lio-progress-text' );
		var savingsEl = document.getElementById( 'lio-savings' );
		var logEl = document.getElementById( 'lio-log' );

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
				humanBytes( totalSaved ) + ' saved · ' + totalWebp + ' WebP created';
		}

		function finish() {
			startBtn.disabled = false;
			startBtn.style.display = '';
			stopBtn.style.display = 'none';
			log( i18n.done || 'All done!', 'ok' );
		}

		function next() {
			if ( stopped || ! queue.length ) {
				finish();
				return;
			}
			var id = queue.shift();
			post( 'lio_optimize', { id: id } )
				.then( function ( res ) {
					done++;
					if ( res && res.success ) {
						totalSaved += res.data.saved || 0;
						totalWebp += res.data.webp || 0;
						log(
							'#' + res.data.id + ' ✓ ' + res.data.human +
								' (' + res.data.percent + '%)',
							'ok'
						);
					} else {
						var m = res && res.data ? res.data.message : 'error';
						log( '#' + id + ' ✗ ' + m, 'err' );
					}
					update();
					next();
				} )
				.catch( function () {
					done++;
					log( '#' + id + ' ✗ request failed', 'err' );
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
			log( 'Fetching image list…' );

			post( 'lio_get_ids', {} ).then( function ( res ) {
				if ( ! res || ! res.success || ! res.data.total ) {
					log( i18n.noImages || 'No images found.', 'err' );
					finish();
					return;
				}
				queue = res.data.ids;
				total = res.data.total;
				done = 0;
				totalSaved = 0;
				totalWebp = 0;
				update();
				log( 'Found ' + total + ' images.' );
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
			var optBtn = e.target.closest( '.lio-optimize-btn' );
			var resBtn = e.target.closest( '.lio-restore-btn' );

			if ( optBtn ) {
				e.preventDefault();
				handleSingle( optBtn, 'lio_optimize', i18n.optimizing );
			} else if ( resBtn ) {
				e.preventDefault();
				if ( ! window.confirm( i18n.confirmRestore || 'Restore original?' ) ) {
					return;
				}
				handleSingle( resBtn, 'lio_restore', i18n.restoring );
			}
		} );
	}

	function handleSingle( btn, action, busyText ) {
		var cell = btn.closest( '.lio-cell' );
		var msg = cell ? cell.querySelector( '.lio-msg' ) : null;
		var id = btn.getAttribute( 'data-id' );

		btn.disabled = true;
		if ( msg ) {
			msg.className = 'lio-msg';
			msg.textContent = busyText || '…';
		}

		post( action, { id: id } ).then( function ( res ) {
			if ( res && res.success ) {
				if ( msg ) {
					msg.className = 'lio-msg ok';
					if ( action === 'lio_optimize' ) {
						msg.textContent =
							( i18n.optimized || 'Optimized' ) +
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
					msg.className = 'lio-msg err';
					msg.textContent =
						( i18n.failed || 'Failed' ) +
						': ' + ( res && res.data ? res.data.message : '' );
				}
			}
		} ).catch( function () {
			btn.disabled = false;
			if ( msg ) {
				msg.className = 'lio-msg err';
				msg.textContent = i18n.failed || 'Failed';
			}
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		initBulk();
		initMediaButtons();
	} );
} )();
