/**
 * Player list filtering.
 *
 * The whole list is already in the page, so filtering is a display toggle and
 * never a request. A rank group with no visible rows hides its heading too,
 * otherwise a search for one name leaves a column of empty band headings behind.
 */
( function () {
	'use strict';

	function apply( root, query ) {
		var q = query.trim().toLowerCase();

		root.querySelectorAll( 'tr[data-wzc-name]' ).forEach( function ( row ) {
			var name = row.getAttribute( 'data-wzc-name' ) || '';
			row.style.display = ! q || name.indexOf( q ) > -1 ? '' : 'none';
		} );

		root.querySelectorAll( '.wzc-grp' ).forEach( function ( group ) {
			var rows = group.querySelectorAll( 'tr[data-wzc-name]' );
			var any = Array.prototype.some.call( rows, function ( row ) {
				return row.style.display !== 'none';
			} );
			group.style.display = any ? '' : 'none';
		} );
	}

	function init() {
		document.querySelectorAll( '[data-wzc-search]' ).forEach( function ( input ) {
			var root = input.closest( '.wzc-players' );
			if ( ! root ) {
				return;
			}

			input.addEventListener( 'input', function () {
				apply( root, input.value );
			} );
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
}() );
