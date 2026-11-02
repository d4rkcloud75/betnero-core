<?php
/**
 * Blocchi Betnero.
 *
 *   betnero/tabella-casino     tabella completa con filtri
 *   betnero/tabella-scommesse  tabella bookmaker
 *   betnero/toplist            versione compatta (nel testo o in sidebar)
 *   betnero/scheda-operatore   testata e testi delle pagine recensione
 *
 * Stessi nomi dei blocchi del tema: le pagine gia' fatte non cambiano.
 * Il tema, se trova questo plugin, non registra i suoi.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	$v = BETNERO_CORE_VERSION;
	wp_register_style( 'betnero-tables', BETNERO_CORE_URL . 'assets/css/tables.css', array(), $v );
	wp_register_style( 'betnero-scheda', BETNERO_CORE_URL . 'assets/css/scheda.css', array( 'betnero-tables' ), $v );
	wp_register_style( 'betnero-bar', BETNERO_CORE_URL . 'assets/css/bar.css', array(), $v );
	wp_register_style( 'betnero-faq', BETNERO_CORE_URL . 'assets/css/faq.css', array(), $v );
	wp_register_script( 'betnero-tables', BETNERO_CORE_URL . 'assets/js/tables.js', array(), $v, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_register_script( 'betnero-bar', BETNERO_CORE_URL . 'assets/js/bar.js', array(), $v, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	wp_register_script(
		'betnero-blocks-editor',
		BETNERO_CORE_URL . 'assets/js/blocks-editor.js',
		array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-api-fetch', 'wp-data' ),
		$v,
		true
	);

	$common = array(
		'editor_script' => 'betnero-blocks-editor',
		'style'         => 'betnero-tables',
		'category'      => 'betnero',
		'supports'      => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
	);

	register_block_type( 'betnero/tabella-casino', $common + array(
		'title'           => 'Tabella casino',
		'attributes'      => array(
			'showFilters' => array( 'type' => 'boolean', 'default' => true ),
			'showParere'  => array( 'type' => 'boolean', 'default' => true ),
			'limit'       => array( 'type' => 'number', 'default' => 0 ),
			'align'       => array( 'type' => 'string' ),
			'className'   => array( 'type' => 'string' ),
		),
		'render_callback' => function ( $attributes ) {
			return bnc_render_table( 'casino', $attributes );
		},
	) );

	register_block_type( 'betnero/tabella-scommesse', $common + array(
		'title'           => 'Tabella scommesse',
		'attributes'      => array(
			'showFilters' => array( 'type' => 'boolean', 'default' => false ),
			'limit'       => array( 'type' => 'number', 'default' => 0 ),
			'align'       => array( 'type' => 'string' ),
			'className'   => array( 'type' => 'string' ),
		),
		'render_callback' => function ( $attributes ) {
			return bnc_render_table( 'scommesse', $attributes );
		},
	) );

	register_block_type( 'betnero/toplist', $common + array(
		'title'           => 'Toplist compatta',
		'attributes'      => array(
			'type'      => array( 'type' => 'string', 'default' => 'casino' ),
			'limit'     => array( 'type' => 'number', 'default' => 5 ),
			'title'     => array( 'type' => 'string', 'default' => '' ),
			'layout'    => array( 'type' => 'string', 'default' => 'inline' ),
			'align'     => array( 'type' => 'string' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_render_toplist',
	) );

	register_block_type( 'betnero/scheda-operatore', array(
		'title'           => 'Scheda operatore',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-scheda',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
		'attributes'      => array(
			'operatore' => array( 'type' => 'number', 'default' => 0 ),
			'parte'     => array( 'type' => 'string', 'default' => 'tutto' ),
			'align'     => array( 'type' => 'string' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_render_scheda',
	) );

	/* Accordion: stile "FAQ Betnero" per il blocco Dettagli di WordPress (sostituisce JetTabs) */
	register_block_style( 'core/details', array(
		'name'         => 'bn-faq',
		'label'        => 'FAQ Betnero',
		'style_handle' => 'betnero-faq',
	) );
} );

add_filter( 'block_categories_all', function ( $cats ) {
	foreach ( $cats as $c ) {
		if ( 'betnero' === $c['slug'] ) {
			return $cats;
		}
	}
	array_unshift( $cats, array( 'slug' => 'betnero', 'title' => 'Betnero' ) );
	return $cats;
} );

/**
 * Dati FAQ strutturati (schema.org FAQPage) dai blocchi Dettagli con stile
 * "FAQ Betnero", solo se nella pagina non ci sono gia' FAQ di Yoast.
 */
add_action( 'wp_footer', function () {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post || false === strpos( $post->post_content, 'is-style-bn-faq' ) || has_block( 'yoast/faq-block', $post ) ) {
		return;
	}
	$items = array();
	foreach ( bnc_flatten_blocks( parse_blocks( $post->post_content ) ) as $b ) {
		if ( 'core/details' !== $b['blockName'] || false === strpos( isset( $b['attrs']['className'] ) ? $b['attrs']['className'] : '', 'is-style-bn-faq' ) ) {
			continue;
		}
		$html = render_block( $b );
		if ( ! preg_match( '#<summary[^>]*>(.*?)</summary>(.*)</details>#is', $html, $m ) ) {
			continue;
		}
		$q = trim( wp_strip_all_tags( $m[1] ) );
		$a = trim( wp_strip_all_tags( $m[2] ) );
		if ( $q && $a ) {
			$items[] = array( '@type' => 'Question', 'name' => $q, 'acceptedAnswer' => array( '@type' => 'Answer', 'text' => $a ) );
		}
	}
	if ( $items ) {
		echo '<script type="application/ld+json">' . wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'FAQPage', 'mainEntity' => $items ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
	}
} );

function bnc_flatten_blocks( $blocks ) {
	$out = array();
	foreach ( $blocks as $b ) {
		$out[] = $b;
		if ( ! empty( $b['innerBlocks'] ) ) {
			$out = array_merge( $out, bnc_flatten_blocks( $b['innerBlocks'] ) );
		}
	}
	return $out;
}
