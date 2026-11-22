/* Betnero Core: quote sempre fresche anche sulle pagine in cache (ricarica i blocchi via REST). */
( function () {
	function load( box ) {
		var url = box.getAttribute( 'data-url' );
		if ( box.hasAttribute( 'data-top' ) ) {
			url += '?a=' + encodeURIComponent( box.getAttribute( 'data-top' ) );
		} else if ( box.hasAttribute( 'data-qm' ) ) {
			url += '?a=' + encodeURIComponent( box.getAttribute( 'data-qm' ) ) + '&post=' + encodeURIComponent( box.getAttribute( 'data-qmpost' ) || '0' );
		} else if ( box.hasAttribute( 'data-tab' ) ) {
			url += '?a=' + encodeURIComponent( box.getAttribute( 'data-tab' ) );
		} else {
			url += '?post=' + encodeURIComponent( box.getAttribute( 'data-post' ) ) + '&tipo=' + encodeURIComponent( box.getAttribute( 'data-tipo' ) ) + '&mercato=' + encodeURIComponent( box.getAttribute( 'data-mercato' ) || 'auto' );
		}
		fetch( url, { credentials: 'omit' } ).then( function ( r ) {
			return r.ok ? r.json() : null;
		} ).then( function ( j ) {
			if ( j && j.html ) {
				box.innerHTML = j.html;
			}
		} ).catch( function () {} );
	}
	function start() {
		document.querySelectorAll( '.bnq-live[data-post], .bnq-live[data-tab], .bnq-live[data-qm], .bnq-live[data-top]' ).forEach( load );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
