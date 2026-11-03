/*
 * Betnero Core: selettore Casino/Scommesse e filtri della classifica a schede.
 * Animazioni: la pillola del selettore scivola sull'opzione scelta, la lista
 * cambia con una dissolvenza e le schede entrano una dopo l'altra; i filtri
 * fanno sparire e ricomparire le schede con una dissolvenza.
 * Con "riduci movimento" attivo nel sistema tutto cambia senza animazioni.
 */
( function () {
	var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	var EASE = 'cubic-bezier(.2,.7,.2,1)';
	var canAnimate = ! reduce && typeof Element.prototype.animate === 'function';

	function anim( el, frames, opts ) {
		return canAnimate ? el.animate( frames, opts ) : null;
	}

	function cardsIn( cards ) {
		cards.forEach( function ( c, i ) {
			anim( c, [ { opacity: 0, transform: 'translateY(14px)' }, { opacity: 1, transform: 'none' } ], { duration: 320, delay: Math.min( i, 8 ) * 45, easing: EASE, fill: 'backwards' } );
		} );
	}

	function visibleCards( list ) {
		return Array.prototype.filter.call( list.querySelectorAll( '.bnk-op' ), function ( c ) {
			return ! c.hidden;
		} );
	}

	function init( box ) {
		var tabsWrap = box.querySelector( '.bnk-tabs' );
		var tabs = box.querySelectorAll( '.bnk-tab' );
		var lists = box.querySelectorAll( '[data-bnk-list]' );
		var title = box.querySelector( '.bnk-title' );
		var filters = box.querySelector( '[data-bnk-filters]' );
		var active = {};
		var firstList = box.querySelector( '[data-bnk-list]:not([hidden])' );
		var current = firstList ? firstList.getAttribute( 'data-bnk-list' ) : 'casino';
		var busy = null;

		// Pillola che scivola sotto l'opzione scelta (solo se c'e' il selettore).
		var ind = null;
		function placeInd( instant ) {
			if ( ! ind ) {
				return;
			}
			var sel = tabsWrap.querySelector( '.bnk-tab[aria-selected="true"]' );
			if ( ! sel ) {
				return;
			}
			if ( instant ) {
				ind.style.transition = 'none';
			}
			ind.style.width = sel.offsetWidth + 'px';
			ind.style.transform = 'translateX(' + sel.offsetLeft + 'px)';
			if ( instant ) {
				ind.getBoundingClientRect();
				ind.style.transition = '';
			}
		}
		if ( tabsWrap ) {
			ind = document.createElement( 'span' );
			ind.className = 'bnk-ind';
			ind.setAttribute( 'aria-hidden', 'true' );
			tabsWrap.insertBefore( ind, tabsWrap.firstChild );
			tabsWrap.classList.add( 'bnk-has-ind' );
			placeInd( true );
			window.addEventListener( 'resize', function () {
				placeInd( true );
			} );
			if ( document.fonts && document.fonts.ready ) {
				document.fonts.ready.then( function () {
					placeInd( true );
				} );
			}
		}

		function listOf( type ) {
			return box.querySelector( '[data-bnk-list="' + type + '"]' );
		}

		function show( type ) {
			if ( type === current ) {
				return;
			}
			tabs.forEach( function ( t ) {
				var on = t.getAttribute( 'data-tab' ) === type;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
			} );
			placeInd( false );

			var from = listOf( current );
			var to = listOf( type );
			current = type;

			function swap() {
				lists.forEach( function ( l ) {
					l.hidden = l !== to;
				} );
				if ( title && title.getAnimations ) {
					title.getAnimations().forEach( function ( x ) {
						x.cancel();
					} );
				}
				if ( title && title.getAttribute( 'data-' + type ) ) {
					title.textContent = title.getAttribute( 'data-' + type );
					anim( title, [ { opacity: 0, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' } ], { duration: 260, easing: EASE } );
				}
				if ( filters ) {
					filters.hidden = type !== 'casino';
				}
				cardsIn( visibleCards( to ) );
			}

			if ( busy ) {
				busy.cancel();
				busy = null;
			}
			var out = anim( from, [ { opacity: 1, transform: 'none' }, { opacity: 0, transform: 'translateY(8px)' } ], { duration: 170, easing: 'ease-in' } );
			if ( title ) {
				anim( title, [ { opacity: 1 }, { opacity: 0 } ], { duration: 170, easing: 'ease-in', fill: 'forwards' } );
			}
			if ( out ) {
				busy = out;
				out.onfinish = function () {
					busy = null;
					swap();
				};
				out.oncancel = function () {
					if ( title ) {
						title.getAnimations().forEach( function ( a ) {
							a.cancel();
						} );
					}
				};
			} else {
				swap();
			}
		}

		tabs.forEach( function ( t, i ) {
			t.addEventListener( 'click', function () {
				show( t.getAttribute( 'data-tab' ) );
			} );
			t.addEventListener( 'keydown', function ( e ) {
				if ( e.key !== 'ArrowRight' && e.key !== 'ArrowLeft' ) {
					return;
				}
				var next = tabs[ ( i + ( e.key === 'ArrowRight' ? 1 : tabs.length - 1 ) ) % tabs.length ];
				next.focus();
				show( next.getAttribute( 'data-tab' ) );
			} );
		} );

		if ( filters ) {
			var selects = filters.querySelectorAll( 'select[data-fs]' );
			var reset = filters.querySelector( '[data-bnk-reset]' );

			function matches( op ) {
				var ok = Object.keys( active ).every( function ( k ) {
					return ! active[ k ] || op.getAttribute( 'data-' + k ) === '1';
				} );
				Array.prototype.forEach.call( selects, function ( sel ) {
					if ( ok && sel.value ) {
						var tokens = ( op.getAttribute( 'data-' + sel.getAttribute( 'data-fs' ) ) || '' ).split( ' ' );
						ok = tokens.indexOf( sel.value ) !== -1;
					}
				} );
				return ok;
			}

			function anyActive() {
				return Object.keys( active ).some( function ( k ) {
					return active[ k ];
				} ) || Array.prototype.some.call( selects, function ( sel ) {
					return !! sel.value;
				} );
			}

			function apply() {
				var list = listOf( 'casino' );
				var leaving = [];
				var entering = [];
				var shown = 0;
				list.querySelectorAll( '.bnk-op' ).forEach( function ( op ) {
					var ok = matches( op );
					shown += ok ? 1 : 0;
					if ( ok && op.hidden ) {
						entering.push( op );
					} else if ( ! ok && ! op.hidden ) {
						leaving.push( op );
					}
				} );
				var empty = list.querySelector( '.bnk-empty' );
				if ( reset ) {
					reset.hidden = ! anyActive();
				}
				Array.prototype.forEach.call( selects, function ( sel ) {
					sel.parentNode.classList.toggle( 'is-active', !! sel.value );
				} );

				function finish() {
					leaving.forEach( function ( op ) {
						op.hidden = true;
						op.getAnimations && op.getAnimations().forEach( function ( a ) {
							a.cancel();
						} );
					} );
					entering.forEach( function ( op ) {
						op.hidden = false;
					} );
					if ( empty ) {
						empty.hidden = shown > 0;
						if ( ! shown ) {
							anim( empty, [ { opacity: 0 }, { opacity: 1 } ], { duration: 250 } );
						}
					}
					cardsIn( entering );
				}

				var last = null;
				leaving.forEach( function ( op ) {
					last = anim( op, [ { opacity: 1, transform: 'none' }, { opacity: 0, transform: 'scale(.98)' } ], { duration: 180, easing: 'ease-in', fill: 'forwards' } ) || last;
				} );
				if ( last ) {
					last.onfinish = finish;
				} else {
					finish();
				}
			}

			filters.querySelectorAll( '.bnk-chip' ).forEach( function ( c ) {
				c.addEventListener( 'click', function () {
					var f = c.getAttribute( 'data-f' );
					active[ f ] = ! active[ f ];
					c.setAttribute( 'aria-pressed', active[ f ] ? 'true' : 'false' );
					anim( c, [ { transform: 'scale(.94)' }, { transform: 'scale(1)' } ], { duration: 200, easing: EASE } );
					apply();
				} );
			} );
			Array.prototype.forEach.call( selects, function ( sel ) {
				sel.addEventListener( 'change', apply );
			} );
			if ( reset ) {
				reset.addEventListener( 'click', function () {
					Object.keys( active ).forEach( function ( k ) {
						active[ k ] = false;
					} );
					filters.querySelectorAll( '.bnk-chip' ).forEach( function ( c ) {
						c.setAttribute( 'aria-pressed', 'false' );
					} );
					Array.prototype.forEach.call( selects, function ( sel ) {
						sel.value = '';
					} );
					apply();
				} );
			}
		}
	}

	function boot() {
		document.querySelectorAll( '[data-bnk]' ).forEach( init );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
