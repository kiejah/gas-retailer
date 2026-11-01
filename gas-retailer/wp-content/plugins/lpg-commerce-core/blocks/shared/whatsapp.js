/**
 * Front-end helpers shared by the enquiry, RFQ and exchange view scripts.
 *
 * buildMessage(), freeText() and waLink() mirror
 * LPG\Commerce\WhatsApp\MessageBuilder so a link built in the browser matches
 * one built on the server (C19).
 */

export const MAX_LENGTH = 1000;

const PLACEHOLDERS = [
	'product',
	'sku',
	'url',
	'brand',
	'size',
	'type',
	'location',
];

// Control characters except tab and newline (built with RegExp so the source
// stays free of literal control characters).
const CONTROL_CHARS = new RegExp(
	'[\\u0000-\\u0008\\u000B\\u000C\\u000E-\\u001F\\u007F]',
	'g'
);

function scrub( text ) {
	return String( text ?? '' )
		.replace( /\r\n?/g, '\n' )
		.replace( CONTROL_CHARS, '' );
}

function cleanValue( value ) {
	const holder = document.createElement( 'textarea' );
	// Decode entities without parsing markup: strip tags first.
	holder.innerHTML = scrub( value ).replace( /<[^>]*>/g, '' );
	return holder.value
		.replace( /[{}]/g, ( c ) => ( c === '{' ? '(' : ')' ) )
		.replace( /\s+/g, ' ' )
		.trim();
}

function normalise( text ) {
	return text
		.split( '\n' )
		.map( ( line ) => line.replace( /[^\S\n]+/g, ' ' ).trim() )
		.join( '\n' )
		.replace( /\n{3,}/g, '\n\n' )
		.replace( / ?\(\s*\)/g, '' )
		.replace( / +([.,:;!?])/g, '$1' )
		.trim();
}

/**
 * Cut to MAX_LENGTH characters (code points, as mb_substr), then trim the end.
 *
 * @param {string} text Text.
 * @return {string} Text.
 */
export function truncate( text ) {
	const chars = Array.from( text );
	return chars.length <= MAX_LENGTH
		? text
		: chars.slice( 0, MAX_LENGTH ).join( '' ).trimEnd();
}

/**
 * Fill a template like MessageBuilder::build().
 *
 * @param {string} template Template with {placeholders}.
 * @param {Object} vars     Placeholder values.
 * @return {string} Message.
 */
export function buildMessage( template, vars ) {
	const text = scrub( template ).replace(
		/\{([A-Za-z0-9_-]*)\}/g,
		( match, name ) =>
			PLACEHOLDERS.includes( name ) ? cleanValue( vars[ name ] ) : ''
	);
	return truncate( normalise( text ) );
}

/**
 * A visitor's own edited message: newlines kept, control characters
 * removed, trimmed and cut to MAX_LENGTH.
 *
 * @param {string} text Text.
 * @return {string} Text.
 */
export function freeText( text ) {
	return truncate( scrub( text ).trim() );
}

/**
 * PHP rawurlencode(): encodeURIComponent plus ! ' ( ) *.
 *
 * @param {string} text Text.
 * @return {string} Encoded text.
 */
export function rawUrlEncode( text ) {
	return encodeURIComponent( text ).replace(
		/[!'()*]/g,
		( c ) => '%' + c.charCodeAt( 0 ).toString( 16 ).toUpperCase()
	);
}

/**
 * Click-to-chat link like MessageBuilder::link().
 *
 * @param {string} number Phone number (non-digits dropped).
 * @param {string} text   Message.
 * @return {string} URL, or '' without digits.
 */
export function waLink( number, text ) {
	const digits = String( number ?? '' ).replace( /\D+/g, '' );
	if ( ! digits ) {
		return '';
	}
	return (
		'https://wa.me/' +
		digits +
		( text ? '?text=' + rawUrlEncode( text ) : '' )
	);
}

/**
 * POST JSON to an lpg/v1 endpoint with the REST nonce.
 *
 * @param {HTMLElement} block     Block wrapper with data-lpg-endpoint / data-lpg-nonce.
 * @param {Object}      body      Body.
 * @param {boolean}     keepalive Keep the request alive across navigation.
 * @return {Promise<{status: number, data: Object|null}>} Result (status 0 on network failure).
 */
export async function postJson( block, body, keepalive = false ) {
	try {
		const response = await window.fetch( block.dataset.lpgEndpoint, {
			method: 'POST',
			credentials: 'same-origin',
			keepalive,
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': block.dataset.lpgNonce || '',
			},
			body: JSON.stringify( body ),
		} );
		let data = null;
		try {
			data = await response.json();
		} catch {
			data = null;
		}
		return { status: response.status, data };
	} catch {
		return { status: 0, data: null };
	}
}

/**
 * Announce a WhatsApp navigation with a cancelable `lpg:whatsapp` event
 * (detail.url) on the block. Returns false when a listener cancelled it.
 *
 * @param {HTMLElement} block Block wrapper.
 * @param {string}      url   URL.
 * @return {boolean} Whether to proceed.
 */
export function announceNavigation( block, url ) {
	return block.dispatchEvent(
		new window.CustomEvent( 'lpg:whatsapp', {
			bubbles: true,
			cancelable: true,
			detail: { url },
		} )
	);
}

/**
 * Run a callback for each matching element once the DOM is ready.
 *
 * @param {string}                  selector Selector.
 * @param {function(Element): void} callback Callback.
 */
export function onReady( selector, callback ) {
	const run = () => document.querySelectorAll( selector ).forEach( callback );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', run );
	} else {
		run();
	}
}
