<?php
/**
 * Riquadro "Betnero" su pagine e articoli:
 * - operatore della pagina (usato dal blocco "Scheda operatore")
 * - barra in basso con l'offerta (sostituisce i popup "Bottom Bar" di JetPopup)
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Operatore della barra in basso per un contenuto (0 = nessuna barra).
 */
function bnc_bar_operator( $post_id ) {
	if ( ! bnc_bar_enabled() ) {
		return 0;
	}
	$v = get_post_meta( $post_id, '_bn_barra', true );
	if ( 'page' === $v ) {
		return bnc_operator_for_page( $post_id );
	}
	$v = (int) $v;
	return ( $v && 'publish' === get_post_status( $v ) ) ? $v : 0;
}

/**
 * La barra resta spenta finche' JetPopup e' attivo (altrimenti si vedrebbero
 * due barre), salvo l'opzione "prova" nelle impostazioni.
 */
function bnc_bar_enabled() {
	$s = bnc_toplist_settings();
	$jetpopup = class_exists( 'Jet_Popup' ) || defined( 'JET_POPUP_VERSION' );
	return ! $jetpopup || ! empty( $s['bar_force'] );
}

add_action( 'add_meta_boxes', function ( $post_type ) {
	if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
		return;
	}
	add_meta_box( 'bnc-page', 'Betnero: operatore e barra in basso', 'bnc_render_page_box', $post_type, 'side', 'default' );
} );

function bnc_render_page_box( $post ) {
	wp_nonce_field( 'bnc_page', 'bnc_page_nonce' );
	$ops   = get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
	$page  = (int) get_post_meta( $post->ID, '_bn_operatore', true );
	$bar   = (string) get_post_meta( $post->ID, '_bn_barra', true );
	$label = function ( $op ) {
		return $op->post_title . ( 'scommesse' === bnc_tipo( $op->ID ) ? ' (bookmaker)' : ' (casino)' );
	};

	echo '<p><label for="bnc_operatore"><strong>Operatore di questa pagina</strong></label><br><select id="bnc_operatore" name="bnc_operatore" style="width:100%"><option value="0">- Nessuno -</option>';
	foreach ( $ops as $op ) {
		echo '<option value="' . (int) $op->ID . '"' . selected( $page, $op->ID, false ) . '>' . esc_html( $label( $op ) ) . '</option>';
	}
	echo '</select><span class="description">Per le recensioni: lo usa il blocco "Scheda operatore".</span></p>';

	echo '<p><label for="bnc_barra"><strong>Barra in basso</strong></label><br><select id="bnc_barra" name="bnc_barra" style="width:100%">';
	echo '<option value="">Nessuna</option>';
	echo '<option value="page"' . selected( $bar, 'page', false ) . '>Operatore di questa pagina</option>';
	foreach ( $ops as $op ) {
		echo '<option value="' . (int) $op->ID . '"' . selected( $bar, (string) $op->ID, false ) . '>' . esc_html( $label( $op ) ) . '</option>';
	}
	echo '</select></p>';
}

add_action( 'save_post', function ( $post_id ) {
	if ( ! isset( $_POST['bnc_page_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bnc_page_nonce'] ) ), 'bnc_page' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$op = isset( $_POST['bnc_operatore'] ) ? absint( $_POST['bnc_operatore'] ) : 0;
	if ( $op ) {
		update_post_meta( $post_id, '_bn_operatore', $op );
	} else {
		delete_post_meta( $post_id, '_bn_operatore' );
	}
	delete_post_meta( $post_id, '_bn_barra_auto' ); // Scelta a mano: l'importazione non la cambia piu'.
	$bar = isset( $_POST['bnc_barra'] ) ? sanitize_text_field( wp_unslash( $_POST['bnc_barra'] ) ) : '';
	if ( 'page' === $bar || absint( $bar ) ) {
		update_post_meta( $post_id, '_bn_barra', 'page' === $bar ? 'page' : (string) absint( $bar ) );
	} else {
		delete_post_meta( $post_id, '_bn_barra' );
	}
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular() && bnc_bar_operator( get_queried_object_id() ) ) {
		wp_enqueue_style( 'betnero-bar' );
		wp_enqueue_script( 'betnero-bar' );
	}
} );

add_action( 'wp_footer', function () {
	if ( ! is_singular() ) {
		return;
	}
	$op = bnc_bar_operator( get_queried_object_id() );
	if ( $op ) {
		echo bnc_render_bar( $op ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
}, 5 );
