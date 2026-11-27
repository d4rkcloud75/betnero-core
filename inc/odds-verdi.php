<?php
/**
 * Blocco "Quote maggiorate e più alte" (0.9.30): i due box in uno solo, con la testata verde.
 *
 * Non riscrive niente: chiama bnc_boost_render() (inc/odds-boost.php) e bnc_top_render()
 * (inc/odds-top.php) e li mette dentro un box solo. Se non c'è né una quota maggiorata
 * valida né una quota sopra la media, il blocco non stampa niente sul sito (nell'editor un avviso).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'betnero/quote-verdi', array(
		'title'           => 'Quote maggiorate e più alte',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-quote',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false ),
		'attributes'      => array(
			'giorni'     => array( 'type' => 'number', 'default' => 7 ),
			'limite'     => array( 'type' => 'number', 'default' => 5 ),
			'campionato' => array( 'type' => 'string', 'default' => '' ),
			'titolo'     => array( 'type' => 'string', 'default' => '' ),
			'className'  => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_verdi_block',
	) );
}, 25 );

function bnc_verdi_attrs( $a ) {
	$a = is_array( $a ) ? $a : array();
	return array(
		'giorni'     => max( 1, min( 7, isset( $a['giorni'] ) ? (int) $a['giorni'] : 7 ) ),
		'limite'     => max( 1, min( 15, isset( $a['limite'] ) ? (int) $a['limite'] : 5 ) ),
		'campionato' => isset( $a['campionato'] ) ? sanitize_title( $a['campionato'] ) : '',
		'titolo'     => isset( $a['titolo'] ) ? sanitize_text_field( $a['titolo'] ) : '',
		'label'      => isset( $a['label'] ) ? sanitize_text_field( $a['label'] ) : '',
	);
}

/** Nome del campionato per il titolo delle quote più alte, se non arriva già. */
function bnc_verdi_label( $slug, $label ) {
	if ( '' !== $label || '' === $slug ) {
		return $label;
	}
	global $wpdb;
	$t  = bnc_odds_table();
	$ln = (string) $wpdb->get_var( $wpdb->prepare( "SELECT league_name FROM {$t} WHERE league_slug = %s ORDER BY start DESC LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB
	return '' !== $ln ? bnc_odds_league_label( $ln ) : '';
}

function bnc_verdi_render( $a ) {
	$a     = bnc_verdi_attrs( $a );
	$label = bnc_verdi_label( $a['campionato'], $a['label'] );
	$boost = bnc_boost_render( bnc_boost_active( 0, $a['limite'] ), 'Quote maggiorate' );
	$top   = bnc_top_render( array(
		'giorni'     => $a['giorni'],
		'limite'     => $a['limite'],
		'campionato' => $a['campionato'],
		'titolo'     => '' !== $label ? 'Le quote più alte della ' . $label : '',
	) );
	if ( '' === $boost && '' === $top ) {
		return '';
	}
	$titolo = '' !== $a['titolo'] ? $a['titolo'] : 'Quote maggiorate e quote più alte';
	$h      = '<div class="bnq bnq-verdi"><p class="bnq-head is-green">' . esc_html( $titolo ) . '</p>';
	$h     .= $boost . $top;
	return $h . '<p class="bnq-adm">18+ | Gioca responsabilmente | Offerte dei bookmaker e quote variabili, alle loro condizioni</p></div>';
}

function bnc_verdi_block( $a ) {
	$a     = bnc_verdi_attrs( $a );
	$inner = bnc_verdi_render( $a );
	if ( '' === $inner ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="bnq-empty">Né quote maggiorate valide (Operatori &gt; Quote maggiorate) né quote sopra la media per queste partite: il blocco resta vuoto anche sul sito.</p>';
		}
		return '';
	}
	return $inner;
}
