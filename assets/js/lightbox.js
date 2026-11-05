/*
 * Betnero Core: lightbox per le gallerie delle recensioni (Il sito web, App).
 * Un clic sulla foto la apre ingrandita sopra la pagina; frecce e tastiera
 * per passare alle altre foto della stessa galleria; Esc o clic fuori per chiudere.
 * Senza JavaScript il link apre comunque l'immagine.
 */
( function () {
	var box, img, cap, prev, next, list = [], idx = 0, last = null;

	function build() {
		box = document.createElement( 'div' );
		box.className = 'bn-lb';
		box.setAttribute( 'role', 'dialog' );
		box.setAttribute( 'aria-modal', 'true' );
		box.setAttribute( 'aria-label', 'Immagine ingrandita' );
		box.hidden = true;
		box.innerHTML = '<button type="button" class="bn-lb-close" aria-label="Chiudi">&times;</button>' +
			'<button type="button" class="bn-lb-prev" aria-label="Immagine precedente">&#8249;</button>' +
			'<figure class="bn-lb-fig"><img alt=""><figcaption></figcaption></figure>' +
			'<button type="button" class="bn-lb-next" aria-label="Immagine successiva">&#8250;</button>';
		document.body.appendChild( box );
		img = box.querySelector( 'img' );
		cap = box.querySelector( 'figcaption' );
		prev = box.querySelector( '.bn-lb-prev' );
		next = box.querySelector( '.bn-lb-next' );
		box.querySelector( '.bn-lb-close' ).addEventListener( 'click', close );
		prev.addEventListener( 'click', function ( e ) { e.stopPropagation(); go( -1 ); } );
		next.addEventListener( 'click', function ( e ) { e.stopPropagation(); go( 1 ); } );
		box.addEventListener( 'click', function ( e ) {
			if ( e.target === box || e.target.classList.contains( 'bn-lb-fig' ) ) {
				close();
			}
		} );
		document.addEventListener( 'keydown', function ( e ) {
			if ( box.hidden ) {
				return;
			}
			if ( e.key === 'Escape' ) { close(); }
			if ( e.key === 'ArrowLeft' ) { go( -1 ); }
			if ( e.key === 'ArrowRight' ) { go( 1 ); }
		} );
		var x0 = null;
		box.addEventListener( 'touchstart', function ( e ) { x0 = e.touches[ 0 ].clientX; }, { passive: true } );
		box.addEventListener( 'touchend', function ( e ) {
			if ( x0 === null ) { return; }
			var dx = e.changedTouches[ 0 ].clientX - x0;
			if ( Math.abs( dx ) > 40 ) { go( dx < 0 ? 1 : -1 ); }
			x0 = null;
		} );
	}

	function show() {
		var a = list[ idx ];
		var t = a.querySelector( 'img' );
		img.src = a.href;
		img.alt = t ? t.alt : '';
		cap.textContent = list.length > 1 ? ( idx + 1 ) + ' / ' + list.length : '';
		prev.hidden = next.hidden = list.length < 2;
	}

	function go( d ) {
		idx = ( idx + d + list.length ) % list.length;
		show();
	}

	function open( a ) {
		if ( ! box ) {
			build();
		}
		var grid = a.closest( '.bns-gallery-grid' );
		list = Array.prototype.slice.call( grid.querySelectorAll( 'a[data-bn-lb]' ) );
		idx = list.indexOf( a );
		last = a;
		show();
		box.hidden = false;
		document.documentElement.classList.add( 'bn-lb-open' );
		box.querySelector( '.bn-lb-close' ).focus();
	}

	function close() {
		box.hidden = true;
		img.removeAttribute( 'src' );
		document.documentElement.classList.remove( 'bn-lb-open' );
		if ( last ) {
			last.focus();
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var a = e.target.closest && e.target.closest( 'a[data-bn-lb]' );
		if ( ! a || e.ctrlKey || e.metaKey || e.shiftKey ) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();
		open( a );
	}, true );
} )();
