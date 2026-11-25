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
	function start() {
		document.querySelectorAll( '[data-bnqc]' ).forEach( tabs );
		document.querySelectorAll( '[data-bnm]' ).forEach( init );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', start );
	} else {
		start();
	}
} )();
