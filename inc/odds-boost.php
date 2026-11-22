<?php
/**
 * Quote maggiorate (0.8.0).
 *
 * odds-api.io non le fornisce: si inseriscono a mano in Operatori > Quote maggiorate,
 * una per offerta, con bookmaker, descrizione, quota normale, quota maggiorata, scadenza
 * e (facoltativa) la partita. Alla scadenza spariscono da sole.
 *
 * - Blocco "Quote maggiorate": tutte le offerte valide, oppure solo quelle della partita
 *   del pronostico.
 * - Nel riquadro automatico delle quote compaiono le offerte collegate alla stessa partita.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BNC_BOOST_TYPE', 'bn-maggiorate' );

add_action( 'init', function () {
	register_post_type( BNC_BOOST_TYPE, array(
		'labels'          => array(
			'name'          => 'Quote maggiorate',
			'singular_name' => 'Quota maggiorata',
			'add_new'       => 'Aggiungi quota maggiorata',
			'add_new_item'  => 'Nuova quota maggiorata',
			'edit_item'     => 'Modifica quota maggiorata',
			'all_items'     => 'Quote maggiorate',
			'search_items'  => 'Cerca',
			'not_found'     => 'Nessuna quota maggiorata',
		),
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => 'edit.php?post_type=' . BNC_POST_TYPE,
		'show_in_rest'    => false,
		'supports'        => array( 'title' ),
		'capability_type' => 'post',
		'map_meta_cap'    => true,
	) );

	register_block_type( 'betnero/quote-maggiorate', array(
		'title'           => 'Quote maggiorate',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-quote',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false ),
		'uses_context'    => array( 'postId' ),
		'attributes'      => array(
			'quali'     => array( 'type' => 'string', 'default' => 'tutte' ),
			'limite'    => array( 'type' => 'number', 'default' => 10 ),
			'titolo'    => array( 'type' => 'string', 'default' => '' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => function ( $a, $c, $block ) {
			return bnc_boost_block( $a, bnc_odds_block_post( $block ) );
		},
	) );
}, 22 );

/* ---------- Riquadro di inserimento ---------- */

add_filter( 'enter_title_here', function ( $t, $post ) {
	return BNC_BOOST_TYPE === $post->post_type ? 'Descrizione, es. "Juventus vince e almeno 2 gol nella partita"' : $t;
}, 10, 2 );

add_action( 'add_meta_boxes_' . BNC_BOOST_TYPE, function () {
	add_meta_box( 'bnc-boost', 'Offerta', 'bnc_boost_metabox', BNC_BOOST_TYPE, 'normal', 'high' );
} );

function bnc_boost_get( $id ) {
	return array(
		'operatore' => (int) get_post_meta( $id, '_bnqm_operatore', true ),
		'normale'   => (float) get_post_meta( $id, '_bnqm_normale', true ),
		'maggiorata' => (float) get_post_meta( $id, '_bnqm_maggiorata', true ),
		'scadenza'  => (int) get_post_meta( $id, '_bnqm_scadenza', true ), // timestamp
		'evento'    => (int) get_post_meta( $id, '_bnqm_evento', true ),
		'note'      => (string) get_post_meta( $id, '_bnqm_note', true ),
	);
}

function bnc_boost_metabox( $post ) {
	global $wpdb;
	wp_nonce_field( 'bnc_boost', 'bnc_boost_nonce' );
	$b   = bnc_boost_get( $post->ID );
	$ops = bnc_operators( 'scommesse', false );
	$tz  = wp_timezone();
	$scad = $b['scadenza'] ? ( new DateTime( '@' . $b['scadenza'] ) )->setTimezone( $tz )->format( 'Y-m-d\TH:i' ) : '';

	echo '<table class="form-table" role="presentation"><tbody>';
	echo '<tr><th><label for="bnqm-op">Bookmaker</label></th><td><select id="bnqm-op" name="bnqm[operatore]" required><option value="">Scegli...</option>';
	foreach ( $ops as $op ) {
		echo '<option value="' . (int) $op['id'] . '"' . selected( $b['operatore'], (int) $op['id'], false ) . '>' . esc_html( $op['name'] ) . '</option>';
	}
	echo '</select><p class="description">Il pulsante porta al link affiliato dell\'operatore.</p></td></tr>';
	echo '<tr><th><label for="bnqm-n">Quota normale</label></th><td><input id="bnqm-n" name="bnqm[normale]" type="text" inputmode="decimal" value="' . esc_attr( $b['normale'] ? bnc_odds_fmt( $b['normale'] ) : '' ) . '" style="width:90px"> <span class="description">facoltativa, es. 3,17</span></td></tr>';
	echo '<tr><th><label for="bnqm-m">Quota maggiorata</label></th><td><input id="bnqm-m" name="bnqm[maggiorata]" type="text" inputmode="decimal" value="' . esc_attr( $b['maggiorata'] ? bnc_odds_fmt( $b['maggiorata'] ) : '' ) . '" style="width:90px" required> <span class="description">es. 3,44</span></td></tr>';
	echo '<tr><th><label for="bnqm-s">Scadenza</label></th><td><input id="bnqm-s" name="bnqm[scadenza]" type="datetime-local" value="' . esc_attr( $scad ) . '" required> <span class="description">ora italiana; dopo sparisce dal sito</span></td></tr>';

	$t    = bnc_odds_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, league_name, home, away, start FROM {$t} WHERE ( start > %s AND status IN ('pending','live') ) OR id = %d ORDER BY start ASC LIMIT 400", gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ), $b['evento'] ), ARRAY_A ); // phpcs:ignore WordPress.DB
	echo '<tr><th><label for="bnqm-ev">Partita</label></th><td><select id="bnqm-ev" name="bnqm[evento]" style="max-width:420px"><option value="">Nessuna (offerta generica)</option>';
	foreach ( $rows as $r ) {
		echo '<option value="' . (int) $r['id'] . '"' . selected( $b['evento'], (int) $r['id'], false ) . '>' . esc_html( wp_date( 'd/m H:i', strtotime( $r['start'] . ' UTC' ) ) . ' ' . bnc_odds_team( $r['home'] ) . ' - ' . bnc_odds_team( $r['away'] ) . ' (' . bnc_odds_league_label( $r['league_name'] ) . ')' ) . '</option>';
	}
	echo '</select><p class="description">Facoltativa. Se scelta, l\'offerta compare anche nei pronostici di questa partita.</p></td></tr>';
	echo '<tr><th><label for="bnqm-note">Condizioni</label></th><td><input id="bnqm-note" name="bnqm[note]" type="text" class="large-text" value="' . esc_attr( $b['note'] ) . '" placeholder="es. Solo pre-match, puntata massima 10 €"></td></tr>';
	echo '</tbody></table>';
}

function bnc_boost_num( $v ) {
	$v = str_replace( ',', '.', trim( (string) $v ) );
	return is_numeric( $v ) ? round( (float) $v, 2 ) : 0.0;
}

add_action( 'save_post_' . BNC_BOOST_TYPE, function ( $post_id ) {
	if ( ! isset( $_POST['bnc_boost_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bnc_boost_nonce'] ) ), 'bnc_boost' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$in = isset( $_POST['bnqm'] ) ? (array) wp_unslash( $_POST['bnqm'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	update_post_meta( $post_id, '_bnqm_operatore', absint( isset( $in['operatore'] ) ? $in['operatore'] : 0 ) );
	update_post_meta( $post_id, '_bnqm_normale', bnc_boost_num( isset( $in['normale'] ) ? $in['normale'] : '' ) );
	update_post_meta( $post_id, '_bnqm_maggiorata', bnc_boost_num( isset( $in['maggiorata'] ) ? $in['maggiorata'] : '' ) );
	update_post_meta( $post_id, '_bnqm_evento', absint( isset( $in['evento'] ) ? $in['evento'] : 0 ) );
	update_post_meta( $post_id, '_bnqm_note', sanitize_text_field( isset( $in['note'] ) ? $in['note'] : '' ) );
	$ts = 0;
	if ( ! empty( $in['scadenza'] ) ) {
		$d  = DateTime::createFromFormat( 'Y-m-d\TH:i', sanitize_text_field( $in['scadenza'] ), wp_timezone() );
		$ts = $d ? $d->getTimestamp() : 0;
	}
	update_post_meta( $post_id, '_bnqm_scadenza', $ts );
	if ( function_exists( 'bnc_schedule_page_cache_purge' ) ) {
		bnc_schedule_page_cache_purge();
	}
} );

/* ---------- Colonne della lista ---------- */

add_filter( 'manage_' . BNC_BOOST_TYPE . '_posts_columns', function ( $c ) {
	return array(
		'cb'        => isset( $c['cb'] ) ? $c['cb'] : '',
		'title'     => 'Descrizione',
		'bnqm_op'   => 'Bookmaker',
		'bnqm_q'    => 'Quota',
		'bnqm_ev'   => 'Partita',
		'bnqm_scad' => 'Scadenza',
	);
} );

add_action( 'manage_' . BNC_BOOST_TYPE . '_posts_custom_column', function ( $col, $id ) {
	$b = bnc_boost_get( $id );
	if ( 'bnqm_op' === $col ) {
		echo esc_html( $b['operatore'] ? get_the_title( $b['operatore'] ) : '-' );
	} elseif ( 'bnqm_q' === $col ) {
		echo ( $b['normale'] ? '<s>' . esc_html( bnc_odds_fmt( $b['normale'] ) ) . '</s> ' : '' ) . '<strong>' . esc_html( bnc_odds_fmt( $b['maggiorata'] ) ) . '</strong>';
	} elseif ( 'bnqm_ev' === $col ) {
		$ev = $b['evento'] ? bnc_odds_event( $b['evento'] ) : null;
		echo esc_html( $ev ? bnc_odds_team( $ev['home'] ) . ' - ' . bnc_odds_team( $ev['away'] ) : '-' );
	} elseif ( 'bnqm_scad' === $col ) {
		if ( ! $b['scadenza'] ) {
			echo '-';
		} else {
			$scaduta = $b['scadenza'] <= time();
			echo '<span style="color:' . ( $scaduta ? '#b32d2e' : '#008a20' ) . '">' . esc_html( wp_date( 'd/m H:i', $b['scadenza'] ) . ( $scaduta ? ' (scaduta)' : '' ) ) . '</span>';
		}
	}
}, 10, 2 );

/* ---------- Lettura e visualizzazione ---------- */

/** Offerte valide (pubblicate e non scadute), dalla piu' vicina alla scadenza. $evento = 0 per tutte. */
function bnc_boost_active( $evento = 0, $limit = 10 ) {
	$q = array(
		'post_type'      => BNC_BOOST_TYPE,
		'post_status'    => 'publish',
		'posts_per_page' => max( 1, min( 50, (int) $limit ) ),
		'meta_key'       => '_bnqm_scadenza', // phpcs:ignore WordPress.DB.SlowDBQuery
		'orderby'        => 'meta_value_num',
		'order'          => 'ASC',
		'no_found_rows'  => true,
		'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
			array( 'key' => '_bnqm_scadenza', 'value' => time(), 'compare' => '>', 'type' => 'NUMERIC' ),
		),
	);
	if ( $evento ) {
		$q['meta_query'][] = array( 'key' => '_bnqm_evento', 'value' => (int) $evento, 'compare' => '=', 'type' => 'NUMERIC' );
	}
	$out = array();
	foreach ( get_posts( $q ) as $p ) {
		$b = bnc_boost_get( $p->ID );
		if ( ! $b['operatore'] || 'publish' !== get_post_status( $b['operatore'] ) || $b['maggiorata'] <= 0 ) {
			continue;
		}
		$b['id']    = $p->ID;
		$b['testo'] = get_the_title( $p );
		$b['op']    = bnc_operator( $b['operatore'] );
		$out[]      = $b;
	}
	return $out;
}

function bnc_boost_render( $items, $titolo = 'Quote maggiorate', $with_match = true ) {
	if ( ! $items ) {
		return '';
	}
	$h = '<div class="bnq bnq-boost"><p class="bnq-title">' . esc_html( $titolo ) . '</p><div class="bnq-bl">';
	foreach ( $items as $b ) {
		$ev    = ( $with_match && $b['evento'] ) ? bnc_odds_event( $b['evento'] ) : null;
		$h    .= '<div class="bnq-bi"><div class="bnq-logo">' . bnc_logo( $b['op'] ) . '</div><div class="bnq-bt">';
		$h    .= '<strong>' . esc_html( $b['testo'] ) . '</strong>';
		if ( $ev ) {
			$h .= '<span>' . esc_html( bnc_odds_team( $ev['home'] ) . ' - ' . bnc_odds_team( $ev['away'] ) . ', ' . wp_date( 'j/m H:i', strtotime( $ev['start'] . ' UTC' ) ) ) . '</span>';
		}
		$h .= '<span class="bnq-bs">Valida fino alle ' . esc_html( wp_date( 'H:i \d\e\l j/m', $b['scadenza'] ) ) . ( '' !== $b['note'] ? ' &middot; ' . esc_html( $b['note'] ) : '' ) . '</span></div>';
		$h .= '<div class="bnq-bq">' . ( $b['normale'] > 0 ? '<s>' . esc_html( bnc_odds_fmt( $b['normale'] ) ) . '</s>' : '' ) . '<strong>' . esc_html( bnc_odds_fmt( $b['maggiorata'] ) ) . '</strong></div>';
		$h .= '<div class="bnq-bgo">' . bnc_go_button( $b['op'], 'Gioca', 'bnt-btn bnt-go' ) . '</div></div>';
	}
	return $h . '</div><p class="bnq-adm">18+ | Gioca responsabilmente | Offerte dei bookmaker, valide fino alla scadenza indicata e alle loro condizioni</p></div>';
}

function bnc_boost_attrs( $a ) {
	$a = is_array( $a ) ? $a : array();
	return array(
		'quali'  => ( isset( $a['quali'] ) && 'pronostico' === $a['quali'] ) ? 'pronostico' : 'tutte',
		'limite' => max( 1, min( 30, isset( $a['limite'] ) ? (int) $a['limite'] : 10 ) ),
		'titolo' => isset( $a['titolo'] ) ? sanitize_text_field( $a['titolo'] ) : '',
	);
}

function bnc_boost_render_for( $a, $post_id ) {
	$a      = bnc_boost_attrs( $a );
	$evento = 'pronostico' === $a['quali'] ? (int) get_post_meta( $post_id, '_bn_quote_evento', true ) : 0;
	if ( 'pronostico' === $a['quali'] && ! $evento ) {
		return '';
	}
	$titolo = '' !== $a['titolo'] ? $a['titolo'] : ( $evento ? 'Quote maggiorate per questa partita' : 'Quote maggiorate di oggi' );
	return bnc_boost_render( bnc_boost_active( $evento, $a['limite'] ), $titolo, ! $evento );
}

function bnc_boost_block( $a, $post_id ) {
	$a     = bnc_boost_attrs( $a );
	$inner = bnc_boost_render_for( $a, $post_id );
	if ( '' === $inner ) {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? '<p class="bnq-empty">Nessuna quota maggiorata valida' . ( 'pronostico' === $a['quali'] ? ' per la partita di questo pronostico' : '' ) . '. Si aggiungono in Operatori &gt; Quote maggiorate.</p>' : '';
	}
	wp_enqueue_script( 'betnero-quote' );
	return '<div class="bnq-live" data-qm="' . esc_attr( wp_json_encode( $a ) ) . '" data-qmpost="' . (int) $post_id . '" data-url="' . esc_url( rest_url( 'betnero/v1/quote/maggiorate' ) ) . '">' . $inner . '</div>';
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'betnero/v1', '/quote/maggiorate', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'a'    => array( 'type' => 'string', 'required' => true ),
			'post' => array( 'type' => 'integer', 'default' => 0 ),
		),
		'callback'            => function ( $req ) {
			$pid = (int) $req['post'];
			if ( $pid && 'publish' !== get_post_status( $pid ) && ! current_user_can( 'edit_post', $pid ) ) {
				$pid = 0;
			}
			$res = rest_ensure_response( array( 'html' => bnc_boost_render_for( json_decode( (string) $req['a'], true ), $pid ) ) );
			$res->header( 'Cache-Control', 'public, max-age=120' );
			return $res;
		},
	) );
} );

/** Nel riquadro automatico delle quote: le offerte della stessa partita, sotto il confronto. */
add_filter( 'bnc_odds_entrambi_after', function ( $html, $post_id ) {
	$ev = (int) get_post_meta( $post_id, '_bn_quote_evento', true );
	return $ev ? $html . bnc_boost_render( bnc_boost_active( $ev, 5 ), 'Quote maggiorate per questa partita', false ) : $html;
}, 10, 2 );
