/* Betnero Core 0.9.5: pagina partita, filtri dei mercati e linee (Under/Over, handicap). */
/* 0.9.26: un solo listener sul document invece di uno per blocco. Negli articoli quote.js
   ricrea i blocchi via REST appena la pagina si apre: con il listener legato al singolo
   [data-bnm] i pulsanti delle linee restavano senza ascoltatore. */
( function () {
	function gruppo( e ) {
		var g = e.target.closest( '[data-bnm-group]' );
		if ( ! g ) {
			return false;
		}
		var root = g.closest( '[data-bnm]' );
		if ( ! root ) {
			return false;
		}
		var grp = g.getAttribute( 'data-bnm-group' );
		root.querySelectorAll( '[data-bnm-group]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b === g ? 'true' : 'false' );
		} );
		var others = 0;
		root.querySelectorAll( '[data-bnm-mk]' ).forEach( function ( mk ) {
			var show = grp === 'tutti' || mk.getAttribute( 'data-bnm-mk' ) === grp;
			mk.hidden = ! show;
			if ( show && grp !== 'tutti' ) {
				mk.open = true;
			}
			if ( show && mk.classList.contains( 'is-other' ) ) {
				others++;
			}
		} );
		var box = root.querySelector( '[data-bnm-others]' );
		if ( box ) {
			box.hidden = others === 0;
		}
		return true;
	}

	function linea( e ) {
		var l = e.target.closest( '[data-bnm-line]' );
		if ( ! l ) {
			return false;
		}
		var mk = l.closest( '.bnm-mk' );
		if ( ! mk ) {
			return false;
		}
		var line = l.getAttribute( 'data-bnm-line' );
		mk.querySelectorAll( '[data-bnm-line]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-pressed', b === l ? 'true' : 'false' );
		} );
		mk.querySelectorAll( '.bnm-line' ).forEach( function ( d ) {
			d.hidden = d.getAttribute( 'data-line' ) !== line;
		} );
		return true;
	}

	/* 0.9.10: schede Partite / Risultati / Classifica del blocco Quote campionato */
	function schede( e ) {
		var t = e.target.closest( '[data-bnqc-tab]' );
		if ( ! t ) {
			return false;
		}
		var root = t.closest( '[data-bnqc]' ) || document;
		root.querySelectorAll( '[data-bnqc-tab]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-selected', b === t ? 'true' : 'false' );
		} );
		root.querySelectorAll( '.bnqc-panel' ).forEach( function ( p ) {
			p.hidden = p.id !== 'bnqc-p-' + t.getAttribute( 'data-bnqc-tab' );
		} );
		return true;
	}

	/* 0.9.12: schede del widget partita (API-Football) */
	function wtabs( e ) {
		var t = e.target.closest( '[data-bnw-tab]' );
		if ( ! t ) {
			return false;
		}
		var root = t.closest( '[data-bnw]' ) || document;
		root.querySelectorAll( '[data-bnw-tab]' ).forEach( function ( b ) {
			b.setAttribute( 'aria-selected', b === t ? 'true' : 'false' );
		} );
		root.querySelectorAll( '[data-bnw-panel]' ).forEach( function ( p ) {
			p.hidden = p.getAttribute( 'data-bnw-panel' ) !== t.getAttribute( 'data-bnw-tab' );
		} );
		return true;
	}

	/* 0.9.13: widget partita in diretta. Ricarica via REST all'apertura e, se la partita
	   e' in corso o sta per iniziare, ogni minuto; mantiene la scheda scelta. */
	function live( box ) {
		var url = box.getAttribute( 'data-bnw-url' ) + '?evento=' + encodeURIComponent( box.getAttribute( 'data-bnw-ev' ) );
		var timer = null;
		function refresh() {
			if ( document.hidden ) {
				return;
			}
			fetch( url, { credentials: 'omit' } ).then( function ( r ) {
				return r.ok ? r.json() : null;
			} ).then( function ( j ) {
				if ( ! j || ! j.html ) {
					return;
				}
				var cur = box.querySelector( '[data-bnw-tab][aria-selected="true"]' );
				var tab = cur ? cur.getAttribute( 'data-bnw-tab' ) : '';
				var tmp = document.createElement( 'div' );
				tmp.innerHTML = j.html;
				var inner = tmp.querySelector( '.bnw-live' );
				box.innerHTML = inner ? inner.innerHTML : j.html;
				var b = tab && box.querySelector( '[data-bnw-tab="' + tab + '"]' );
				if ( b ) {
					b.click();
				}
				if ( ! j.poll && timer ) {
					clearInterval( timer );
					timer = null;
				} else if ( j.poll && ! timer ) {
					timer = setInterval( refresh, 60000 );
				}
			} ).catch( function () {} );
		}
		refresh();
		if ( box.getAttribute( 'data-bnw-poll' ) === '1' ) {
			timer = setInterval( refresh, 60000 );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		if ( ! e.target || ! e.target.closest ) {
			return;
		}
		gruppo( e ) || linea( e ) || schede( e ) || wtabs( e );
	} );

	function start() {
		document.querySelectorAll( '.bnw-live[data-bnw-ev]' ).forEach( live );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
