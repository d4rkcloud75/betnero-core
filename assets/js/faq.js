/*
 * Betnero Core: FAQ (blocco Dettagli con stile "FAQ Betnero").
 * - apertura e chiusura animate (altezza e dissolvenza del testo);
 * - nei gruppi esclusivi (contenitore .bn-hh-faq o [data-bn-exclusive])
 *   aprendo una domanda si chiudono le altre.
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
		return p && ( p.classList.contains( 'bn-hh-faq' ) || p.hasAttribute( 'data-bn-exclusive' ) ) ? p : null;
	}

	document.addEventListener( 'click', function ( e ) {
		var summary = e.target.closest( 'details.is-style-bn-faq > summary' );
		if ( ! summary ) {
			return;
		}
		var el = summary.parentElement;
		e.preventDefault();
		// A meta' animazione il clic inverte la direzione; altrimenti apre o chiude.
		var opening = direction.has( el ) ? ! direction.get( el ) : ! el.open;
		if ( opening ) {
			var g = exclusiveGroup( el );
			if ( g ) {
				Array.prototype.forEach.call( g.querySelectorAll( ':scope > details.is-style-bn-faq[open]' ), function ( other ) {
					if ( other !== el ) {
						animateTo( other, false );
					}
				} );
			}
		}
		animateTo( el, opening );
	} );
} )();
