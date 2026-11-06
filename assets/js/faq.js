/*
 * Betnero Core: FAQ (blocco Dettagli con stile "FAQ Betnero").
 * - apertura e chiusura animate (altezza e dissolvenza del testo);
 * - anche il contenitore "Le domande piu' frequenti" (details.bn-faqbox) si apre animato;
 * - dalla 0.3.4 l'animazione vale per tutti i blocchi Dettagli e per l'indice dell'Academy;
 * - nei gruppi esclusivi (contenitore .bn-hh-faq, details.bn-faqbox, .bn-guide-grid o [data-bn-exclusive])
 *   aprendo una voce si chiudono le altre;
 * - un clic su un link dentro il titolo segue il link, senza aprire o chiudere.
 * Con "riduci movimento" attivo nel sistema le FAQ si aprono senza animazione.
 */
( function () {
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var EASE = 'cubic-bezier(.2,.7,.2,1)';
	var running = new WeakMap();
	var direction = new WeakMap();

	function borders( el ) {
		var cs = getComputedStyle( el );
		return parseFloat( cs.borderTopWidth ) + parseFloat( cs.borderBottomWidth );
	}

	function content( el ) {
		return Array.prototype.filter.call( el.children, function ( c ) {
			return c.tagName !== 'SUMMARY';
		} );
	}

	function stop( el ) {
		var a = running.get( el );
		if ( a ) {
			a.cancel();
			running.delete( el );
		}
		direction.delete( el );
		el.style.height = '';
		el.style.overflow = '';
	}

	function animateTo( el, open ) {
		var summary = el.querySelector( 'summary' );
		var start = el.getBoundingClientRect().height;
		stop( el );
		if ( open ) {
			el.open = true;
		}
		var end = open ? el.getBoundingClientRect().height : summary.getBoundingClientRect().height + borders( el );
		if ( reduce || start === end ) {
			el.open = open;
			return;
		}
		el.style.overflow = 'hidden';
		el.style.boxSizing = 'border-box';
		direction.set( el, open );
		var a = el.animate( [ { height: start + 'px' }, { height: end + 'px' } ], { duration: open ? 320 : 260, easing: EASE } );
		running.set( el, a );
		if ( ! open ) {
			el.setAttribute( 'data-bn-state', 'closing' );
		}
		var fades = content( el ).map( function ( c ) {
			return c.animate(
				open ? [ { opacity: 0, transform: 'translateY(-4px)' }, { opacity: 1, transform: 'none' } ] : [ { opacity: 1 }, { opacity: 0 } ],
				{ duration: open ? 320 : 180, easing: EASE, fill: open ? 'backwards' : 'forwards' }
			);
		} );
		a.onfinish = function () {
			running.delete( el );
			direction.delete( el );
			el.open = open;
			el.removeAttribute( 'data-bn-state' );
			el.style.height = '';
			el.style.overflow = '';
			fades.forEach( function ( f ) {
				f.cancel();
			} );
		};
		a.oncancel = function () {
			el.removeAttribute( 'data-bn-state' );
			fades.forEach( function ( f ) {
				f.cancel();
			} );
		};
	}

	function exclusiveGroup( el ) {
		var p = el.parentElement;
		return p && ( p.classList.contains( 'bn-hh-faq' ) || p.classList.contains( 'bn-faqbox' ) || p.classList.contains( 'bn-guide-grid' ) || p.hasAttribute( 'data-bn-exclusive' ) ) ? p : null;
	}

	document.addEventListener( 'click', function ( e ) {
		var summary = e.target.closest( 'details.wp-block-details > summary, .bn-acad-index details > summary' );
		if ( ! summary || e.target.closest( 'summary a' ) ) {
			return;
		}
		var el = summary.parentElement;
		e.preventDefault();
		// A meta' animazione il clic inverte la direzione; altrimenti apre o chiude.
		var opening = direction.has( el ) ? ! direction.get( el ) : ! el.open;
		if ( opening ) {
			var g = exclusiveGroup( el );
			if ( g ) {
				Array.prototype.forEach.call( g.querySelectorAll( ':scope > details[open]' ), function ( other ) {
					if ( other !== el ) {
						animateTo( other, false );
					}
				} );
			}
		}
		animateTo( el, opening );
	} );
} )();
