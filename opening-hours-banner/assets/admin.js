/* Opening Hours by IT Boffins — admin editor (vanilla JS, no jQuery). */
( function () {
	'use strict';

	var i18n = ( window.IBOH && window.IBOH.i18n ) || {};
	var uid = Date.now(); // Unique-enough indices for newly added rows.

	function nextIndex() {
		uid += 1;
		return uid;
	}

	/* ---- Range rows ------------------------------------------------------- */

	function addRange( container ) {
		var tpl = document.getElementById( 'iboh-range-tpl' );
		if ( ! tpl || ! container ) {
			return;
		}
		var node = tpl.content.firstElementChild.cloneNode( true );
		var base = container.getAttribute( 'data-name' );
		var idx = nextIndex();
		node.querySelectorAll( '[data-field]' ).forEach( function ( input ) {
			input.name = base + '[' + idx + '][' + input.getAttribute( 'data-field' ) + ']';
			input.removeAttribute( 'data-field' );
		} );
		container.appendChild( node );
		schedulePreview();
	}

	/* ---- Holiday rows ----------------------------------------------------- */

	function addHoliday() {
		var tpl = document.getElementById( 'iboh-holiday-tpl' );
		var list = document.getElementById( 'iboh-holidays' );
		if ( ! tpl || ! list ) {
			return;
		}
		var node = tpl.content.firstElementChild.cloneNode( true );
		var base = 'iboh_settings[holidays][' + nextIndex() + ']';

		node.querySelectorAll( '[data-field]' ).forEach( function ( input ) {
			input.name = base + '[' + input.getAttribute( 'data-field' ) + ']';
			input.removeAttribute( 'data-field' );
		} );
		var ranges = node.querySelector( '[data-iboh-ranges]' );
		if ( ranges ) {
			ranges.classList.add( 'iboh-ranges' );
			ranges.setAttribute( 'data-name', base + '[ranges]' );
			ranges.removeAttribute( 'data-iboh-ranges' );
		}
		list.appendChild( node );
		schedulePreview();
	}

	/* ---- Copy Monday to the other weekdays ------------------------------- */

	function copyWeekdays() {
		var monday = document.querySelector( '[data-iboh-day][data-dow="1"]' );
		if ( ! monday ) {
			return;
		}
		var mondayState = readDay( monday );
		[ 2, 3, 4, 5 ].forEach( function ( dow ) {
			var row = document.querySelector( '[data-iboh-day][data-dow="' + dow + '"]' );
			if ( ! row ) {
				return;
			}
			var toggle = row.querySelector( '.iboh-closed-toggle' );
			if ( toggle ) {
				toggle.checked = mondayState.closed;
				toggleClosed( toggle );
			}
			var container = row.querySelector( '.iboh-ranges' );
			if ( container ) {
				container.innerHTML = '';
				mondayState.ranges.forEach( function ( r ) {
					addRange( container );
					var rows = container.querySelectorAll( '.iboh-range' );
					var last = rows[ rows.length - 1 ];
					var inputs = last.querySelectorAll( 'input[type="time"]' );
					if ( inputs[ 0 ] ) { inputs[ 0 ].value = r.open; }
					if ( inputs[ 1 ] ) { inputs[ 1 ].value = r.close; }
				} );
			}
		} );
		schedulePreview();
	}

	/* ---- Closed toggle (show/hide the hours body) ------------------------ */

	function toggleClosed( checkbox ) {
		var wrap = checkbox.closest( '[data-iboh-day], [data-iboh-holiday]' );
		if ( ! wrap ) {
			return;
		}
		var body = wrap.querySelector( '.iboh-day-body' );
		if ( body ) {
			body.style.display = checkbox.checked ? 'none' : '';
		}
	}

	/* ---- Reading the form into a config object --------------------------- */

	function rangesFrom( container ) {
		var out = [];
		if ( ! container ) {
			return out;
		}
		container.querySelectorAll( '.iboh-range' ).forEach( function ( row ) {
			var inputs = row.querySelectorAll( 'input[type="time"]' );
			var open = inputs[ 0 ] ? inputs[ 0 ].value : '';
			var close = inputs[ 1 ] ? inputs[ 1 ].value : '';
			if ( open && close ) {
				out.push( { open: open, close: close } );
			}
		} );
		return out;
	}

	function readDay( row ) {
		var toggle = row.querySelector( '.iboh-closed-toggle' );
		var closed = toggle ? toggle.checked : false;
		return {
			closed: closed,
			ranges: closed ? [] : rangesFrom( row.querySelector( '.iboh-ranges' ) )
		};
	}

	function buildConfig() {
		var cfg = {
			tz: window.IBOH ? window.IBOH.tz : '',
			offset: window.IBOH ? window.IBOH.offset : 0,
			timeFormat: window.IBOH ? window.IBOH.timeFormat : 'H:i',
			dayNames: window.IBOH ? window.IBOH.dayNames : [],
			schedule: {},
			holidays: {},
			labels: {},
			banner: {}
		};

		document.querySelectorAll( '[data-iboh-day]' ).forEach( function ( row ) {
			var dow = row.getAttribute( 'data-dow' );
			cfg.schedule[ dow ] = readDay( row );
		} );

		document.querySelectorAll( '[data-iboh-holiday]' ).forEach( function ( row ) {
			var dateInput = row.querySelector( 'input[type="date"]' );
			var date = dateInput ? dateInput.value : '';
			if ( ! date ) {
				return;
			}
			var labelInput = row.querySelector( '.iboh-holiday-label' );
			cfg.holidays[ date ] = {
				closed: readDay( row ).closed,
				ranges: readDay( row ).ranges,
				label: labelInput ? labelInput.value : ''
			};
		} );

		document.querySelectorAll( '[data-iboh-label]' ).forEach( function ( input ) {
			cfg.labels[ input.getAttribute( 'data-iboh-label' ) ] = input.value;
		} );

		document.querySelectorAll( '[data-iboh-banner-field]' ).forEach( function ( input ) {
			var key = input.getAttribute( 'data-iboh-banner-field' );
			cfg.banner[ key ] = ( 'checkbox' === input.type ) ? ( input.checked ? 1 : 0 ) : input.value;
		} );

		return cfg;
	}

	/* ---- Live preview ---------------------------------------------------- */

	var previewTimer = null;

	function schedulePreview() {
		if ( previewTimer ) {
			window.clearTimeout( previewTimer );
		}
		previewTimer = window.setTimeout( renderPreview, 150 );
	}

	function renderPreview() {
		var box = document.getElementById( 'iboh-preview' );
		if ( ! box || ! window.IBOHEval ) {
			return;
		}
		var cfg = buildConfig();
		var s = window.IBOHEval.buildStatus( cfg, window.IBOHEval.siteNow( cfg ) );
		box.classList.toggle( 'iboh-open', s.open );
		box.classList.toggle( 'iboh-closed', ! s.open );
		box.style.background = s.open ? cfg.banner.colour_open : cfg.banner.colour_closed;
		box.style.color = cfg.banner.colour_text;
		var main = box.querySelector( '[data-iboh-main]' );
		var sub = box.querySelector( '[data-iboh-sub]' );
		if ( main ) { main.textContent = s.main; }
		if ( sub ) { sub.textContent = s.sub; }
	}

	/* ---- Wiring ---------------------------------------------------------- */

	function init() {
		document.addEventListener( 'click', function ( e ) {
			var t = e.target;
			if ( t.closest( '.iboh-add-range' ) ) {
				e.preventDefault();
				var body = t.closest( '.iboh-day-body' );
				addRange( body ? body.querySelector( '.iboh-ranges' ) : null );
			} else if ( t.closest( '.iboh-remove-range' ) ) {
				e.preventDefault();
				var range = t.closest( '.iboh-range' );
				if ( range ) { range.remove(); }
				schedulePreview();
			} else if ( t.closest( '.iboh-remove-holiday' ) ) {
				e.preventDefault();
				var holiday = t.closest( '.iboh-holiday' );
				if ( holiday ) { holiday.remove(); }
				schedulePreview();
			} else if ( t.id === 'iboh-add-holiday' ) {
				e.preventDefault();
				addHoliday();
			} else if ( t.id === 'iboh-copy-weekdays' ) {
				e.preventDefault();
				copyWeekdays();
			}
		} );

		document.addEventListener( 'change', function ( e ) {
			if ( e.target.classList.contains( 'iboh-closed-toggle' ) ) {
				toggleClosed( e.target );
			}
			schedulePreview();
		} );

		document.addEventListener( 'input', function () {
			schedulePreview();
		} );

		renderPreview();
		// Keep the "soon"/transition wording live even with no edits.
		window.setInterval( renderPreview, 30000 );
	}

	if ( 'loading' !== document.readyState ) {
		init();
	} else {
		document.addEventListener( 'DOMContentLoaded', init );
	}
} )();
