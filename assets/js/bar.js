/*
 * Betnero Core: barra in basso con l'offerta dell'operatore.
 * Si apre dopo un attimo e resta sempre visibile (dalla 0.4.2 non si chiude piu').
 * Scrive la sua altezza in --bnb-h, cosi' il pulsante "torna su" sta sopra la barra.
 */
( function () {
	var bar = document.getElementById( 'bnb-bar' );
	if ( ! bar ) {
		return;
	}
	function setHeight() {
		document.documentElement.style.setProperty( '--bnb-h', bar.offsetHeight + 'px' );
	}
	bar.hidden = false;
	setHeight();
	window.setTimeout( function () {
		bar.classList.add( 'bnb-open' );
		document.body.classList.add( 'bnb-has-bar' );
		setHeight();
	}, 800 );
	window.addEventListener( 'resize', setHeight, { passive: true } );
} )();
