<?php
/**
 * Blocco "Quote più alte" (0.9.0): l'alternativa automatica alle quote maggiorate.
 *
 * Tra le partite in programma dei campionati seguiti trova gli esiti in cui uno dei nostri
 * bookmaker paga molto piu' della media degli altri (soglia in %, di serie 5%).
 * Non sono quote maggiorate e il blocco lo dice: sono le quote normali piu' convenienti.
 * Legge solo la copia locale (nessuna richiesta in piu' a odds-api.io).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'betnero/quote-top', array(
		'title'           => 'Quote più alte',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-quote',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false ),
		'attributes'      => array(
			'giorni'     => array( 'type' => 'number', 'default' => 2 ),
			'limite'     => array( 'type' => 'number', 'default' => 5 ),
			'soglia'     => array( 'type' => 'number', 'default' => 5 ),
			'campionato' => array( 'type' => 'string', 'default' => '' ),
			'titolo'     => array( 'type' => 'string', 'default' => '' ),
			'className'  => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_top_block',
	) );
}, 23 );

function bnc_top_attrs( $a ) {
	$a = is_array( $a ) ? $a : array();
	return array(
		'giorni'     => max( 1, min( 7, isset( $a['giorni'] ) ? (int) $a['giorni'] : 2 ) ),
		'limite'     => max( 1, min( 15, isset( $a['limite'] ) ? (int) $a['limite'] : 5 ) ),
		'soglia'     => max( 1, min( 50, isset( $a['soglia'] ) ? (int) $a['soglia'] : 5 ) ),
		'campionato' => isset( $a['campionato'] ) ? sanitize_title( $a['campionato'] ) : '',
		'titolo'     => isset( $a['titolo'] ) ? sanitize_text_field( $a['titolo'] ) : '',
	);
}

/**
 * Esiti piu' convenienti. Per ogni partita ed esito: quota migliore tra i bookmaker mostrati,
 * media degli altri (almeno 3 bookmaker con quota), scarto in %. Una sola riga per partita
 * (la piu' conveniente), ordinate per scarto.
 */
function bnc_top_find( $a ) {
	global $wpdb;
	$ck    = 'bnc_top_' . md5( wp_json_encode( $a ) );
	$cache = get_transient( $ck );
	if ( is_array( $cache ) ) {
		return $cache;
	}
	$t     = bnc_odds_table();
	$sql   = "SELECT * FROM {$t} WHERE status = 'pending' AND start > %s AND start <= %s AND odds_updated IS NOT NULL";
	$args  = array( gmdate( 'Y-m-d H:i:s', time() + 10 * MINUTE_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() + $a['giorni'] * DAY_IN_SECONDS ) );
	if ( '' !== $a['campionato'] ) {
		$sql   .= ' AND league_slug = %s';
		$args[] = $a['campionato'];
	}
	$rows  = $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY start ASC LIMIT 300', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$books = bnc_odds_shown_books();
	$out   = array();
	foreach ( $rows as $r ) {
		$ev = array( 'start' => $r['start'], 'status' => $r['status'], 'odds_updated' => $r['odds_updated'] );
		if ( bnc_odds_stale( $ev ) ) {
			continue; // quote ferme da piu' di 3 ore: meglio non segnalarle
		}
		$odds = json_decode( (string) $r['odds'], true );
		$odds = is_array( $odds ) ? $odds : array();
		$best_row = null;
		foreach ( bnc_odds_markets() as $mk => $m ) {
			foreach ( $m['out'] as $ok => $ol ) {
				$prices = array();
				foreach ( $books as $name => $op ) {
					$p = bnc_odds_price( isset( $odds[ $name ] ) ? $odds[ $name ] : array(), $mk, (string) $ok );
					if ( $p > 1 ) {
						$prices[ $name ] = $p;
					}
				}
				if ( count( $prices ) < 3 ) {
					continue;
				}
				arsort( $prices );
				$top_name = key( $prices );
				$top      = reset( $prices );
				$others   = array_slice( $prices, 1 );
				$avg      = array_sum( $others ) / count( $others );
				$gap      = $avg > 0 ? ( $top / $avg - 1 ) * 100 : 0;
				if ( $gap >= $a['soglia'] && ( ! $best_row || $gap > $best_row['gap'] ) ) {
					$best_row = array(
						'id'     => (int) $r['id'],
						'home'   => $r['home'],
						'away'   => $r['away'],
						'lega'   => $r['league_name'],
						'start'  => $r['start'],
						'label'  => $m['label'] . ': ' . $ol,
						'top'    => $top,
						'avg'    => $avg,
						'gap'    => $gap,
						'book'   => $top_name,
						'op_id'  => (int) $books[ $top_name ]['id'],
						'n'      => count( $prices ),
						'agg'    => $r['odds_updated'],
					);
				}
			}
		}
		if ( $best_row ) {
			$out[] = $best_row;
		}
	}
	usort( $out, function ( $x, $y ) {
		return $y['gap'] <=> $x['gap'];
	} );
	$out = array_slice( $out, 0, $a['limite'] );
	set_transient( $ck, $out, 5 * MINUTE_IN_SECONDS );
	return $out;
}

function bnc_top_render( $a ) {
	$a     = bnc_top_attrs( $a );
	$items = bnc_top_find( $a );
	if ( ! $items ) {
		return '';
	}
	$titolo = '' !== $a['titolo'] ? $a['titolo'] : 'Le quote più alte ' . ( 1 === $a['giorni'] ? 'di oggi' : 'dei prossimi giorni' );
	$h      = '<div class="bnq bnq-boost bnq-top"><p class="bnq-title">' . esc_html( $titolo ) . '</p><div class="bnq-bl">';
	$latest = 0;
	foreach ( $items as $it ) {
		$op = bnc_operator( $it['op_id'] );
		$h .= '<div class="bnq-bi"><div class="bnq-logo">' . bnc_logo( $op ) . '</div><div class="bnq-bt">';
		$h .= '<strong>' . esc_html( bnc_odds_team( $it['home'] ) . ' - ' . bnc_odds_team( $it['away'] ) ) . '</strong>';
		$h .= '<span>' . esc_html( $it['label'] ) . ' &middot; ' . esc_html( bnc_odds_league_label( $it['lega'] ) . ', ' . wp_date( 'j/m H:i', strtotime( $it['start'] . ' UTC' ) ) ) . '</span>';
		$h .= '<span class="bnq-bs">' . esc_html( sprintf( 'Media degli altri %d bookmaker: %s', $it['n'] - 1, bnc_odds_fmt( $it['avg'] ) ) ) . '</span></div>';
		$h .= '<div class="bnq-bq"><span class="bnq-gap">+' . esc_html( number_format_i18n( $it['gap'], 0 ) ) . '%</span><strong>' . esc_html( bnc_odds_fmt( $it['top'] ) ) . '</strong></div>';
		$h .= '<div class="bnq-bgo">' . bnc_go_button( $op, 'Vai al sito', 'bnt-btn bnt-go' ) . '</div></div>';
		$latest = max( $latest, strtotime( $it['agg'] . ' UTC' ) );
	}
	$note = 'Quote normali (non maggiorate) più alte della media dei nostri bookmaker' . ( $latest ? ', aggiornate alle ' . wp_date( 'd/m H:i', $latest ) : '' ) . '.';
	return $h . '</div><p class="bnq-note">' . esc_html( $note ) . '</p><p class="bnq-adm">18+ | Gioca responsabilmente | Le quote possono variare</p></div>';
}

function bnc_top_block( $a ) {
	$a     = bnc_top_attrs( $a );
	$inner = bnc_top_render( $a );
	if ( '' === $inner ) {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? '<p class="bnq-empty">Nessuna quota sopra la media di almeno il ' . (int) $a['soglia'] . '% nelle partite in programma. Prova ad abbassare la soglia o ad allargare i giorni.</p>' : '';
	}
	wp_enqueue_script( 'betnero-quote' );
	return '<div class="bnq-live" data-top="' . esc_attr( wp_json_encode( $a ) ) . '" data-url="' . esc_url( rest_url( 'betnero/v1/quote/top' ) ) . '">' . $inner . '</div>';
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'betnero/v1', '/quote/top', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array( 'a' => array( 'type' => 'string', 'required' => true ) ),
		'callback'            => function ( $req ) {
			$res = rest_ensure_response( array( 'html' => bnc_top_render( json_decode( (string) $req['a'], true ) ) ) );
			$res->header( 'Cache-Control', 'public, max-age=120' );
			return $res;
		},
	) );
} );
