/* Betnero: filtri delle tabelle casino e scommesse (nel browser, senza ricaricare). */
( function () {
	function matches( row, key, value ) {
		if ( ! value ) {
			return true;
		}
		var data = ( row.getAttribute( 'data-' + key ) || '' ).split( ' ' );
		return data.indexOf( value ) !== -1;
	}

	function apply( table ) {
		var selects = table.querySelectorAll( '[data-bnt-filter]' );
		var rows = table.querySelectorAll( '.bnt-row' );
		var active = false;
		var visible = 0;
		var n = 0;
		selects.forEach( function ( s ) { if ( s.value ) { active = true; } } );
		rows.forEach( function ( row ) {
			var ok = true;
			selects.forEach( function ( s ) {
				if ( ! matches( row, s.getAttribute( 'data-bnt-filter' ), s.value ) ) {
					ok = false;
				}
			} );
			row.hidden = ! ok;
			if ( ok ) {
				visible++;
				n++;
				var rank = row.querySelector( '.bnt-rank' );
				if ( rank ) { rank.textContent = n; }
			}
		} );
		var empty = table.querySelector( '.bnt-empty' );
		if ( empty ) { empty.hidden = visible > 0; }
		var reset = table.querySelector( '[data-bnt-reset]' );
		if ( reset ) { reset.hidden = ! active; }
	}

	function init() {
		document.querySelectorAll( '[data-bnt-table]' ).forEach( function ( table ) {
			table.querySelectorAll( '[data-bnt-filter]' ).forEach( function ( s ) {
				s.addEventListener( 'change', function () { apply( table ); } );
			} );
			var reset = table.querySelector( '[data-bnt-reset]' );
			if ( reset ) {
				reset.addEventListener( 'click', function () {
					table.querySelectorAll( '[data-bnt-filter]' ).forEach( function ( s ) { s.value = ''; } );
					apply( table );
				} );
			}
		} );
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
