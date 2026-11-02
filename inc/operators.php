<?php
/**
 * Tipo di contenuto "Operatori" (casino e bookmaker) e lista in admin.
 *
 * L'ordine nelle tabelle e' l'ordine della lista in admin: si cambia
 * trascinando le righe (colonna "Posizione").
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bnc_register_post_type() {
	register_post_type( BNC_POST_TYPE, array(
		'labels'              => array(
			'name'               => 'Operatori',
			'singular_name'      => 'Operatore',
			'menu_name'          => 'Operatori',
			'all_items'          => 'Tutti gli operatori',
			'add_new'            => 'Aggiungi operatore',
			'add_new_item'       => 'Aggiungi operatore',
			'edit_item'          => 'Modifica operatore',
			'new_item'           => 'Nuovo operatore',
			'search_items'       => 'Cerca operatori',
			'not_found'          => 'Nessun operatore',
			'not_found_in_trash' => 'Nessun operatore nel cestino',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_rest'        => true,
		'rest_base'           => 'bn-operatori',
		'menu_position'       => 21,
		'menu_icon'           => 'dashicons-awards',
		'supports'            => array( 'title', 'page-attributes', 'revisions' ),
		'hierarchical'        => false,
		'has_archive'         => false,
		'rewrite'             => false,
		'exclude_from_search' => true,
		'capability_type'     => 'post',
	) );

	foreach ( bnc_fields() as $key => $f ) {
		register_post_meta( BNC_POST_TYPE, '_bn_' . $key, array(
			'single'        => true,
			'show_in_rest'  => false,
			'auth_callback' => function () {
				return current_user_can( 'edit_posts' );
			},
		) );
	}
}
add_action( 'init', 'bnc_register_post_type' );

/* Schermata classica (solo campi), niente editor a blocchi per gli operatori */
add_filter( 'use_block_editor_for_post_type', function ( $use, $post_type ) {
	return BNC_POST_TYPE === $post_type ? false : $use;
}, 10, 2 );

/* Tipo e nome nelle risposte REST (servono al selettore del blocco "Scheda operatore") */
add_action( 'rest_api_init', function () {
	register_rest_field( BNC_POST_TYPE, 'bn_tipo', array(
		'get_callback' => function ( $obj ) {
			return bnc_tipo( $obj['id'] );
		},
	) );
} );

/* Sottomenu: Casino / Bookmaker */
add_action( 'admin_menu', function () {
	$base = 'edit.php?post_type=' . BNC_POST_TYPE;
	add_submenu_page( $base, 'Casino', 'Casino', 'edit_posts', $base . '&bn_tipo=casino' );
	add_submenu_page( $base, 'Bookmaker', 'Bookmaker', 'edit_posts', $base . '&bn_tipo=scommesse' );
} );

/* Nuovo operatore con il tipo scelto dalla lista */
add_filter( 'admin_url', function ( $url, $path ) {
	if ( 'post-new.php?post_type=' . BNC_POST_TYPE === $path && isset( $_GET['bn_tipo'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		$url = add_query_arg( 'bn_tipo', sanitize_key( $_GET['bn_tipo'] ), $url ); // phpcs:ignore WordPress.Security.NonceVerification
	}
	return $url;
}, 10, 2 );

/* Lista: filtro per tipo e ordine per posizione */
add_action( 'pre_get_posts', function ( $q ) {
	if ( ! is_admin() || ! $q->is_main_query() || BNC_POST_TYPE !== $q->get( 'post_type' ) ) {
		return;
	}
	$tipo = isset( $_GET['bn_tipo'] ) ? sanitize_key( $_GET['bn_tipo'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	if ( in_array( $tipo, array( 'casino', 'scommesse' ), true ) ) {
		$q->set( 'meta_query', array( array( 'key' => '_bn_tipo', 'value' => $tipo ) ) );
	}
	if ( ! $q->get( 'orderby' ) ) {
		$q->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
	$q->set( 'posts_per_page', 200 );
} );

add_filter( 'views_edit-' . BNC_POST_TYPE, function ( $views ) {
	$current = isset( $_GET['bn_tipo'] ) ? sanitize_key( $_GET['bn_tipo'] ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
	foreach ( array( 'casino' => 'Casino', 'scommesse' => 'Bookmaker' ) as $t => $label ) {
		$n          = count( get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_bn_tipo', 'meta_value' => $t ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
		$url        = admin_url( 'edit.php?post_type=' . BNC_POST_TYPE . '&bn_tipo=' . $t );
		$views[ $t ] = '<a href="' . esc_url( $url ) . '"' . ( $current === $t ? ' class="current"' : '' ) . '>' . esc_html( $label ) . ' <span class="count">(' . $n . ')</span></a>';
	}
	return $views;
} );

add_filter( 'manage_' . BNC_POST_TYPE . '_posts_columns', function ( $cols ) {
	return array(
		'cb'         => $cols['cb'],
		'bn_order'   => 'Posizione',
		'bn_logo'    => 'Logo',
		'title'      => 'Nome',
		'bn_tipo'    => 'Tipo',
		'bn_bonus'   => 'Bonus',
		'bn_rating'  => 'Voto',
		'bn_tabella' => 'In tabella',
		'date'       => $cols['date'],
	);
} );

add_action( 'manage_' . BNC_POST_TYPE . '_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'bn_order':
			echo '<span class="bnc-handle dashicons dashicons-menu" title="Trascina per cambiare posizione"></span> <span class="bnc-pos">' . (int) get_post_field( 'menu_order', $post_id ) . '</span>';
			break;
		case 'bn_logo':
			$id = (int) bnc_get( $post_id, 'logo' );
			echo $id ? wp_get_attachment_image( $id, 'thumbnail', false, array( 'style' => 'max-width:90px;max-height:36px;width:auto;height:auto' ) ) : '';
			break;
		case 'bn_tipo':
			echo 'scommesse' === bnc_tipo( $post_id ) ? 'Bookmaker' : 'Casino';
			break;
		case 'bn_bonus':
			echo esc_html( wp_trim_words( wp_strip_all_tags( (string) bnc_get( $post_id, 'bonus' ) ), 12 ) );
			break;
		case 'bn_rating':
			echo esc_html( (string) bnc_get( $post_id, 'rating' ) );
			break;
		case 'bn_tabella':
			echo '1' === (string) bnc_get( $post_id, 'in_tabella' ) ? 'Sì' : '<span style="color:#b32d2e">No</span>';
			break;
	}
}, 10, 2 );

/* Trascinamento delle righe per cambiare l'ordine */
add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || BNC_POST_TYPE !== $screen->post_type ) {
		return;
	}
	wp_enqueue_style( 'bnc-admin', BETNERO_CORE_URL . 'assets/css/admin.css', array(), BETNERO_CORE_VERSION );
	if ( 'edit.php' === $hook ) {
		wp_enqueue_script( 'bnc-admin-order', BETNERO_CORE_URL . 'assets/js/admin-order.js', array( 'jquery', 'jquery-ui-sortable' ), BETNERO_CORE_VERSION, true );
		wp_localize_script( 'bnc-admin-order', 'BNC_ORDER', array(
			'ajax'  => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'bnc_order' ),
		) );
	}
} );

add_action( 'wp_ajax_bnc_save_order', function () {
	check_ajax_referer( 'bnc_order', 'nonce' );
	if ( ! current_user_can( 'edit_posts' ) ) {
		wp_send_json_error( 'permessi', 403 );
	}
	$ids = isset( $_POST['ids'] ) ? array_map( 'absint', (array) $_POST['ids'] ) : array();
	global $wpdb;
	$pos = 10;
	foreach ( $ids as $id ) {
		if ( $id && BNC_POST_TYPE === get_post_type( $id ) ) {
			$wpdb->update( $wpdb->posts, array( 'menu_order' => $pos ), array( 'ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $id );
			$pos += 10;
		}
	}
	bnc_flush_cache();
	wp_send_json_success();
} );

/* Cache dei dati operatori: svuotata a ogni modifica */
function bnc_flush_cache() {
	if ( ! empty( $GLOBALS['bnc_bulk'] ) ) {
		return; // Durante la migrazione si svuota una volta sola alla fine.
	}
	wp_cache_delete( 'bnc_ops_casino', 'betnero' );
	wp_cache_delete( 'bnc_ops_scommesse', 'betnero' );
	delete_transient( 'bnc_ops_casino' );
	delete_transient( 'bnc_ops_scommesse' );
	if ( function_exists( 'rocket_clean_domain' ) ) {
		rocket_clean_domain(); // WP Rocket: le tabelle sono su molte pagine.
	}
}
add_action( 'save_post_' . BNC_POST_TYPE, 'bnc_flush_cache' );
add_action( 'trashed_post', function ( $id ) {
	if ( BNC_POST_TYPE === get_post_type( $id ) ) {
		bnc_flush_cache();
	}
} );
