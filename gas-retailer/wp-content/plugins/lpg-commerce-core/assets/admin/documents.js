/**
 * Product documents meta box: pick PDFs from the media library, remove rows.
 * Vanilla JS; uses wp.media only when the user may upload files.
 */
( function () {
	'use strict';

	var box = document.querySelector( '[data-lpg-documents]' );
	if ( ! box ) {
		return;
	}
	var body = box.querySelector( '[data-lpg-doc-rows]' );
	var template = box.querySelector( '[data-lpg-doc-template]' );
	var addButton = box.querySelector( '[data-lpg-doc-add]' );
	var empty = box.querySelector( '[data-lpg-doc-empty]' );
	var frame = null;

	function reindex() {
		var rows = body.querySelectorAll( '[data-lpg-doc-row]' );
		Array.prototype.forEach.call( rows, function ( row, index ) {
			Array.prototype.forEach.call( row.querySelectorAll( '[name^="lpg_documents["]' ), function ( input ) {
				input.name = input.name.replace( /^lpg_documents\[[^\]]*\]/, 'lpg_documents[' + index + ']' );
			} );
		} );
		if ( empty ) {
			empty.hidden = rows.length > 0;
		}
	}

	function addRow( attachment ) {
		if ( body.querySelector( '[data-lpg-doc-row][data-id="' + attachment.id + '"]' ) ) {
			return;
		}
		var fragment = template.content.cloneNode( true );
		var row = fragment.querySelector( '[data-lpg-doc-row]' );
		row.setAttribute( 'data-id', attachment.id );
		row.querySelector( '[data-lpg-doc-id]' ).value = attachment.id;
		row.querySelector( '[data-lpg-doc-file]' ).textContent = attachment.filename || attachment.title || ( '#' + attachment.id );
		row.querySelector( '[data-lpg-doc-label]' ).value = attachment.title || '';
		body.appendChild( fragment );
	}

	body.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( '[data-lpg-doc-remove]' );
		if ( button ) {
			var row = button.closest( '[data-lpg-doc-row]' );
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
					multiple: true
				} );
				frame.on( 'select', function () {
					frame.state().get( 'selection' ).each( function ( model ) {
						var attachment = model.toJSON();
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
}() );
