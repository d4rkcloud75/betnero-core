/* Betnero Core: editor dei blocchi (senza build). */
( function ( wp ) {
	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var ToggleControl = wp.components.ToggleControl;
	var RangeControl = wp.components.RangeControl;
	var SelectControl = wp.components.SelectControl;
	var TextControl = wp.components.TextControl;
	var ServerSideRender = wp.serverSideRender;

	function preview( name, attributes ) {
		var ed = wp.data && wp.data.select( 'core/editor' );
		var postId = ed && ed.getCurrentPostId ? ed.getCurrentPostId() : 0;
		return el( 'div', useBlockProps(), el( ServerSideRender, { block: name, attributes: attributes, urlQueryArgs: postId ? { post_id: postId } : {} } ) );
	}

	function set( props, key ) {
		return function ( v ) {
			var o = {};
			o[ key ] = v;
			props.setAttributes( o );
		};
	}

	wp.blocks.registerBlockType( 'betnero/tabella-casino', {
		title: 'Tabella casino',
		icon: 'list-view',
		category: 'betnero',
		description: 'Tabella dei casino (Operatori > Casino), con filtri.',
		edit: function ( props ) {
			var a = props.attributes;
			return [
				el( InspectorControls, { key: 'i' },
					el( PanelBody, { title: 'Impostazioni' },
						el( ToggleControl, { label: 'Mostra i filtri', checked: a.showFilters, onChange: set( props, 'showFilters' ) } ),
						el( ToggleControl, { label: 'Mostra il parere dell\'esperto', checked: a.showParere, onChange: set( props, 'showParere' ) } ),
						el( RangeControl, { label: 'Numero di casino (0 = tutti)', value: a.limit, min: 0, max: 30, onChange: function ( v ) { props.setAttributes( { limit: v || 0 } ); } } )
					)
				),
				el( 'div', { key: 'p' }, preview( 'betnero/tabella-casino', a ) )
			];
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'betnero/tabella-scommesse', {
		title: 'Tabella scommesse',
		icon: 'list-view',
		category: 'betnero',
		description: 'Tabella dei bookmaker (Operatori > Bookmaker).',
		edit: function ( props ) {
			var a = props.attributes;
			return [
				el( InspectorControls, { key: 'i' },
					el( PanelBody, { title: 'Impostazioni' },
						el( ToggleControl, { label: 'Mostra i filtri', checked: a.showFilters, onChange: set( props, 'showFilters' ) } ),
						el( RangeControl, { label: 'Numero di bookmaker (0 = tutti)', value: a.limit, min: 0, max: 30, onChange: function ( v ) { props.setAttributes( { limit: v || 0 } ); } } )
					)
				),
				el( 'div', { key: 'p' }, preview( 'betnero/tabella-scommesse', a ) )
			];
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'betnero/toplist', {
		title: 'Toplist compatta',
		icon: 'star-filled',
		category: 'betnero',
		description: 'Le prime posizioni di casino o bookmaker.',
		edit: function ( props ) {
			var a = props.attributes;
			return [
				el( InspectorControls, { key: 'i' },
					el( PanelBody, { title: 'Impostazioni' },
						el( SelectControl, { label: 'Tipo', value: a.type, options: [ { label: 'Casino', value: 'casino' }, { label: 'Scommesse', value: 'scommesse' } ], onChange: set( props, 'type' ) } ),
						el( SelectControl, { label: 'Aspetto', value: a.layout, options: [ { label: 'Nel testo (righe)', value: 'inline' }, { label: 'Sidebar (card)', value: 'side' } ], onChange: set( props, 'layout' ) } ),
						el( RangeControl, { label: 'Numero di operatori', value: a.limit, min: 1, max: 10, onChange: function ( v ) { props.setAttributes( { limit: v || 5 } ); } } ),
						el( TextControl, { label: 'Titolo (vuoto = automatico)', value: a.title, onChange: set( props, 'title' ) } )
					)
				),
				el( 'div', { key: 'p' }, preview( 'betnero/toplist', a ) )
			];
		},
		save: function () { return null; }
	} );

	function OperatorSelect( props ) {
		var state = useState( null );
		var ops = state[ 0 ];
		var setOps = state[ 1 ];
		useEffect( function () {
			wp.apiFetch( { path: '/wp/v2/bn-operatori?per_page=100&orderby=title&order=asc&_fields=id,title,bn_tipo' } ).then( setOps ).catch( function () { setOps( [] ); } );
		}, [] );
		var options = [ { label: 'Quello collegato a questa pagina', value: 0 } ];
		( ops || [] ).forEach( function ( o ) {
			options.push( { label: o.title.rendered + ( o.bn_tipo === 'scommesse' ? ' (bookmaker)' : ' (casino)' ), value: o.id } );
		} );
		return el( SelectControl, { label: 'Operatore', value: props.value, options: options, onChange: function ( v ) { props.onChange( parseInt( v, 10 ) || 0 ); } } );
	}

	wp.blocks.registerBlockType( 'betnero/scheda-operatore', {
		title: 'Scheda operatore',
		icon: 'id-alt',
		category: 'betnero',
		description: 'Testata della recensione (logo, bonus, voto, Vai al sito) e testi presi dall\'operatore.',
		edit: function ( props ) {
			var a = props.attributes;
			return [
				el( InspectorControls, { key: 'i' },
					el( PanelBody, { title: 'Impostazioni' },
						el( OperatorSelect, { value: a.operatore, onChange: set( props, 'operatore' ) } ),
						el( SelectControl, { label: 'Cosa mostrare', value: a.parte, options: [
							{ label: 'Testata e testi', value: 'tutto' },
							{ label: 'Solo testata', value: 'testata' },
							{ label: 'Solo testi', value: 'testi' }
						], onChange: set( props, 'parte' ) } )
					)
				),
				el( 'div', { key: 'p' }, preview( 'betnero/scheda-operatore', a ) )
			];
		},
		save: function () { return null; }
	} );
	wp.blocks.registerBlockType( 'betnero/classifica', {
		title: 'Classifica a schede',
		icon: 'awards',
		category: 'betnero',
		description: 'Casino e bookmaker in schede, con selettore Casino/Scommesse e filtri.',
		edit: function ( props ) {
			var a = props.attributes;
			return [
				el( InspectorControls, { key: 'i' },
					el( PanelBody, { title: 'Testi' },
						el( TextControl, { label: 'Etichetta sopra il titolo', value: a.eyebrow, onChange: set( props, 'eyebrow' ) } ),
						el( TextControl, { label: 'Titolo casino', value: a.titleCasino, onChange: set( props, 'titleCasino' ) } ),
						el( TextControl, { label: 'Titolo scommesse', value: a.titleSport, onChange: set( props, 'titleSport' ) } ),
						el( TextControl, { label: 'Descrizione', value: a.description, onChange: set( props, 'description' ) } )
					),
					el( PanelBody, { title: 'Impostazioni' },
						el( SelectControl, { label: 'Cosa mostrare', value: a.tipi, options: [ { label: 'Casino e scommesse (con selettore)', value: 'entrambi' }, { label: 'Solo casino', value: 'casino' }, { label: 'Solo scommesse', value: 'scommesse' } ], onChange: set( props, 'tipi' ) } ),
						el( ToggleControl, { label: 'Mostra i filtri', checked: a.showFilters, onChange: set( props, 'showFilters' ) } ),
						el( RangeControl, { label: 'Numero di casino (0 = tutti)', value: a.limitCasino, min: 0, max: 30, onChange: function ( v ) { props.setAttributes( { limitCasino: v || 0 } ); } } ),
						el( RangeControl, { label: 'Numero di bookmaker (0 = tutti)', value: a.limitSport, min: 0, max: 30, onChange: function ( v ) { props.setAttributes( { limitSport: v || 0 } ); } } )
					)
				),
				el( 'div', { key: 'p' }, preview( 'betnero/classifica', a ) )
			];
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'betnero/podio', {
		title: 'Podio operatori',
		icon: 'star-filled',
		category: 'betnero',
		description: 'I primi operatori in schede compatte, per la hero.',
		edit: function ( props ) {
			var a = props.attributes;
			return [
				el( InspectorControls, { key: 'i' },
					el( PanelBody, { title: 'Impostazioni' },
						el( SelectControl, { label: 'Tipo', value: a.type, options: [ { label: 'Casino', value: 'casino' }, { label: 'Scommesse', value: 'scommesse' } ], onChange: set( props, 'type' ) } ),
						el( RangeControl, { label: 'Quanti operatori', value: a.limit, min: 1, max: 5, onChange: function ( v ) { props.setAttributes( { limit: v || 3 } ); } } ),
						el( TextControl, { label: 'Titolo (vuoto = "Podio casinò di" + mese)', value: a.title, onChange: set( props, 'title' ) } )
					)
				),
				el( 'div', { key: 'p', style: { background: '#3C95D1', padding: '12px', borderRadius: '12px' } }, preview( 'betnero/podio', a ) )
			];
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'betnero/numeri', {
		title: 'Numeri del sito',
		icon: 'chart-bar',
		category: 'betnero',
		description: 'Operatori, recensioni, pronostici e guide: contati in automatico.',
		edit: function ( props ) {
			return el( 'div', { style: { background: '#3C95D1', padding: '12px', borderRadius: '12px' } }, preview( 'betnero/numeri', props.attributes ) );
		},
		save: function () { return null; }
	} );
} )( window.wp );
