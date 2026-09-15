/**
 * Well Web Notify — admin behaviour.
 *
 * Four jobs: keep the section index in step with the scroll, preview the phone
 * format, send a test message, and copy the verification command. No jQuery.
 *
 * DELETED, and worth saying why so it does not come back: this file used to
 * rewrite #adminmenu on load to force-highlight a shared "Well Web" parent
 * item. WordPress decides sidebar highlighting server-side, through the
 * `parent_file` and `submenu_file` filters, and a script rewriting the markup
 * afterwards silently overrode that — not only here but in Well Web SEO, which
 * relies on exactly those filters. It also marked a tab "current" by substring
 * matching on the URL, so on the Log screen it highlighted Channels.
 */
( function () {
	'use strict';

	var cfg  = window.wellwebNotify || {};
	var i18n = cfg.i18n || {};

	// ─── Channel rows expand and collapse ───────────────────────────────────
	//
	// The row used to be a <details>, and the enable switch inside its <summary>
	// did not work: a label-wrapped checkbox inside a summary is inert in
	// Chrome — the summary's activation behaviour claims the click, the label
	// never forwards it to the control, and the details does not even open.
	// See the note at the top of includes/section-channels.php.
	//
	// A previous attempt to rescue it called preventDefault() on clicks landing
	// on a control inside the summary. That cannot work either: preventDefault
	// on a click whose target is a checkbox is precisely what cancels the
	// checkbox. It made a broken switch look like a deliberate one.
	//
	// So the row is an ordinary element, the name is a real disclosure button,
	// and the panel is shown and hidden here.
	var rows = Array.prototype.slice.call( document.querySelectorAll( '.wellweb-notify-channel' ) );

	function setOpen( row, open ) {
		row.classList.toggle( 'is-open', open );
		var button = row.querySelector( '.wellweb-notify-channel__disclosure' );
		if ( button ) {
			button.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		}
	}

	// The markup ships open so that a script that fails to load still leaves the
	// credentials reachable. Collapse the ones that were not meant to be open.
	rows.forEach( function ( row ) {
		setOpen( row, row.classList.contains( 'is-open' ) );
	} );

	document.addEventListener( 'click', function ( e ) {
		var row = e.target.closest( '.wellweb-notify-channel' );
		if ( ! row || e.target.closest( '.wellweb-notify-channel__body' ) ) {
			return;
		}

		// Anywhere on the row expands it, EXCEPT the controls — and "except"
		// here means "ignore the event", never "cancel it". The switch and the
		// Send test button have to keep their own default behaviour.
		if ( e.target.closest( 'input, label, a' ) || e.target.closest( '.wellweb-notify-test' ) ) {
			return;
		}

		setOpen( row, ! row.classList.contains( 'is-open' ) );
	} );

	// Switching a channel on opens it, because the next thing you need is the
	// field you have not filled in yet.
	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target.matches( '.wellweb-notify-channel .wpds-toggle input' ) ) {
			return;
		}
		if ( e.target.checked ) {
			setOpen( e.target.closest( '.wellweb-notify-channel' ), true );
		}
	} );

	// ─── The section index follows the scroll ───────────────────────────────
	//
	// The index is the only thing on the page saying where you are in it, so it
	// has to keep up with the scroll.
	//
	// A fixed LINE, not an IntersectionObserver band. The band version was
	// written first and is wrong in a way that only shows on a real page: with
	// a band, two sections can be inside it at once, so the rule has to be
	// "first" or "last" — and "first" lags a section behind while "last" runs a
	// section ahead of a short one. Jumping to #message highlighted Activity,
	// because Message is four lines tall and Activity's top was in the band too.
	//
	// "The last section whose top has passed 100px" has one answer at every
	// scroll position, whatever the section heights are.
	var links = Array.prototype.slice.call( document.querySelectorAll( '.wpds-subnav a' ) );

	if ( links.length ) {
		var ids = links.map( function ( a ) {
			return a.getAttribute( 'href' ).slice( 1 );
		} );

		var markSection = function () {
			var current = ids[ 0 ];

			ids.forEach( function ( id ) {
				var el = document.getElementById( id );
				if ( el && el.getBoundingClientRect().top <= 100 ) {
					current = id;
				}
			} );

			// A short final section can never get its top above the line if the
			// page cannot scroll that far, so the bottom always means the last.
			if ( window.scrollY + window.innerHeight >= document.documentElement.scrollHeight - 4 ) {
				current = ids[ ids.length - 1 ];
			}

			links.forEach( function ( a ) {
				a.classList.toggle( 'is-active', a.getAttribute( 'href' ) === '#' + current );
			} );
		};

		// One read per frame at most — the handler measures four elements, but
		// it must not do that once per scroll event.
		var queued = false;
		window.addEventListener( 'scroll', function () {
			if ( queued ) {
				return;
			}
			queued = true;
			window.requestAnimationFrame( function () {
				queued = false;
				markSection();
			} );
		}, { passive: true } );

		markSection();
	}

	// ─── Phone preview ──────────────────────────────────────────────────────
	//
	// The Message section shows what a locally-typed number turns into. Doing it
	// only on save means the setting cannot be checked without saving it, so it
	// follows the field. The rule mirrors wellweb_notify_normalize_phone() for
	// the one case this preview covers: a national number with a leading zero.
	var codeField = document.getElementById( 'f-country-code' );
	var preview   = document.querySelector( '.wellweb-notify-phone__preview' );

	if ( codeField && preview ) {
		codeField.addEventListener( 'input', function () {
			var code = codeField.value.replace( /[^0-9]/g, '' );
			var out  = preview.querySelector( '.wellweb-notify-phone__out' );
			var raw  = ( preview.getAttribute( 'data-example' ) || '' ).replace( /[^0-9]/g, '' );

			out.textContent = code
				? '+' + code + raw.replace( /^0+/, '' )
				: ( i18n.setCode || 'set a code' );
		} );
	}

	// ─── Send test ──────────────────────────────────────────────────────────

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '.wellweb-notify-test' );
		if ( ! btn ) {
			return;
		}

		e.preventDefault();

		var result = btn.parentNode.querySelector( '.wellweb-notify-test-result' );
		var body   = new URLSearchParams( {
			action: 'wellweb_notify_test',
			nonce: cfg.nonce || '',
			channel: btn.getAttribute( 'data-channel' ) || ''
		} );

		btn.disabled = true;
		setResult( result, i18n.testing || 'Sending…', '' );

		window.fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		} )
			.then( function ( r ) { return r.json(); } )
			.then( function ( json ) {
				if ( json && json.success ) {
					setResult( result, i18n.success || 'Sent', 'is-ok' );
				} else {
					setResult( result, ( json && json.data ) || i18n.error || 'Failed', 'is-bad' );
				}
			} )
			.catch( function () {
				setResult( result, i18n.error || 'Failed', 'is-bad' );
			} )
			.then( function () {
				btn.disabled = false;
			} );
	} );

	function setResult( el, text, state ) {
		if ( ! el ) {
			return;
		}
		el.className = 'wellweb-notify-test-result ' + state;
		el.textContent = text;
	}

	// ─── Click to copy ──────────────────────────────────────────────────────

	function copy( el ) {
		var text = el.textContent.trim();
		var done = function () {
			var original = el.textContent;
			el.textContent = i18n.copied || 'Copied';
			window.setTimeout( function () { el.textContent = original; }, 1200 );
		};

		if ( navigator.clipboard && window.isSecureContext ) {
			navigator.clipboard.writeText( text ).then( done );
			return;
		}

		var field = document.createElement( 'textarea' );
		field.value = text;
		document.body.appendChild( field );
		field.select();
		document.execCommand( 'copy' );
		document.body.removeChild( field );
		done();
	}

	document.addEventListener( 'click', function ( e ) {
		var el = e.target.closest( '.wellweb-notify-copy' );
		if ( el ) {
			copy( el );
		}
	} );

	document.addEventListener( 'keydown', function ( e ) {
		if ( e.key !== 'Enter' && e.key !== ' ' ) {
			return;
		}
		var el = e.target.closest( '.wellweb-notify-copy' );
		if ( el ) {
			e.preventDefault();
			copy( el );
		}
	} );
}() );
