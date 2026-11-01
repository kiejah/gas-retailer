/**
 * Product documents meta box: pick PDFs from the media library, remove rows.
 * Vanilla JS; uses wp.media only when the user may upload files.
 */
( function () {
	'use strict';

	const box = document.querySelector( '[data-lpg-documents]' );
	if ( ! box ) {
		return;
	}
	const body = box.querySelector( '[data-lpg-doc-rows]' );
	const template = box.querySelector( '[data-lpg-doc-template]' );
	const addButton = box.querySelector( '[data-lpg-doc-add]' );
	const empty = box.querySelector( '[data-lpg-doc-empty]' );
	let frame = null;

	function reindex() {
		const rows = body.querySelectorAll( '[data-lpg-doc-row]' );
		Array.prototype.forEach.call( rows, function ( row, index ) {
			Array.prototype.forEach.call(
				row.querySelectorAll( '[name^="lpg_documents["]' ),
				function ( input ) {
					input.name = input.name.replace(
						/^lpg_documents\[[^\]]*\]/,
						'lpg_documents[' + index + ']'
					);
				}
			);
		} );
		if ( empty ) {
			empty.hidden = rows.length > 0;
		}
	}

	function addRow( attachment ) {
		if (
			body.querySelector(
				'[data-lpg-doc-row][data-id="' + attachment.id + '"]'
			)
		) {
			return;
		}
		const fragment = template.content.cloneNode( true );
		const row = fragment.querySelector( '[data-lpg-doc-row]' );
		row.setAttribute( 'data-id', attachment.id );
		row.querySelector( '[data-lpg-doc-id]' ).value = attachment.id;
		row.querySelector( '[data-lpg-doc-file]' ).textContent =
			attachment.filename || attachment.title || '#' + attachment.id;
		row.querySelector( '[data-lpg-doc-label]' ).value =
			attachment.title || '';
		body.appendChild( fragment );
	}

	body.addEventListener( 'click', function ( event ) {
		const button = event.target.closest( '[data-lpg-doc-remove]' );
		if ( button ) {
			const row = button.closest( '[data-lpg-doc-row]' );
			row.parentNode.removeChild( row );
			reindex();
		}
	} );

	if ( addButton && window.wp && window.wp.media ) {
		addButton.addEventListener( 'click', function () {
			if ( ! frame ) {
				frame = window.wp.media( {
					title: addButton.getAttribute( 'data-title' ),
					button: { text: addButton.getAttribute( 'data-button' ) },
					library: { type: 'application/pdf' },
					multiple: true,
				} );
				frame.on( 'select', function () {
					frame
						.state()
						.get( 'selection' )
						.each( function ( model ) {
							const attachment = model.toJSON();
							if ( 'application/pdf' === attachment.mime ) {
								addRow( attachment );
							}
						} );
					reindex();
				} );
			}
			frame.open();
		} );
	}

	reindex();
} )();
