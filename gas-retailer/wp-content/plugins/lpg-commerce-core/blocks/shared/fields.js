/**
 * Inline field errors for the RFQ and exchange forms.
 *
 * Fields carry data-lpg-field="<api key>" (e.g. contact.email, items.0.qty)
 * and an error slot with id "<field id>-error".
 */

function controls( el ) {
	return el.matches( 'fieldset' )
		? Array.from( el.querySelectorAll( 'input, select, textarea' ) )
		: [ el ];
}

/**
 * Remove every error from a form.
 *
 * @param {HTMLFormElement} form Form.
 */
export function clearErrors( form ) {
	form.querySelectorAll( '.lpg-field-error' ).forEach( ( slot ) => {
		slot.textContent = '';
		slot.hidden = true;
	} );
	form.querySelectorAll( '[aria-invalid]' ).forEach( ( el ) => {
		el.removeAttribute( 'aria-invalid' );
		const ids = ( el.getAttribute( 'aria-describedby' ) || '' )
			.split( /\s+/ )
			.filter( ( id ) => id && ! id.endsWith( '-error' ) );
		if ( ids.length ) {
			el.setAttribute( 'aria-describedby', ids.join( ' ' ) );
		} else {
			el.removeAttribute( 'aria-describedby' );
		}
	} );
}

/**
 * Show an error on the field with the given API key.
 *
 * @param {HTMLFormElement} form    Form.
 * @param {string}          key     API key.
 * @param {string}          message Message.
 * @return {HTMLElement|null} The first control marked invalid, or null if no such field.
 */
export function showError( form, key, message ) {
	const el = form.querySelector(
		'[data-lpg-field="' + window.CSS.escape( key ) + '"]'
	);
	if ( ! el ) {
		return null;
	}
	const slot = el.id ? document.getElementById( el.id + '-error' ) : null;
	if ( slot ) {
		slot.textContent = message;
		slot.hidden = false;
	}
	const targets = controls( el );
	targets.forEach( ( control ) => {
		control.setAttribute( 'aria-invalid', 'true' );
		if ( slot ) {
			const ids = ( control.getAttribute( 'aria-describedby' ) || '' )
				.split( /\s+/ )
				.filter( Boolean );
			if ( ! ids.includes( slot.id ) ) {
				ids.push( slot.id );
			}
			control.setAttribute( 'aria-describedby', ids.join( ' ' ) );
		}
	} );
	return targets[ 0 ] || null;
}

/**
 * Show a message in the form's live status region.
 *
 * @param {HTMLElement} status  Status element.
 * @param {string}      message Message ('' clears).
 * @param {boolean}     focus   Move focus to it.
 */
export function setStatus( status, message, focus = false ) {
	if ( ! status ) {
		return;
	}
	status.textContent = message;
	status.classList.toggle( 'is-error', !! message );
	if ( focus && message ) {
		status.focus();
	}
}
