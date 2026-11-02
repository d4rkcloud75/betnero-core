/* Betnero Core: ordine degli operatori trascinando le righe della lista. */
( function ( $ ) {
	$( function () {
		var body = $( '#the-list' );
		if ( ! body.length ) {
			return;
		}
		body.sortable( {
			handle: '.bnc-handle',
			axis: 'y',
			items: '> tr',
			helper: function ( e, tr ) {
				var orig = tr.children();
				var h = tr.clone();
				h.children().each( function ( i ) { $( this ).width( orig.eq( i ).width() ); } );
				return h;
			},
			update: function () {
				var ids = body.children( 'tr' ).map( function () {
					return ( this.id || '' ).replace( 'post-', '' );
				} ).get();
				body.addClass( 'bnc-saving' );
				$.post( BNC_ORDER.ajax, { action: 'bnc_save_order', nonce: BNC_ORDER.nonce, ids: ids } ).done( function () {
					body.children( 'tr' ).each( function ( i ) { $( this ).find( '.bnc-pos' ).text( ( i + 1 ) * 10 ); } );
				} ).always( function () {
					body.removeClass( 'bnc-saving' );
				} );
			}
		} );
	} );
} )( jQuery );
