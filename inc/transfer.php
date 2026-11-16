<?php
/**
 * Trasferimento dallo staging alla produzione (0.5.0), senza copiare il database.
 *
 * Lo script del go-live legge e scrive via REST i dati che le API standard di
 * WordPress non espongono:
 *
 * GET  /betnero/v1/trasferimento/post/<id>   dati nascosti di una pagina o di un operatore
 * POST /betnero/v1/trasferimento/post/<id>   { "meta": { "chiave": valore | null } }
 * GET  /betnero/v1/trasferimento/opzioni     impostazioni di Betnero Core
 * POST /betnero/v1/trasferimento/opzioni     { "opzioni": { ... } }
 *
 * Solo le chiavi dell'elenco qui sotto, solo per gli amministratori.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Chiavi trasferibili: tutte quelle di Betnero (_bn_...), il modello di pagina,
 * titolo SEO e meta description di Yoast, e il segnale di Elementor (da togliere
 * sulle pagine convertite in blocchi).
 */
function bnc_transfer_key_ok( $key ) {
	if ( 0 === strpos( $key, '_bn_' ) ) {
		return true;
	}
	return in_array( $key, array( '_wp_page_template', '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_elementor_edit_mode' ), true );
}

function bnc_transfer_options() {
	return array( 'bnc_toplist', 'bnc_bar_auto_v1' );
}

add_action( 'rest_api_init', function () {
	$admin = function () {
		return current_user_can( 'manage_options' );
	};

	register_rest_route( 'betnero/v1', '/trasferimento/post/(?P<id>\d+)', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => $admin,
			'callback'            => function ( $req ) {
				$id   = (int) $req['id'];
				$post = get_post( $id );
				if ( ! $post ) {
					return new WP_Error( 'bnc_not_found', 'Contenuto non trovato', array( 'status' => 404 ) );
				}
				$meta = array();
				foreach ( (array) get_post_meta( $id ) as $k => $vals ) {
					if ( bnc_transfer_key_ok( $k ) ) {
						$meta[ $k ] = maybe_unserialize( $vals[0] );
					}
				}
				return array(
					'id'         => $id,
					'tipo'       => $post->post_type,
					'stato'      => $post->post_status,
					'slug'       => $post->post_name,
					'genitore'   => (int) $post->post_parent,
					'ordine'     => (int) $post->menu_order,
					'modificato' => $post->post_modified_gmt,
					'meta'       => $meta,
				);
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => $admin,
			'callback'            => function ( $req ) {
				$id = (int) $req['id'];
				if ( ! get_post( $id ) ) {
					return new WP_Error( 'bnc_not_found', 'Contenuto non trovato', array( 'status' => 404 ) );
				}
				$fatti = array();
				foreach ( (array) $req->get_param( 'meta' ) as $k => $v ) {
					if ( ! bnc_transfer_key_ok( $k ) ) {
						continue;
					}
					if ( null === $v ) {
						delete_post_meta( $id, $k );
					} else {
						update_post_meta( $id, $k, is_string( $v ) ? wp_slash( $v ) : $v );
					}
					$fatti[] = $k;
				}
				if ( $fatti && function_exists( 'bnc_flush_cache' ) ) {
					bnc_flush_cache();
				}
				clean_post_cache( $id );
				return array( 'id' => $id, 'scritti' => $fatti );
			},
		),
	) );

	register_rest_route( 'betnero/v1', '/trasferimento/opzioni', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => $admin,
			'callback'            => function () {
				$out = array();
				foreach ( bnc_transfer_options() as $o ) {
					$out[ $o ] = get_option( $o, null );
				}
				return $out;
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => $admin,
			'callback'            => function ( $req ) {
				$fatti = array();
				foreach ( (array) $req->get_param( 'opzioni' ) as $o => $v ) {
					if ( in_array( $o, bnc_transfer_options(), true ) ) {
						update_option( $o, $v );
						$fatti[] = $o;
					}
				}
				return array( 'scritte' => $fatti );
			},
		),
	) );
} );
