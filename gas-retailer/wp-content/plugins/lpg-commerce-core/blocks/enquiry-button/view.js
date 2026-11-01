/**
 * Front end of `lpg/enquiry-button` (C19).
 *
 * Enhances the plain wa.me link into a toggle for the editable message
 * panel. "Open WhatsApp" always points at a wa.me link built here from the
 * edited text; clicking it logs the enquiry (best effort, without the text)
 * and follows the link.
 */
import {
	freeText,
	waLink,
	postJson,
	announceNavigation,
	onReady,
} from '../shared/whatsapp';

function init( block ) {
	const toggle = block.querySelector( '.lpg-enquiry__toggle' );
	const panel = block.querySelector( '.lpg-enquiry__panel' );
	const message = block.querySelector( '.lpg-enquiry__message' );
	const open = block.querySelector( '.lpg-enquiry__open' );
	if ( ! toggle || ! panel || ! message || ! open ) {
		return;
	}
	const number = block.dataset.lpgNumber || '';

	toggle.setAttribute( 'role', 'button' );
	toggle.setAttribute( 'aria-expanded', 'false' );
	toggle.setAttribute( 'aria-controls', panel.id );

	const setOpen = ( isOpen ) => {
		panel.hidden = ! isOpen;
		toggle.setAttribute( 'aria-expanded', String( isOpen ) );
		if ( isOpen ) {
			message.focus();
		}
	};

	toggle.addEventListener( 'click', ( event ) => {
		event.preventDefault();
		setOpen( panel.hidden );
	} );
	toggle.addEventListener( 'keydown', ( event ) => {
		if ( event.key === ' ' ) {
			event.preventDefault();
			setOpen( panel.hidden );
		}
	} );
	panel.addEventListener( 'keydown', ( event ) => {
		if ( event.key === 'Escape' ) {
			setOpen( false );
			toggle.focus();
		}
	} );

	const sync = () => {
		const url = waLink( number, freeText( message.value ) );
		if ( url ) {
			open.href = url;
		}
	};
	message.addEventListener( 'input', sync );
	sync();

	open.addEventListener( 'click', ( event ) => {
		sync();
		// Best effort: never wait for, or fail on, the log request.
		postJson(
			block,
			{
				type: 'product',
				product_id: Number( block.dataset.lpgProduct ) || 0,
			},
			true
		);
		if ( ! announceNavigation( block, open.href ) ) {
			event.preventDefault();
		}
	} );
}

onReady( '.lpg-enquiry', init );
