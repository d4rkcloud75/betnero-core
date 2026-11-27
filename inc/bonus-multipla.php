<?php
/**
 * Blocco "Tabella bonus multiple" (0.9.28).
 *
 * Tabella comparativa separata e integrativa a quella dei bookmaker: mostra solo
 * chi ha compilato il campo "Bonus massimo" della sezione "Bonus multipla" in
 * Operatori, nello stesso ordine della lista (Posizione). I dati si inseriscono
 * a mano, qui non ci sono valori di esempio.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'betnero/tabella-bonus-multiple', array(
		'title'           => 'Tabella bonus multiple',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-tables',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
		'attributes'      => array(
			'titolo'    => array( 'type' => 'string', 'default' => 'Bonus multipla' ),
			'align'     => array( 'type' => 'string' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_bonus_multiple_render',
	) );
}, 21 );

/**
 * Bookmaker con il bonus multipla compilato, nell'ordine di Posizione.
 *
 * @return array[]
 */
function bnc_bonus_multiple_rows() {
	$rows = array();
	foreach ( bnc_operators( 'scommesse', false ) as $op ) {
		$max = trim( (string) bnc_get( $op['id'], 'multipla_massimo' ) );
		if ( '' === $max ) {
			continue;
		}
		$rows[] = array(
			'op'    => $op,
			'ev'    => trim( (string) bnc_get( $op['id'], 'multipla_eventi' ) ),
			'quota' => trim( (string) bnc_get( $op['id'], 'multipla_quota' ) ),
			'ini'   => trim( (string) bnc_get( $op['id'], 'multipla_iniziale' ) ),
			'max'   => $max,
			'live'  => trim( (string) bnc_get( $op['id'], 'multipla_live' ) ),
			'liven' => trim( (string) bnc_get( $op['id'], 'multipla_live_nota' ) ),
			'nota'  => trim( (string) bnc_get( $op['id'], 'multipla_nota' ) ),
		);
	}
	return $rows;
}

/** Una voce della riga: etichetta (visibile solo da mobile) e valore. */
function bnc_bonus_multiple_cell( $label, $html ) {
	return '<div class="bntm-c"><span class="bntm-k">' . esc_html( $label ) . '</span><span class="bntm-v">' . $html . '</span></div>';
}

function bnc_bonus_multiple_render( $attrs ) {
	$attrs = is_array( $attrs ) ? $attrs : array();
	$rows  = bnc_bonus_multiple_rows();
	if ( ! $rows ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="bnt-empty">Compila la sezione "Bonus multipla" in Operatori.</p>';
		}
		return '';
	}
	wp_enqueue_style( 'betnero-tables' );

	$titolo = isset( $attrs['titolo'] ) ? trim( (string) $attrs['titolo'] ) : 'Bonus multipla';
	$cols   = array( 'Eventi minimi', 'Quota minima', 'Bonus iniziale', 'Bonus massimo', 'Live', 'Caratteristica' );
	$h      = '<div class="bntm">';
	if ( '' !== $titolo ) {
		$h .= '<p class="bntm-title">' . esc_html( $titolo ) . '</p>';
	}
	$h .= '<div class="bntm-cols" aria-hidden="true"><span>Bookmaker</span>';
	foreach ( $cols as $c ) {
		$h .= '<span>' . esc_html( $c ) . '</span>';
	}
	$h .= '<span></span></div>';
	foreach ( $rows as $r ) {
		$live = bnc_yn( $r['live'] );
		if ( '' !== $r['liven'] ) {
			$live .= '<span class="bntm-n">' . esc_html( $r['liven'] ) . '</span>';
		}
		$h .= '<div class="bntm-row"><div class="bntm-logo">' . bnc_logo( $r['op'] ) . '</div>';
		$h .= bnc_bonus_multiple_cell( 'Eventi minimi', bnc_yn( $r['ev'] ) );
		$h .= bnc_bonus_multiple_cell( 'Quota minima', bnc_yn( $r['quota'] ) );
		$h .= bnc_bonus_multiple_cell( 'Bonus iniziale', bnc_yn( $r['ini'] ) );
		$h .= bnc_bonus_multiple_cell( 'Bonus massimo', '<strong>' . bnc_yn( $r['max'] ) . '</strong>' );
		$h .= bnc_bonus_multiple_cell( 'Live', $live );
		$h .= bnc_bonus_multiple_cell( 'Caratteristica', bnc_yn( $r['nota'] ) );
		$h .= '<div class="bntm-go">' . bnc_go_button( $r['op'] ) . '</div></div>';
	}
	return $h . bnc_adm_line() . '</div>';
}
