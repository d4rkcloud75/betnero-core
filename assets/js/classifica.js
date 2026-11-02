/* Betnero Core: selettore Casino/Scommesse e filtri della classifica a schede. */
( function () {
	function init( box ) {
		var tabs = box.querySelectorAll( '.bnk-tab' );
		var lists = box.querySelectorAll( '[data-bnk-list]' );
		var title = box.querySelector( '.bnk-title' );
		var filters = box.querySelector( '[data-bnk-filters]' );
		var active = {};

		function show( type ) {
			tabs.forEach( function ( t ) {
				var on = t.getAttribute( 'data-tab' ) === type;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
			} );
			lists.forEach( function ( l ) {
				l.hidden = l.getAttribute( 'data-bnk-list' ) !== type;
			} );
			if ( title && title.getAttribute( 'data-' + type ) ) {
				title.textContent = title.getAttribute( 'data-' + type );
			}
			if ( filters ) {
				filters.hidden = type !== 'casino';
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
			filters.querySelectorAll( '.bnk-chip' ).forEach( function ( c ) {
				c.addEventListener( 'click', function () {
					var f = c.getAttribute( 'data-f' );
					active[ f ] = ! active[ f ];
					c.setAttribute( 'aria-pressed', active[ f ] ? 'true' : 'false' );
					var list = box.querySelector( '[data-bnk-list="casino"]' );
					var shown = 0;
					list.querySelectorAll( '.bnk-op' ).forEach( function ( op ) {
						var ok = Object.keys( active ).every( function ( k ) {
							return ! active[ k ] || op.getAttribute( 'data-' + k ) === '1';
						} );
						op.hidden = ! ok;
						shown += ok ? 1 : 0;
					} );
					var empty = list.querySelector( '.bnk-empty' );
					if ( empty ) {
						empty.hidden = shown > 0;
					}
				} );
			} );
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
