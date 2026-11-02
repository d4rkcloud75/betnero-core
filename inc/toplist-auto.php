<?php
/**
 * Toplist automatica negli articoli e in sidebar (sostituisce lo snippet 8).
 *
 * Il tipo (casino o scommesse) dipende dalla categoria dell'articolo:
 * Operatori > Impostazioni. Ogni articolo puo' forzarlo dal riquadro
 * "Toplist Betnero" nella colonna di destra.
 *
 * Se lo snippet 8 e' ancora attivo, questo modulo resta spento per non
 * mostrare due toplist.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bnc_toplist_settings() {
	$s = get_option( 'bnc_toplist', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array(
		'enabled' => '1',
		'sidebar' => '1',
		'default' => 'casino',
		'limit'   => 5,
		'map'     => array(),
	) );
}

/** Lo snippet 8 ("Betnero: toplist per categoria") e' ancora attivo? */
function bnc_snippet_active() {
	return function_exists( 'bn_toplist_compact' ) || function_exists( 'bn_toplist_type' );
}

/**
 * Tipo suggerito per una categoria dal suo slug (usato finche' non la imposti).
 */
function bnc_guess_type( $term ) {
	$chain = array( $term );
	foreach ( get_ancestors( $term->term_id, 'category' ) as $aid ) {
		$t = get_term( $aid, 'category' );
		if ( $t && ! is_wp_error( $t ) ) {
			$chain[] = $t;
		}
	}
	foreach ( $chain as $t ) {
		$slug = $t->slug . ' ' . sanitize_title( $t->name );
		if ( preg_match( '/academy/', $slug ) ) {
			return 'none';
		}
		if ( preg_match( '/scommess|pronostic|calcio|sport|bookmaker|quot[ae]|serie-a|champions|tennis|basket|mondial/', $slug ) ) {
			return 'scommesse';
		}
		if ( preg_match( '/casin|slot|poker|roulette|blackjack|gratta|bingo|lotter|provider/', $slug ) ) {
			return 'casino';
		}
	}
	return '';
}

/**
 * Tipo di toplist per una categoria: impostazione salvata, poi suggerimento, poi predefinito.
 */
function bnc_category_type( $term ) {
	$s = bnc_toplist_settings();
	if ( isset( $s['map'][ $term->term_id ] ) && '' !== $s['map'][ $term->term_id ] ) {
		return $s['map'][ $term->term_id ];
	}
	foreach ( get_ancestors( $term->term_id, 'category' ) as $aid ) {
		if ( isset( $s['map'][ $aid ] ) && '' !== $s['map'][ $aid ] ) {
			return $s['map'][ $aid ];
		}
	}
	$g = bnc_guess_type( $term );
	return $g ? $g : $s['default'];
}

/**
 * Tipo di toplist di un articolo: casino, scommesse o none.
 */
function bnc_article_type( $post_id, $use_forced = true ) {
	$forced = $use_forced ? get_post_meta( $post_id, '_bn_toplist', true ) : '';
	if ( in_array( $forced, array( 'casino', 'scommesse', 'none' ), true ) ) {
		return $forced;
	}
	$cats = get_the_category( $post_id );
	if ( empty( $cats ) ) {
		return bnc_toplist_settings()['default'];
	}
	$found = array();
	foreach ( $cats as $c ) {
		$found[] = bnc_category_type( $c );
	}
	// Una categoria "scommesse" vince su "casino"; "none" solo se lo sono tutte.
	if ( in_array( 'scommesse', $found, true ) ) {
		return 'scommesse';
	}
	if ( in_array( 'casino', $found, true ) ) {
		return 'casino';
	}
	return 'none';
}

function bnc_auto_toplist_on() {
	$s = bnc_toplist_settings();
	return '1' === (string) $s['enabled'] && ! bnc_snippet_active();
}

/* ---------- Nel testo dell'articolo ---------- */

/**
 * Passo 1 (priorita' 20): segnaposto prima del primo H2, o dopo il primo paragrafo.
 * Passo 2 (ultima priorita'): il segnaposto diventa la toplist, cosi' altri plugin
 * (Ad Inserter) non ci infilano banner dentro.
 */
add_filter( 'the_content', function ( $content ) {
	if ( ! bnc_auto_toplist_on() || is_admin() || is_feed() || wp_is_json_request() || doing_action( 'wp_head' ) ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) || get_the_ID() !== get_queried_object_id() ) {
		return $content;
	}
	if ( false !== strpos( $content, 'bnt-toplist' ) || false !== strpos( $content, 'data-bn-toplist' ) || 'none' === bnc_article_type( get_the_ID() ) ) {
		return $content;
	}
	$marker = '<div class="bn-toplist-articolo" data-bn-toplist="1"></div>';
	$pos    = stripos( $content, '<h2' );
	if ( false !== $pos ) {
		return substr( $content, 0, $pos ) . $marker . substr( $content, $pos );
	}
	$pos = stripos( $content, '</p>' );
	if ( false !== $pos ) {
		return substr( $content, 0, $pos + 4 ) . $marker . substr( $content, $pos + 4 );
	}
	return $content;
}, 20 );

add_filter( 'the_content', function ( $content ) {
	if ( false === strpos( $content, 'data-bn-toplist="1"' ) ) {
		return $content;
	}
	$type = bnc_article_type( get_the_ID() );
	$s    = bnc_toplist_settings();
	$html = 'none' === $type ? '' : bnc_render_toplist( array( 'type' => $type, 'limit' => (int) $s['limit'] ) );
	return str_replace( '<div class="bn-toplist-articolo" data-bn-toplist="1"></div>', '<div class="bn-toplist-articolo">' . $html . '</div>', $content );
}, PHP_INT_MAX );

/* ---------- Sidebar: [bn_toplist_sidebar] ---------- */

add_action( 'init', function () {
	if ( bnc_snippet_active() ) {
		return; // Lo shortcode resta dello snippet finche' e' attivo.
	}
	add_shortcode( 'bn_toplist_sidebar', function ( $atts ) {
		$s = bnc_toplist_settings();
		if ( '1' !== (string) $s['sidebar'] ) {
			return '';
		}
		$atts = shortcode_atts( array( 'tipo' => '', 'limit' => $s['limit'] ), $atts );
		$type = in_array( $atts['tipo'], array( 'casino', 'scommesse' ), true ) ? $atts['tipo'] : '';
		if ( ! $type ) {
			$type = is_singular( 'post' ) ? bnc_article_type( get_queried_object_id() ) : $s['default'];
		}
		if ( 'none' === $type ) {
			return ''; // Es. Academy: nessuna toplist, neanche in sidebar.
		}
		return bnc_render_toplist( array( 'type' => $type, 'limit' => (int) $atts['limit'], 'layout' => 'side' ) );
	} );
}, 20 );

/* CSS gia' nell'head per gli articoli (niente salto di impaginazione) */
add_action( 'wp_enqueue_scripts', function () {
	if ( is_singular( 'post' ) && bnc_auto_toplist_on() ) {
		wp_enqueue_style( 'betnero-tables' );
	}
} );

/* ---------- Riquadro nell'articolo: forza il tipo ---------- */

add_action( 'add_meta_boxes_post', function () {
	add_meta_box( 'bnc-toplist', 'Toplist Betnero', function ( $post ) {
		$v = get_post_meta( $post->ID, '_bn_toplist', true );
		wp_nonce_field( 'bnc_toplist_post', 'bnc_toplist_nonce' );
		$auto = bnc_article_type( $post->ID, false );
		$labels = array( 'casino' => 'casino', 'scommesse' => 'scommesse', 'none' => 'nessuna' );
		echo '<select name="bnc_toplist" style="width:100%">';
		echo '<option value="">Automatica (' . esc_html( $labels[ $auto ] ) . ')</option>';
		foreach ( array( 'casino' => 'Casino', 'scommesse' => 'Scommesse', 'none' => 'Nessuna toplist' ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $v, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select>';
	}, 'post', 'side', 'low' );
} );

add_action( 'save_post_post', function ( $post_id ) {
	if ( ! isset( $_POST['bnc_toplist_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bnc_toplist_nonce'] ) ), 'bnc_toplist_post' ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$v = isset( $_POST['bnc_toplist'] ) ? sanitize_key( $_POST['bnc_toplist'] ) : '';
	if ( in_array( $v, array( 'casino', 'scommesse', 'none' ), true ) ) {
		update_post_meta( $post_id, '_bn_toplist', $v );
	} else {
		delete_post_meta( $post_id, '_bn_toplist' );
	}
} );
