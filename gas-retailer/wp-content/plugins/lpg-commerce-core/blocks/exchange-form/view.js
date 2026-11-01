/**
 * Front end of `lpg/exchange-form`: filters sizes by brand, logs the
 * enquiry through lpg/v1/enquiry and continues to the returned whatsapp_url.
 * When that is empty or the request fails, the link is built here from the
 * template and number in the data attributes, so the visitor still reaches
 * WhatsApp.
 */
import { __ } from '@wordpress/i18n';
import {
	buildMessage,
	waLink,
	postJson,
	announceNavigation,
	onReady,
} from '../shared/whatsapp';
import { clearErrors, showError, setStatus } from '../shared/fields';

function parse( json, fallback ) {
	try {
		return JSON.parse( json ) ?? fallback;
	} catch {
		return fallback;
	}
}

function init( block ) {
	const form = block.querySelector( '.lpg-exchange__form' );
	if ( ! form ) {
		return;
	}
	const fallback = block.querySelector( '.lpg-exchange__fallback' );
	const brand = form.querySelector( '[name="brand"]' );
	const size = form.querySelector( '[name="size"]' );
	const location = form.querySelector( '[name="location"]' );
	const submit = form.querySelector( '.lpg-exchange__submit' );
	const status = form.querySelector( '.lpg-exchange__status' );
	const types = parse( block.dataset.lpgTypes, {} );

	form.hidden = false;
	if ( fallback ) {
		fallback.hidden = true;
	}

	const fillSizes = () => {
		const option = brand.selectedOptions[ 0 ];
		const sizes = option ? parse( option.dataset.lpgSizes, [] ) : [];
		size.replaceChildren();
		const placeholder = document.createElement( 'option' );
		placeholder.value = '';
		placeholder.textContent = brand.value
			? __( 'Choose a size…', 'lpg-commerce-core' )
			: __( 'Choose a brand first', 'lpg-commerce-core' );
		size.appendChild( placeholder );
		sizes.forEach( ( label ) => {
			const item = document.createElement( 'option' );
			item.value = label;
			item.textContent = label;
			size.appendChild( item );
		} );
		size.disabled = ! brand.value || sizes.length === 0;
	};
	brand.addEventListener( 'change', fillSizes );
	fillSizes();

	form.addEventListener( 'submit', async ( event ) => {
		event.preventDefault();
		clearErrors( form );
		setStatus( status, '' );

		const required = __( 'This field is required.', 'lpg-commerce-core' );
		let first = null;
		if ( ! brand.value ) {
			first = showError( form, 'brand', required );
		}
		if ( ! size.value ) {
			first = first || showError( form, 'size', required );
		}
		if ( ! location.value.trim() ) {
			first = first || showError( form, 'location', required );
		}
		if ( first ) {
			first.focus();
			return;
		}

		const exchangeType =
			form.querySelector( '[name="exchange_type"]:checked' )?.value ||
			'exchange';
		const body = {
			type: 'exchange',
			brand: brand.value,
			size: size.value,
			exchange_type: exchangeType,
			location: location.value.trim(),
		};

		submit.disabled = true;
		form.setAttribute( 'aria-busy', 'true' );
		setStatus( status, __( 'Opening WhatsApp…', 'lpg-commerce-core' ) );

		const { status: code, data } = await postJson( block, body );

		submit.disabled = false;
		form.removeAttribute( 'aria-busy' );

		if ( code === 400 && data?.data?.params ) {
			let firstInvalid = null;
			Object.entries( data.data.params ).forEach(
				( [ key, message ] ) => {
					firstInvalid =
						firstInvalid || showError( form, key, message );
				}
			);
			setStatus(
				status,
				__(
					'Please correct the highlighted fields.',
					'lpg-commerce-core'
				),
				! firstInvalid
			);
			firstInvalid?.focus();
			return;
		}

		let url = code === 201 && data?.whatsapp_url ? data.whatsapp_url : '';
		if ( ! url ) {
			url = waLink(
				block.dataset.lpgNumber,
				buildMessage( block.dataset.lpgTemplate || '', {
					type: types[ exchangeType ] || exchangeType,
					brand: brand.selectedOptions[ 0 ]?.dataset.lpgName || '',
					size: size.value,
					location: body.location,
				} )
			);
		}
		if ( ! url ) {
			setStatus(
				status,
				__(
					'Sorry, WhatsApp is not available right now. Please call us instead.',
					'lpg-commerce-core'
				),
				true
			);
			return;
		}
		setStatus( status, '' );
		if ( announceNavigation( block, url ) ) {
			window.location.assign( url );
		}
	} );
}

onReady( '.lpg-exchange', init );
