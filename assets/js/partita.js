/* Betnero Core 0.9.5: pagina partita, filtri dei mercati e linee (Under/Over, handicap). */
( function () {
	function init( root ) {
		root.addEventListener( 'click', function ( e ) {
			var g = e.target.closest( '[data-bnm-group]' );
			if ( g ) {
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
				return;
			}
			var l = e.target.closest( '[data-bnm-line]' );
			if ( l ) {
				var mk = l.closest( '.bnm-mk' );
				var line = l.getAttribute( 'data-bnm-line' );
				mk.querySelectorAll( '[data-bnm-line]' ).forEach( function ( b ) {
					b.setAttribute( 'aria-pressed', b === l ? 'true' : 'false' );
				} );
				mk.querySelectorAll( '.bnm-line' ).forEach( function ( d ) {
					d.hidden = d.getAttribute( 'data-line' ) !== line;
				} );
			}
		} );
	}
	/* 0.9.10: schede Partite / Risultati / Classifica del blocco Quote campionato */
	function tabs( root ) {
		root.addEventListener( 'click', function ( e ) {
			var t = e.target.closest( '[data-bnqc-tab]' );
			if ( ! t ) {
				return;
			}
			root.querySelectorAll( '[data-bnqc-tab]' ).forEach( function ( b ) {
				b.setAttribute( 'aria-selected', b === t ? 'true' : 'false' );
			} );
			root.querySelectorAll( '.bnqc-panel' ).forEach( function ( p ) {
				p.hidden = p.id !== 'bnqc-p-' + t.getAttribute( 'data-bnqc-tab' );
			} );
		} );
	}
	/* 0.9.12: schede del widget partita (API-Football) */
	function wtabs( root ) {
		root.addEventListener( 'click', function ( e ) {
			var t = e.target.closest( '[data-bnw-tab]' );
			if ( ! t ) {
				return;
			}
			root.querySelectorAll( '[data-bnw-tab]' ).forEach( function ( b ) {
				b.setAttribute( 'aria-selected', b === t ? 'true' : 'false' );
			} );
			root.querySelectorAll( '[data-bnw-panel]' ).forEach( function ( p ) {
				p.hidden = p.getAttribute( 'data-bnw-panel' ) !== t.getAttribute( 'data-bnw-tab' );
			} );
		} );
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
				var sec = box.querySelector( '[data-bnw]' );
				if ( sec ) {
					wtabs( sec );
					var b = tab && sec.querySelector( '[data-bnw-tab="' + tab + '"]' );
					if ( b ) {
						b.click();
					}
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
	function start() {
		document.querySelectorAll( '.bnw-live[data-bnw-ev]' ).forEach( live );
		document.querySelectorAll( '[data-bnw]' ).forEach( wtabs );
		document.querySelectorAll( '[data-bnqc]' ).forEach( tabs );
		document.querySelectorAll( '[data-bnm]' ).forEach( init );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
