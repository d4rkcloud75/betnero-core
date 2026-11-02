<?php
/**
 * Lettura degli operatori per tabelle, toplist, schede e barra.
 *
 * Fonte: gli "Operatori" di questo plugin. Finche' non ci sono operatori
 * pubblicati (prima della migrazione) si usano ancora le query di JetEngine,
 * cosi' il plugin si puo' attivare senza che le tabelle spariscano.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BNC_QUERY_CASINO    = 2;
const BNC_QUERY_SCOMMESSE = 12;

/**
 * Operatori di un tipo, nell'ordine delle tabelle.
 *
 * @param string $type casino|scommesse.
 * @param bool   $only_table Solo quelli con "Mostra in tabelle" attivo.
 * @return array[]
 */
function bnc_operators( $type, $only_table = true ) {
	static $cache = array();
	$type = 'scommesse' === $type ? 'scommesse' : 'casino';
	$ck   = $type . ( $only_table ? '1' : '0' );
	if ( isset( $cache[ $ck ] ) ) {
		return $cache[ $ck ];
	}
	$posts = get_posts( array(
		'post_type'        => BNC_POST_TYPE,
		'post_status'      => 'publish',
		'numberposts'      => 100,
		'orderby'          => array( 'menu_order' => 'ASC', 'title' => 'ASC' ),
		'meta_key'         => '_bn_tipo', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'       => $type, // phpcs:ignore WordPress.DB.SlowDBQuery
		'suppress_filters' => false,
	) );
	if ( empty( $posts ) && ! bnc_has_any_operator() ) {
		$cache[ $ck ] = bnc_operators_from_jetengine( $type );
		return $cache[ $ck ];
	}
	$ops = array();
	foreach ( $posts as $p ) {
		if ( $only_table && '1' !== (string) bnc_get( $p->ID, 'in_tabella' ) ) {
			continue;
		}
		$ops[] = bnc_operator( $p->ID );
	}
	$cache[ $ck ] = $ops;
	return $ops;
}

function bnc_has_any_operator() {
	static $has = null;
	if ( null === $has ) {
		$has = (bool) get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids' ) );
	}
	return $has;
}

/**
 * Un operatore in forma normalizzata.
 */
function bnc_operator( $post_id ) {
	$post_id = (int) $post_id;
	$tipo    = bnc_tipo( $post_id );
	$logo_id = (int) bnc_get( $post_id, 'logo' );
	$review  = (int) bnc_get( $post_id, 'review_page' );
	$review_url = $review ? get_permalink( $review ) : (string) bnc_get( $post_id, 'review_url' );
	return array(
		'id'         => $post_id,
		'tipo'       => $tipo,
		'name'       => get_the_title( $post_id ),
		'logo_id'    => $logo_id,
		'logo_url'   => $logo_id ? (string) wp_get_attachment_image_url( $logo_id, 'medium' ) : (string) get_post_meta( $post_id, '_bn_logo_src', true ),
		'bonus'      => (string) bnc_get( $post_id, 'bonus' ),
		'nd'         => (string) bnc_get( $post_id, 'senza_deposito' ),
		'spid'       => (string) bnc_get( $post_id, 'spid' ),
		'multipla'   => (string) bnc_get( $post_id, 'multipla' ),
		'rating'     => (string) bnc_get( $post_id, 'rating' ),
		'go_url'     => (string) bnc_get( $post_id, 'link' ),
		'go_blank'   => '1' === (string) bnc_get( $post_id, 'link_blank' ),
		'review_url' => $review_url ? $review_url : '',
		'review_id'  => $review,
		'parere'     => (string) bnc_get( $post_id, 'parere' ),
		'welcome'    => bnc_get( $post_id, 'welcome' ),
		'tipologia'  => bnc_get( $post_id, 'tipologia' ),
		'pagamenti'  => bnc_get( $post_id, 'pagamenti' ),
		'collegato'  => (int) bnc_get( $post_id, 'collegato' ),
	);
}

/* ---------- Ripiego su JetEngine (solo prima della migrazione) ---------- */

function bnc_jet_query_items( $query_id ) {
	if ( ! class_exists( '\Jet_Engine\Query_Builder\Manager' ) ) {
		return array();
	}
	$query = \Jet_Engine\Query_Builder\Manager::instance()->get_query_by_id( $query_id );
	return $query ? (array) $query->get_items() : array();
}

function bnc_item_value( $item, $key ) {
	if ( is_object( $item ) && isset( $item->$key ) ) {
		return $item->$key;
	}
	if ( is_array( $item ) && isset( $item[ $key ] ) ) {
		return $item[ $key ];
	}
	return '';
}

/**
 * Valori spuntati di una checkbox JetEngine (array chiave => "true"/"false",
 * serializzato o JSON).
 *
 * @return string[]
 */
function bnc_parse_checks( $v ) {
	if ( is_string( $v ) ) {
		$u = maybe_unserialize( $v );
		if ( is_string( $u ) ) {
			$j = json_decode( $u, true );
			$u = is_array( $j ) ? $j : ( '' === $u ? array() : array( $u ) );
		}
		$v = $u;
	}
	if ( ! is_array( $v ) ) {
		return array();
	}
	$out = array();
	foreach ( $v as $k => $val ) {
		if ( is_int( $k ) && ! is_bool( $val ) && 'true' !== $val && 'false' !== $val ) {
			$out[] = (string) $val;
		} elseif ( true === $val || 'true' === $val || 1 === $val || '1' === $val ) {
			$out[] = (string) $k;
		}
	}
	return $out;
}

/** Primo href di un pezzo di HTML. */
function bnc_html_href( $html ) {
	return preg_match( '/<a\s[^>]*href=["\']([^"\']+)["\']/i', (string) $html, $m ) ? trim( html_entity_decode( $m[1] ) ) : '';
}

/** Primo src di immagine di un pezzo di HTML. */
function bnc_html_img( $html ) {
	return preg_match( '/<img\s[^>]*src=["\']([^"\']+)["\']/i', (string) $html, $m ) ? trim( html_entity_decode( $m[1] ) ) : '';
}

function bnc_operators_from_jetengine( $type ) {
	$is_casino = ( 'casino' === $type );
	$items     = bnc_jet_query_items( $is_casino ? BNC_QUERY_CASINO : BNC_QUERY_SCOMMESSE );
	$ops       = array();
	foreach ( $items as $it ) {
		$go = (string) bnc_item_value( $it, $is_casino ? 'visita_il_sito' : 'visita_il_sito2' );
		$rv = $is_casino ? (string) bnc_item_value( $it, 'vai_alla_scheda' ) : '';
		$ops[] = array(
			'id'         => 0,
			'tipo'       => $type,
			'name'       => (string) bnc_item_value( $it, $is_casino ? 'name' : 'nome2' ),
			'logo_id'    => 0,
			'logo_url'   => bnc_html_img( bnc_item_value( $it, $is_casino ? 'logo' : 'logo_898' ) ),
			'bonus'      => (string) bnc_item_value( $it, $is_casino ? 'bonusbonus' : 'bonus' ),
			'nd'         => $is_casino ? (string) bnc_item_value( $it, 'bonus_senza_deposito' ) : '',
			'spid'       => $is_casino ? (string) bnc_item_value( $it, 'registrazione_spid' ) : '',
			'multipla'   => $is_casino ? '' : (string) bnc_item_value( $it, 'bonus_multipla' ),
			'rating'     => (string) bnc_item_value( $it, $is_casino ? 'valutazione' : 'valutazione_857' ),
			'go_url'     => bnc_html_href( $go ),
			'go_blank'   => false !== stripos( $go, 'target="_blank"' ),
			'review_url' => bnc_html_href( $rv ),
			'review_id'  => 0,
			'parere'     => $is_casino ? (string) bnc_item_value( $it, 'il_parere_dellesperto' ) : '',
			'welcome'    => $is_casino ? bnc_parse_checks( bnc_item_value( $it, 'welcome_bonus' ) ) : array(),
			'tipologia'  => $is_casino ? bnc_parse_checks( bnc_item_value( $it, 'tipologia_bonus' ) ) : array(),
			'pagamenti'  => $is_casino ? bnc_parse_checks( bnc_item_value( $it, 'pagamenti' ) ) : array(),
			'collegato'  => 0,
		);
	}
	return $ops;
}

/**
 * Operatore legato a una pagina (recensione o barra in basso).
 */
function bnc_operator_for_page( $page_id ) {
	$op = (int) get_post_meta( $page_id, '_bn_operatore', true );
	if ( $op && 'publish' === get_post_status( $op ) ) {
		return $op;
	}
	$found = get_posts( array(
		'post_type'   => BNC_POST_TYPE,
		'post_status' => 'publish',
		'numberposts' => 1,
		'fields'      => 'ids',
		'meta_key'    => '_bn_review_page', // phpcs:ignore WordPress.DB.SlowDBQuery
		'meta_value'  => (int) $page_id, // phpcs:ignore WordPress.DB.SlowDBQuery
	) );
	return $found ? (int) $found[0] : 0;
}
