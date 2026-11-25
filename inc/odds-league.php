<?php
/**
 * Blocco "Quote campionato" (0.9.10): la testata della pagina Quote Serie A del design v3.
 *
 * Titolo H1, riga con stagione e bookmaker, schede Partite / Risultati / Classifica.
 * Partite = Tabella partite del campionato; Risultati = partite finite negli ultimi 7 giorni;
 * Classifica = classifica di API-Football (Operatori > Dati calcio).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'betnero/quote-campionato', array(
		'title'           => 'Quote campionato',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-quote',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false, 'align' => array( 'wide' ) ),
		'attributes'      => array(
			'campionato' => array( 'type' => 'string', 'default' => 'italy-serie-a' ),
			'classifica' => array( 'type' => 'number', 'default' => 135 ),
			'titolo'     => array( 'type' => 'string', 'default' => '' ),
			'giorni'     => array( 'type' => 'number', 'default' => 7 ),
			'className'  => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_league_block',
	) );
}, 22 );

/* Il tema mette autore e data dopo il primo H1 del contenuto: qui finirebbero nella testata. */
add_filter( 'render_block_core/post-content', function ( $html ) {
	return ( is_singular() && has_block( 'betnero/quote-campionato', get_queried_object_id() ) ) ? preg_replace( '#<p class="bn-byline">.*?</p>#s', '', $html, 1 ) : $html;
}, 30 );

function bnc_league_block( $a ) {
	$slug   = isset( $a['campionato'] ) ? sanitize_title( $a['campionato'] ) : 'italy-serie-a';
	$cl_id  = isset( $a['classifica'] ) ? (int) $a['classifica'] : 0;
	$giorni = max( 1, min( 14, isset( $a['giorni'] ) ? (int) $a['giorni'] : 7 ) );
	global $wpdb;
	$t     = bnc_odds_table();
	$lname = (string) $wpdb->get_var( $wpdb->prepare( "SELECT league_name FROM {$t} WHERE league_slug = %s ORDER BY start DESC LIMIT 1", $slug ) ); // phpcs:ignore WordPress.DB
	$label = '' !== $lname ? bnc_odds_league_label( $lname ) : 'campionato';
	$titolo = ! empty( $a['titolo'] ) ? sanitize_text_field( $a['titolo'] ) : 'Quote ' . $label;
	$words = preg_split( '/\s+/', $label );
	$badge = strtoupper( substr( $words[0], 0, 1 ) . ( isset( $words[1] ) ? substr( $words[1], 0, 1 ) : substr( $words[0], 1, 1 ) ) );

	// Riga sotto il titolo: stagione (dalla classifica), bookmaker, ultimo aggiornamento.
	$meta = array();
	if ( false !== strpos( $lname, ' - ' ) ) {
		$meta[] = strtok( $lname, ' ' ) === 'Italy' ? 'Italia' : trim( substr( $lname, 0, strpos( $lname, ' - ' ) ) );
	}
	$all = get_option( 'bnc_fd_standings', array() );
	if ( $cl_id && isset( $all[ $cl_id ]['stagione'] ) ) {
		$se     = (int) $all[ $cl_id ]['stagione'];
		$meta[] = 'Stagione ' . $se . '/' . ( $se + 1 );
	}
	$nb  = count( bnc_odds_shown_books() );
	$upd = (string) $wpdb->get_var( $wpdb->prepare( "SELECT MAX(odds_updated) FROM {$t} WHERE league_slug = %s", $slug ) ); // phpcs:ignore WordPress.DB
	$meta[] = 'quote di ' . $nb . ' bookmaker ADM' . ( $upd ? ', aggiornate alle ' . wp_date( 'd/m H:i', strtotime( $upd . ' UTC' ) ) : '' );

	$partite = bnc_odds_table_render( array( 'modo' => 'auto', 'campionato' => $slug, 'giorni' => $giorni, 'risultati' => false, 'titolo' => 'Partite' ) );
	$risult  = bnc_league_results( $slug );
	$classif = ( $cl_id && function_exists( 'bnc_fd_standings_render' ) ) ? bnc_fd_standings_render( $cl_id ) : '';

	wp_enqueue_script( 'betnero-partita' );
	$tabs = array( 'partite' => array( 'Partite', $partite ), 'risultati' => array( 'Risultati', $risult ), 'classifica' => array( 'Classifica', $classif ) );
	$h    = '<section class="bnqc" data-bnqc><div class="bnqc-head"><div class="bnqc-top"><span class="bnqc-badge" aria-hidden="true">' . esc_html( $badge ) . '</span><div class="bnqc-tt"><h1>' . esc_html( $titolo ) . '</h1><span>' . esc_html( implode( ' · ', $meta ) ) . '</span></div></div>';
	$h   .= '<div class="bnqc-tabs" role="tablist">';
	$first = true;
	foreach ( $tabs as $k => $tb ) {
		if ( '' === $tb[1] ) {
			continue;
		}
		$h    .= '<button type="button" role="tab" id="bnqc-t-' . esc_attr( $k ) . '" aria-controls="bnqc-p-' . esc_attr( $k ) . '" aria-selected="' . ( $first ? 'true' : 'false' ) . '" data-bnqc-tab="' . esc_attr( $k ) . '">' . esc_html( $tb[0] ) . '</button>';
		$first = false;
	}
	$h    .= '</div></div>';
	$first = true;
	foreach ( $tabs as $k => $tb ) {
		if ( '' === $tb[1] ) {
			continue;
		}
		$h    .= '<div class="bnqc-panel" role="tabpanel" id="bnqc-p-' . esc_attr( $k ) . '" aria-labelledby="bnqc-t-' . esc_attr( $k ) . '"' . ( $first ? '' : ' hidden' ) . '>' . $tb[1] . '</div>';
		$first = false;
	}
	return $h . '</section>';
}

/** Partite finite negli ultimi 7 giorni, dalla copia locale delle quote. */
function bnc_league_results( $slug ) {
	global $wpdb;
	$t    = bnc_odds_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE league_slug = %s AND status = 'settled' AND score <> '' AND start > %s ORDER BY start DESC LIMIT 20", $slug, gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $rows ) {
		return '';
	}
	$h = '<div class="bnqc-res">';
	foreach ( $rows as $r ) {
		$sc = explode( '-', $r['score'], 2 );
		$h .= '<div class="bnqc-res-row"><span>' . esc_html( wp_date( 'd/m', strtotime( $r['start'] . ' UTC' ) ) ) . ' Finale</span><strong>' . esc_html( bnc_odds_team( $r['home'] ) . ' – ' . bnc_odds_team( $r['away'] ) ) . '</strong><b>' . esc_html( trim( $sc[0] ) . ' – ' . trim( isset( $sc[1] ) ? $sc[1] : '' ) ) . '</b></div>';
	}
	return $h . '</div>';
}
