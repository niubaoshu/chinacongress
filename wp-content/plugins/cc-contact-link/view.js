/**
 * cc-contact-link — 前台一键复制交互。
 */
( function () {
	'use strict';

	function fallbackCopy( text ) {
		var textarea = document.createElement( 'textarea' );
		textarea.value = text;
		textarea.setAttribute( 'readonly', '' );
		textarea.style.position = 'fixed';
		textarea.style.opacity = '0';
		document.body.appendChild( textarea );
		textarea.select();
		var ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( err ) {
			ok = false;
		}
		document.body.removeChild( textarea );
		return ok;
	}

	function markCopied( button ) {
		if ( button._ccCopyTimer ) {
			window.clearTimeout( button._ccCopyTimer );
		}
		button.textContent = '已复制 ✓';
		button.classList.add( 'is-copied' );
		button._ccCopyTimer = window.setTimeout( function () {
			button.textContent = '复制';
			button.classList.remove( 'is-copied' );
		}, 2000 );
	}

	document.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '.cc-contact-link__copy' );
		if ( ! button ) {
			return;
		}
		var text = button.getAttribute( 'data-copy-text' );
		if ( ! text ) {
			return;
		}

		if ( navigator.clipboard && navigator.clipboard.writeText ) {
			navigator.clipboard.writeText( text ).then(
				function () {
					markCopied( button );
				},
				function () {
					if ( fallbackCopy( text ) ) {
						markCopied( button );
					}
				}
			);
			return;
		}

		if ( fallbackCopy( text ) ) {
			markCopied( button );
		}
	} );
} )();
