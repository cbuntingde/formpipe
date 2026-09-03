/**
 * FormPipe front-end: ajax submit, basic a11y, live counters, quiz prefill.
 */
( function () {
	'use strict';

	const API_BASE = '/wp-json/formpipe/v1/';

	function ready( fn ) {
		if ( document.readyState !== 'loading' ) {
			fn();
		} else {
			document.addEventListener( 'DOMContentLoaded', fn );
		}
	}

	function $( sel, root ) { return ( root || document ).querySelector( sel ); }
	function $$( sel, root ) { return Array.prototype.slice.call( ( root || document ).querySelectorAll( sel ) ); }

	function setResponse( form, status, message, errors ) {
		const out = $( '.formpipe-response-output', form );
		if ( out ) {
			out.dataset.status = status || '';
			out.textContent = message || '';
		}

		form.dataset.status = status || 'init';

		$$( '.formpipe-not-valid-tip', form ).forEach( function ( tip ) {
			tip.textContent = '';
			tip.classList.remove( 'formpipe-not-valid-tip-visible' );
		} );

		$$( '[aria-invalid]', form ).forEach( function ( el ) {
			el.removeAttribute( 'aria-invalid' );
		} );

		if ( errors && typeof errors === 'object' ) {
			Object.keys( errors ).forEach( function ( name ) {
				const input = form.querySelector( '[name="' + name + '"], [name="' + name + '[]"]' );
				if ( ! input ) {
					return;
				}
				input.setAttribute( 'aria-invalid', 'true' );
				const id = errors[ name ].idref;
				const tip = form.querySelector( '.formpipe-not-valid-tip[data-for="' + name + '"], .formpipe-not-valid-tip#' + id );
				if ( tip ) {
					tip.textContent = errors[ name ].reason || '';
					tip.classList.add( 'formpipe-not-valid-tip-visible' );
				}
			} );
		}

		const target = form.getAttribute( 'data-unit-tag' );
		if ( target && status === 'mail_sent' ) {
			form.reset();
		}
	}

	function collect( form ) {
		const fd = new FormData( form );
		// Trim whitespace from text inputs.
		for ( const pair of fd.entries() ) {
			if ( typeof pair[ 1 ] === 'string' ) {
				fd.set( pair[ 0 ], pair[ 1 ].trim() );
			}
		}
		return fd;
	}

	function buildPostedHash( form ) {
		const data = {};
		for ( const pair of new FormData( form ).entries() ) {
			data[ pair[ 0 ] ] = pair[ 1 ];
		}
		const tick = Math.ceil( Date.now() / 30000 );
		// Lightweight hash; Submission re-validates server-side.
		let s = tick + '|' + data._formpipe_unit_tag + '|';
		Object.keys( data ).sort().forEach( function ( k ) {
			s += k + '=' + String( data[ k ] ).slice( 0, 256 ) + '\n';
		} );
		return simpleHash( s );
	}

	function simpleHash( s ) {
		let h = 5381;
		for ( let i = 0; i < s.length; i++ ) {
			h = ( ( h << 5 ) + h ) ^ s.charCodeAt( i );
		}
		return ( '0000000' + ( h >>> 0 ).toString( 16 ) ).slice( -8 );
	}

	function submitAjax( form, e ) {
		e.preventDefault();
		const submit = form.querySelector( '.formpipe-submit' );
		if ( submit ) {
			submit.disabled = true;
		}

		const id = form.dataset.id;
		const fd = collect( form );

		// Compute the posted-data hash (replay protection).
		const hashInput = form.querySelector( 'input[name="_formpipe_posted_hash"]' );
		if ( hashInput ) {
			hashInput.value = buildPostedHash( form );
		}

		fetch( API_BASE + 'forms/' + id + '/feedback', {
			method: 'POST',
			body: fd,
			credentials: 'same-origin',
		} )
		.then( function ( r ) {
			return r.json().then( function ( json ) {
				return { ok: r.ok, json: json };
			} );
		} )
		.then( function ( resp ) {
			setResponse( form, resp.json.status || 'init', resp.json.message || '', resp.json.errors || {} );

			// Mirror posted values into response[output] fields.
			$$( '.formpipe-response-field', form ).forEach( function ( out ) {
				const field = out.dataset.field;
				const valSpan = $( '.formpipe-response-value', out );
				if ( ! valSpan || ! field ) {
					return;
				}
				const fieldEl = form.querySelector( '[name="' + field + '"], [name="' + field + '[]"]' );
				if ( ! fieldEl ) {
					valSpan.textContent = '';
					return;
				}
				let val = '';
				if ( fieldEl.tagName === 'SELECT' && fieldEl.multiple ) {
					val = Array.from( fieldEl.selectedOptions ).map( function ( o ) { return o.value; } ).join( ', ' );
				} else if ( fieldEl.type === 'checkbox' ) {
					const checked = $$( '[name="' + field + '[]"]:checked', form );
					val = checked.map( function ( c ) { return c.value; } ).join( ', ' );
				} else if ( fieldEl.type === 'radio' ) {
					const checked = $( '[name="' + field + '"]:checked', form );
					val = checked ? checked.value : '';
				} else {
					val = fieldEl.value || '';
				}
				valSpan.textContent = val;
			} );

			if ( resp.json.status === 'mail_sent' ) {
				form.reset();
			}
		} )
		.catch( function () {
			setResponse( form, 'mail_failed', 'Network error. Please try again.', {} );
		} )
		.finally( function () {
			if ( submit ) {
				submit.disabled = false;
			}
		} );
	}

	function liveCounter( form ) {
		$$( '.formpipe-count', form ).forEach( function ( out ) {
			const target = form.querySelector( '[name="' + out.getAttribute( 'for' ) + '"]' );
			if ( ! target ) {
				return;
			}
			const update = function () {
				const v = target.value || '';
				const n = out.dataset.mode === 'words'
					? ( v.trim() ? v.trim().split( /\s+/ ).length : 0 )
					: v.length;
				out.textContent = String( n );
			};
			target.addEventListener( 'input', update );
			update();
		} );
	}

	function clientValidate( form ) {
		// Honor HTML5 required + type attributes.
		if ( form.checkValidity && ! form.checkValidity() ) {
			form.reportValidity();
			return false;
		}
		return true;
	}

	ready( function () {
		$$( '.formpipe-form' ).forEach( function ( form ) {
			form.addEventListener( 'submit', function ( e ) {
				if ( ! clientValidate( form ) ) {
					return;
				}
				submitAjax( form, e );
			} );

			liveCounter( form );
		} );
	} );
}() );
