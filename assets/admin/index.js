/**
 * FormPipe admin: tabs + tag-generator dialog wiring.
 */
( function () {
	'use strict';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function $( s, r ) { return ( r || document ).querySelector( s ); }
	function $$( s, r ) { return Array.prototype.slice.call( ( r || document ).querySelectorAll( s ) ); }

	function tabs() {
		const links = $$( '.formpipe-tabs .nav-tab' );
		const panels = $$( '.formpipe-tab' );

		links.forEach( function ( link ) {
			link.addEventListener( 'click', function ( e ) {
				if ( link.getAttribute( 'href' ) && link.getAttribute( 'href' ).indexOf( 'active-tab=' ) !== -1 ) {
					return; // let the URL change happen
				}
				e.preventDefault();
				links.forEach( function ( l ) {
					l.classList.remove( 'nav-tab-active' );
					l.setAttribute( 'aria-selected', 'false' );
				} );
				panels.forEach( function ( p ) { p.hidden = true; } );
				link.classList.add( 'nav-tab-active' );
				link.setAttribute( 'aria-selected', 'true' );
				const tab = link.dataset.tab;
				const panel = $( '.formpipe-tab-' + tab );
				if ( panel ) { panel.hidden = false; }
			} );
		} );
	}

	function tagGenerator() {
		// Tag-generator dialog open/close.
		document.addEventListener( 'click', function ( e ) {
			const opener = e.target.closest( '[data-taggen="open-dialog"]' );
			const closer = e.target.closest( '[data-taggen="close-dialog"]' );
			const inserter = e.target.closest( '[data-taggen="insert-tag"]' );

			if ( opener ) {
				e.preventDefault();
				const dlg = document.getElementById( opener.dataset.target );
				if ( dlg && typeof dlg.showModal === 'function' ) {
					try { dlg.showModal(); } catch ( _ ) { dlg.setAttribute( 'open', '' ); }
				} else if ( dlg ) {
					dlg.setAttribute( 'open', '' );
				}
			} else if ( closer ) {
				e.preventDefault();
				const dlg = closer.closest( 'dialog' );
				if ( dlg ) {
					if ( typeof dlg.close === 'function' ) { dlg.close(); }
					else { dlg.removeAttribute( 'open' ); }
				}
			} else if ( inserter ) {
				e.preventDefault();
				const panel = inserter.closest( '.formpipe-tag-dialog' );
				if ( ! panel ) { return; }
				const tag = buildTag( panel );
				if ( ! tag ) { return; }
				insertIntoTemplate( tag );
				if ( typeof panel.close === 'function' ) { panel.close(); }
				else { panel.removeAttribute( 'open' ); }
			}
		} );

		// Escape closes any open dialog.
		document.addEventListener( 'keydown', function ( e ) {
			if ( e.key !== 'Escape' ) { return; }
			$$( 'dialog.formpipe-tag-dialog[open]' ).forEach( function ( d ) {
				if ( typeof d.close === 'function' ) { d.close(); }
				else { d.removeAttribute( 'open' ); }
			} );
		} );
	}

	function buildTag( panel ) {
		const fields = $( '.formpipe-tag-fields', panel );
		if ( ! fields ) { return ''; }
		const basetype = fields.dataset.basetype || '';
		const parts = [ basetype ];

		$$( '[name]', fields ).forEach( function ( el ) {
			const name = el.name;
			const type = el.type;
			let value = '';
			if ( type === 'checkbox' ) {
				if ( ! el.checked ) { return; }
				value = '*';
			} else if ( el.value !== '' ) {
				value = el.value;
			} else {
				return;
			}

			if ( name === 'type' && value === basetype ) { return; }
			if ( name === 'options' ) {
				const lines = String( value ).split( /\r?\n/ ).filter( Boolean );
				parts.push( '"' + lines.join( ' ' ) + '"' );
				return;
			}
			if ( name === 'question' ) {
				parts.push( '"' + value + '"' );
				return;
			}
			if ( name === 'required' ) {
				parts[ 0 ] = basetype + '*';
				return;
			}
			parts.push( value );
		} );

		return '[' + parts.join( ' ' ) + ']';
	}

	function insertIntoTemplate( tag ) {
		const ta = $( '#wpcf7-form' );
		if ( ! ta ) { return; }
		const start = ta.selectionStart || ta.value.length;
		const end   = ta.selectionEnd   || ta.value.length;
		ta.value = ta.value.slice( 0, start ) + tag + ta.value.slice( end );
		ta.dispatchEvent( new Event( 'input', { bubbles: true } ) );
	}

	ready( function () {
		tabs();
		tagGenerator();
	} );
}() );
