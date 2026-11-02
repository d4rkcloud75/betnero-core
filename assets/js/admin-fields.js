/* Betnero Core: campi dell'operatore (immagini e campi per tipo). */
( function ( $ ) {
	function toggleTipo() {
		var tipo = $( 'input[name="bnc_tipo"]:checked' ).val() || 'casino';
		$( '.bnc-field' ).each( function () {
			var tipi = ( $( this ).data( 'tipo' ) || '' ).split( ' ' );
			$( this ).toggle( tipi.indexOf( tipo ) !== -1 );
		} );
	}
	$( document ).on( 'change', 'input[name="bnc_tipo"]', toggleTipo );
	$( toggleTipo );

	$( document ).on( 'click', '.bnc-media-pick', function ( e ) {
		e.preventDefault();
		var box = $( this ).closest( '.bnc-media' );
		var multiple = box.data( 'multiple' ) === 1 || box.data( 'multiple' ) === '1';
		var input = box.find( 'input[type=hidden]' );
		var frame = wp.media( { title: multiple ? 'Scegli le immagini' : 'Scegli il logo', multiple: multiple ? 'add' : false, library: { type: 'image' } } );
		frame.on( 'open', function () {
			var sel = frame.state().get( 'selection' );
			( input.val() || '' ).split( ',' ).forEach( function ( id ) {
				if ( id ) {
					var a = wp.media.attachment( id );
					a.fetch();
					sel.add( a );
				}
			} );
		} );
		frame.on( 'select', function () {
			var ids = [];
			var html = '';
			frame.state().get( 'selection' ).each( function ( a ) {
				var j = a.toJSON();
				ids.push( j.id );
				var url = ( j.sizes && ( j.sizes.thumbnail || j.sizes.medium ) || j ).url;
				html += '<img src="' + url + '" style="' + ( multiple ? 'width:70px;height:70px;object-fit:cover;margin:0 6px 6px 0' : 'max-width:200px;height:auto' ) + '">';
			} );
			input.val( ids.join( ',' ) );
			box.find( '.bnc-media-preview' ).html( html );
		} );
		frame.open();
	} );

	$( document ).on( 'click', '.bnc-media-clear', function ( e ) {
		e.preventDefault();
		var box = $( this ).closest( '.bnc-media' );
		box.find( 'input[type=hidden]' ).val( '' );
		box.find( '.bnc-media-preview' ).empty();
	} );
} )( jQuery );
