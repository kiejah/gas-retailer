/**
 * Front end of `lpg/rfq-form`: reveals the form, manages item rows and
 * submits to lpg/v1/rfq as JSON with the REST nonce.
 */
import { __ } from '@wordpress/i18n';
import { postJson, onReady } from '../shared/whatsapp';
import { clearErrors, showError, setStatus } from '../shared/fields';

function value( form, key ) {
	const el = form.querySelector(
		'[data-lpg-field="' + window.CSS.escape( key ) + '"]'
	);
	return el ? el.value.trim() : '';
}

function init( block ) {
	const form = block.querySelector( '.lpg-rfq__form' );
	if ( ! form ) {
		return;
	}
	const fallback = block.querySelector( '.lpg-rfq__fallback' );
	const list = form.querySelector( '.lpg-rfq__item-list' );
	const template = form.querySelector( '.lpg-rfq__item-template' );
	const add = form.querySelector( '.lpg-rfq__add-item' );
	const submit = form.querySelector( '.lpg-rfq__submit' );
	const status = form.querySelector( '.lpg-rfq__status' );
	const max = Number( add?.dataset.lpgMax ) || 50;
	let counter = 1;

	form.hidden = false;
	if ( fallback ) {
		fallback.hidden = true;
	}

	const rows = () => Array.from( list.querySelectorAll( '.lpg-rfq__item' ) );

	// Keep data-lpg-field keys positional (items.0…), as the API reports them.
	const refresh = () => {
		const all = rows();
		all.forEach( ( row, index ) => {
			row.querySelectorAll( '[data-lpg-field]' ).forEach( ( el ) => {
				el.dataset.lpgField = el.dataset.lpgField.replace(
					/^items\.[^.]+\./,
					'items.' + index + '.'
				);
			} );
			const remove = row.querySelector( '.lpg-rfq__remove-item' );
			if ( remove ) {
				remove.hidden = all.length === 1;
			}
		} );
		if ( add ) {
			add.hidden = all.length >= max;
		}
	};

	add?.addEventListener( 'click', () => {
		if ( rows().length >= max ) {
			return;
		}
		list.insertAdjacentHTML(
			'beforeend',
			template.innerHTML.replaceAll( '__INDEX__', String( counter++ ) )
		);
		refresh();
		rows().at( -1 ).querySelector( 'input' )?.focus();
	} );

	list.addEventListener( 'click', ( event ) => {
		const button = event.target.closest( '.lpg-rfq__remove-item' );
		if ( ! button ) {
			return;
		}
		const row = button.closest( '.lpg-rfq__item' );
		const previous = row.previousElementSibling || row.nextElementSibling;
		row.remove();
		refresh();
		( previous?.querySelector( 'input' ) || add )?.focus();
	} );

	refresh();

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		clearErrors( form );
		setStatus( status, '' );

		// Client-side required check (the server validates everything again).
		let first = null;
		form.querySelectorAll( '[required]' ).forEach( ( el ) => {
			const empty =
				el.type === 'checkbox' ? ! el.checked : el.value.trim() === '';
			if ( empty ) {
				const marked = showError(
					form,
					el.dataset.lpgField,
					el.type === 'checkbox'
						? __(
								'Please tick this box to send your request.',
								'lpg-commerce-core'
							)
						: __( 'This field is required.', 'lpg-commerce-core' )
				);
				first = first || marked;
			}
		} );
		if ( first ) {
			setStatus(
				status,
				__(
					'Please correct the highlighted fields.',
					'lpg-commerce-core'
				)
			);
			first.focus();
			return;
		}

		const body = {
			contact: {
				name: value( form, 'contact.name' ),
				organisation: value( form, 'contact.organisation' ),
				phone: value( form, 'contact.phone' ),
				email: value( form, 'contact.email' ),
			},
			segment: value( form, 'segment' ),
			items: rows().map( ( row, index ) => ( {
				description: value( row, 'items.' + index + '.description' ),
				qty:
					parseInt( value( row, 'items.' + index + '.qty' ), 10 ) ||
					0,
			} ) ),
			requirements: value( form, 'requirements' ),
			project: value( form, 'project' ),
			location: value( form, 'location' ),
			comments: value( form, 'comments' ),
			consent: !! form.querySelector( '[name="consent"]' )?.checked,
			hp: form.querySelector( '[name="hp"]' )?.value || '',
		};

		submit.disabled = true;
		form.setAttribute( 'aria-busy', 'true' );
		setStatus( status, __( 'Sending…', 'lpg-commerce-core' ) );

		const { status: code, data } = await postJson( block, body );

		submit.disabled = false;
		form.removeAttribute( 'aria-busy' );

		if ( code === 201 ) {
			const success = document.createElement( 'div' );
			success.className = 'lpg-rfq__success lpg-form-success';
			success.setAttribute( 'role', 'status' );
			success.tabIndex = -1;
			success.textContent =
				data?.message ||
				__(
					'Thank you. We have received your quote request.',
					'lpg-commerce-core'
				);
			form.hidden = true;
			block.appendChild( success );
			success.focus();
			return;
		}

		if ( code === 400 && data?.data?.params ) {
			let firstInvalid = null;
			const unmatched = [];
			Object.entries( data.data.params ).forEach(
				( [ key, message ] ) => {
					const marked = showError( form, key, message );
					if ( marked ) {
						firstInvalid = firstInvalid || marked;
					} else {
						unmatched.push( message );
					}
				}
			);
			setStatus(
				status,
				[
					__(
						'Please correct the highlighted fields.',
						'lpg-commerce-core'
					),
					...unmatched,
				].join( ' ' ),
				! firstInvalid
			);
			firstInvalid?.focus();
			return;
		}

		let message = __(
			'Sorry, something went wrong. Please try again, or contact us by phone.',
			'lpg-commerce-core'
		);
		if ( code === 429 ) {
			message = __(
				'Too many requests. Please wait a few minutes and try again, or contact us by phone.',
				'lpg-commerce-core'
			);
		} else if ( code === 403 ) {
			message = __(
				'Your session has expired. Please reload the page and try again.',
				'lpg-commerce-core'
			);
		}
		setStatus( status, message, true );
	} );
}

onReady( '.lpg-rfq', init );
