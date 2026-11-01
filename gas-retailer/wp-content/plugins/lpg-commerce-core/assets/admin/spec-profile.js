/**
 * Spec profile field rows: add, remove, reorder. Vanilla JS, no build step.
 */
( function () {
	'use strict';

	var table = document.querySelector( '[data-lpg-fields]' );
	var template = document.querySelector( '[data-lpg-row-template]' );
	var addButton = document.querySelector( '[data-lpg-add-row]' );
	if ( ! table || ! template || ! addButton ) {
		return;
	}
	var body = table.querySelector( '[data-lpg-rows]' );

	// Rewrite lpg_fields[N][...] names so the posted order matches the visual order.
	function reindex() {
		Array.prototype.forEach.call( body.querySelectorAll( '[data-lpg-row]' ), function ( row, index ) {
			Array.prototype.forEach.call( row.querySelectorAll( '[name^="lpg_fields["]' ), function ( input ) {
				input.name = input.name.replace( /^lpg_fields\[[^\]]*\]/, 'lpg_fields[' + index + ']' );
			} );
		} );
	}

	function syncOptions( row ) {
		var type = row.querySelector( '[data-lpg-type]' );
		var options = row.querySelector( '[data-lpg-options]' );
		if ( type && options ) {
			options.hidden = 'select' !== type.value;
		}
	}

	addButton.addEventListener( 'click', function () {
		var fragment = template.content.cloneNode( true );
		var row = fragment.querySelector( '[data-lpg-row]' );
		body.appendChild( fragment );
		reindex();
		syncOptions( row );
		var first = row.querySelector( 'input' );
		if ( first ) {
			first.focus();
		}
	} );

	body.addEventListener( 'click', function ( event ) {
		var button = event.target.closest( 'button' );
		if ( ! button ) {
			return;
		}
		var row = button.closest( '[data-lpg-row]' );
		if ( button.hasAttribute( 'data-lpg-remove' ) ) {
			row.parentNode.removeChild( row );
		} else if ( 'up' === button.getAttribute( 'data-lpg-move' ) && row.previousElementSibling ) {
			body.insertBefore( row, row.previousElementSibling );
			button.focus();
		} else if ( 'down' === button.getAttribute( 'data-lpg-move' ) && row.nextElementSibling ) {
			body.insertBefore( row.nextElementSibling, row );
			button.focus();
		} else {
			return;
		}
		reindex();
	} );

	body.addEventListener( 'change', function ( event ) {
		if ( event.target.matches( '[data-lpg-type]' ) ) {
			syncOptions( event.target.closest( '[data-lpg-row]' ) );
		}
	} );

	Array.prototype.forEach.call( body.querySelectorAll( '[data-lpg-row]' ), syncOptions );
}() );
