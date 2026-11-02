/* Betnero Core: barra in basso (si apre dopo un attimo, si chiude per la sessione) */
( function () {
	var bar = document.getElementById( 'bnb-bar' );
	if ( ! bar ) {
		return;
	}
	var key = 'bnb-closed-' + bar.getAttribute( 'data-bnb' );
	try {
		if ( window.sessionStorage.getItem( key ) ) {
			return;
		}
	} catch ( e ) {}
	bar.hidden = false;
	window.setTimeout( function () {
		bar.classList.add( 'bnb-open' );
		document.body.classList.add( 'bnb-has-bar' );
	}, 800 );
	bar.querySelector( '.bnb-close' ).addEventListener( 'click', function () {
		bar.classList.remove( 'bnb-open' );
		document.body.classList.remove( 'bnb-has-bar' );
		try {
			window.sessionStorage.setItem( key, '1' );
		} catch ( e ) {}
		window.setTimeout( function () { bar.hidden = true; }, 300 );
	} );
} )();
