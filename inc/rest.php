<?php
/**
 * API REST per lavorare sugli operatori e sulle recensioni senza passare
 * dall'admin (utile con le password per le applicazioni).
 *
 * GET  /wp-json/betnero/v1/operatori              elenco operatori con recensione e pagina a blocchi
 * GET  /wp-json/betnero/v1/operatori/<id>          tutti i campi di un operatore
 * POST /wp-json/betnero/v1/operatori/<id>/bozza    crea (o rifa') la pagina a blocchi della recensione
 * POST /wp-json/betnero/v1/operatori/<id>/campi    scrive i campi indicati (es. {"campi":{"pagamenti":["paypal"]}})
 *
 * Servono i permessi per modificare le pagine (ruolo Editor o superiore).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'rest_api_init', function () {
	$can = function () {
		return current_user_can( 'edit_pages' );
	};

	register_rest_route( 'betnero/v1', '/operatori', array(
		'methods'             => 'GET',
		'permission_callback' => $can,
		'callback'            => function () {
			$out = array();
			$ops = get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC' ) ) );
			foreach ( $ops as $op ) {
				$page  = (int) bnc_get( $op->ID, 'review_page' );
				$draft = bnc_review_draft_id( $op->ID );
				$out[] = array(
					'id'          => $op->ID,
					'nome'        => $op->post_title,
					'stato'       => $op->post_status,
					'tipo'        => bnc_tipo( $op->ID ),
					'in_tabella'  => (bool) bnc_get( $op->ID, 'in_tabella' ),
					'recensione'  => $page ? array( 'id' => $page, 'link' => get_permalink( $page ) ) : null,
					'bozza'       => $draft ? array( 'id' => $draft, 'stato' => get_post_status( $draft ), 'link' => get_permalink( $draft ) ) : null,
				);
			}
			return $out;
		},
	) );

	register_rest_route( 'betnero/v1', '/operatori/(?P<id>\d+)', array(
		'methods'             => 'GET',
		'permission_callback' => $can,
		'callback'            => function ( $req ) {
			$id = (int) $req['id'];
			if ( BNC_POST_TYPE !== get_post_type( $id ) ) {
				return new WP_Error( 'bnc_not_found', 'Operatore non trovato', array( 'status' => 404 ) );
			}
			$campi = array();
			foreach ( array_keys( bnc_fields() ) as $k ) {
				$campi[ $k ] = bnc_get( $id, $k );
			}
			return array(
				'id'    => $id,
				'nome'  => get_the_title( $id ),
				'stato' => get_post_status( $id ),
				'tipo'  => bnc_tipo( $id ),
				'campi' => $campi,
			);
		},
	) );

	register_rest_route( 'betnero/v1', '/operatori/(?P<id>\d+)/bozza', array(
		'methods'             => 'POST',
		'permission_callback' => $can,
		'callback'            => function ( $req ) {
			$id = (int) $req['id'];
			if ( BNC_POST_TYPE !== get_post_type( $id ) ) {
				return new WP_Error( 'bnc_not_found', 'Operatore non trovato', array( 'status' => 404 ) );
			}
			$existing = bnc_review_draft_id( $id );
			if ( $existing && ! current_user_can( 'edit_post', $existing ) ) {
				return new WP_Error( 'bnc_forbidden', 'Non puoi modificare questa pagina', array( 'status' => 403 ) );
			}
			$page = bnc_create_review_draft( $id );
			if ( ! $page ) {
				return new WP_Error( 'bnc_failed', 'Pagina non creata', array( 'status' => 500 ) );
			}
			return array(
				'id'     => $page,
				'rifatta' => (bool) $existing,
				'stato'  => get_post_status( $page ),
				'link'   => get_permalink( $page ),
			);
		},
	) );
	register_rest_route( 'betnero/v1', '/operatori/(?P<id>\d+)/campi', array(
		'methods'             => 'POST',
		'permission_callback' => function ( $req ) {
			return current_user_can( 'edit_post', (int) $req['id'] );
		},
		'callback'            => function ( $req ) {
			$id = (int) $req['id'];
			if ( BNC_POST_TYPE !== get_post_type( $id ) ) {
				return new WP_Error( 'bnc_not_found', 'Operatore non trovato', array( 'status' => 404 ) );
			}
			$campi  = (array) $req->get_param( 'campi' );
			$fields = bnc_fields();
			$fatti  = array();
			foreach ( $campi as $k => $v ) {
				if ( ! isset( $fields[ $k ] ) ) {
					continue;
				}
				bnc_set( $id, $k, $v );
				$fatti[ $k ] = bnc_get( $id, $k );
			}
			if ( $fatti ) {
				bnc_flush_cache();
			}
			return array( 'id' => $id, 'campi' => $fatti );
		},
	) );
} );
