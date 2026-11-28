<?php
/**
 * Blocco "Tabella partite" (0.7.0): piu' partite con le quote 1X2 migliori.
 *
 * - Scelta automatica: un campionato seguito e i prossimi N giorni.
 * - Scelta a mano: le partite spuntate nel blocco.
 * Legge solo la copia locale delle quote (nessuna richiesta in piu' a odds-api.io)
 * e si rinfresca via REST dopo il caricamento, come gli altri blocchi delle quote.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	register_block_type( 'betnero/tabella-partite', array(
		'title'           => 'Tabella partite',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-quote',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false, 'align' => array( 'wide' ) ),
		'attributes'      => array(
			'modo'       => array( 'type' => 'string', 'default' => 'auto' ),
			'campionato' => array( 'type' => 'string', 'default' => '' ),
			'giorni'     => array( 'type' => 'number', 'default' => 7 ),
			'partite'    => array( 'type' => 'array', 'default' => array(), 'items' => array( 'type' => 'number' ) ),
			'risultati'  => array( 'type' => 'boolean', 'default' => true ),
			'titolo'     => array( 'type' => 'string', 'default' => '' ),
			'align'      => array( 'type' => 'string' ),
			'className'  => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_odds_table_block',
	) );
}, 21 );

/** Attributi puliti, gli stessi per il blocco e per il REST. */
function bnc_odds_table_attrs( $a ) {
	$a = is_array( $a ) ? $a : array();
	return array(
		'modo'       => ( isset( $a['modo'] ) && 'manuale' === $a['modo'] ) ? 'manuale' : 'auto',
		'campionato' => isset( $a['campionato'] ) ? sanitize_title( $a['campionato'] ) : '',
		'giorni'     => max( 1, min( 14, isset( $a['giorni'] ) ? (int) $a['giorni'] : 7 ) ),
		'partite'    => array_slice( array_values( array_filter( array_map( 'absint', isset( $a['partite'] ) ? (array) $a['partite'] : array() ) ) ), 0, 40 ),
		'risultati'  => ! isset( $a['risultati'] ) || (bool) $a['risultati'],
		'titolo'     => isset( $a['titolo'] ) ? sanitize_text_field( $a['titolo'] ) : '',
	);
}

/** Partite della tabella, dalla copia locale. */
function bnc_odds_table_rows( $a ) {
	global $wpdb;
	$t    = bnc_odds_table();
	$from = gmdate( 'Y-m-d H:i:s', time() - ( $a['risultati'] ? DAY_IN_SECONDS : 3 * HOUR_IN_SECONDS ) );
	if ( 'manuale' === $a['modo'] ) {
		if ( ! $a['partite'] ) {
			return array();
		}
		$in = implode( ',', array_map( 'intval', $a['partite'] ) );
		return $wpdb->get_results( "SELECT * FROM {$t} WHERE id IN ({$in}) ORDER BY start ASC", ARRAY_A ); // phpcs:ignore WordPress.DB
	}
	if ( '' === $a['campionato'] ) {
		return array();
	}
	$to = gmdate( 'Y-m-d H:i:s', time() + $a['giorni'] * DAY_IN_SECONDS );
	return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE league_slug = %s AND start >= %s AND start <= %s AND status <> 'cancelled' ORDER BY start ASC LIMIT 60", $a['campionato'], $from, $to ), ARRAY_A ); // phpcs:ignore WordPress.DB
}

function bnc_odds_table_render( $a ) {
	$a    = bnc_odds_table_attrs( $a );
	$rows = bnc_odds_table_rows( $a );
	if ( ! $rows ) {
		return '';
	}
	$books  = bnc_odds_shown_books();
	$outs   = array( '1' => '1', 'X' => 'X', '2' => '2' );
	$titolo = '' !== $a['titolo'] ? $a['titolo'] : ( 'auto' === $a['modo'] ? 'Quote ' . bnc_odds_league_label( $rows[0]['league_name'] ) : 'Quote delle partite' );
	$h      = '<div class="bnq bnq-tab"><p class="bnq-title">' . esc_html( $titolo ) . '</p>';
	$day    = '';
	$latest = 0;
	foreach ( $rows as $r ) {
		$r['odds'] = json_decode( (string) $r['odds'], true );
		$r['odds'] = is_array( $r['odds'] ) ? $r['odds'] : array();
		$ts        = strtotime( $r['start'] . ' UTC' );
		$d         = wp_date( 'l j F', $ts );
		if ( $d !== $day ) {
			$h  .= ( '' !== $day ? '</div>' : '' ) . '<div class="bnq-day"><p class="bnq-day-t">' . esc_html( ucfirst( $d ) ) . '</p>';
			$day = $d;
		}
		$started = 'pending' !== $r['status'] || $ts <= time();
		$stale   = 2 === bnc_odds_stale( array( 'start' => $r['start'], 'status' => $r['status'], 'odds_updated' => $r['odds_updated'] ) );
		if ( 'settled' === $r['status'] && '' !== $r['score'] ) {
			$when = '<span class="bnq-st is-ft">Finale</span>';
		} elseif ( $started ) {
			$when = '<span class="bnq-st is-live">In corso</span>';
		} else {
			$when = '<span class="bnq-st">' . esc_html( wp_date( 'H:i', $ts ) ) . '</span>';
		}
		$score = array( '', '' );
		if ( '' !== $r['score'] && false !== strpos( $r['score'], '-' ) ) {
			$score = explode( '-', $r['score'], 2 );
		}
		$h .= '<div class="bnq-mt">' . $when . '<div class="bnq-teams2"><span>' . esc_html( bnc_odds_team( $r['home'] ) ) . '</span><span>' . esc_html( bnc_odds_team( $r['away'] ) ) . '</span></div>';
		$h .= '<div class="bnq-sc">' . ( 'settled' === $r['status'] ? '<b>' . esc_html( trim( $score[0] ) ) . '</b><b>' . esc_html( trim( $score[1] ) ) . '</b>' : '' ) . '</div><div class="bnq-o3">';
		foreach ( $outs as $ok => $ol ) {
			$best = 0.0;
			$bop  = null;
			if ( ! $stale ) {
				foreach ( $books as $name => $op ) {
					$p = bnc_odds_price( isset( $r['odds'][ $name ] ) ? $r['odds'][ $name ] : array(), '1x2', $ok );
					if ( $p > $best ) {
						$best = $p;
						$bop  = $op;
					}
				}
			}
			$inner = '<span class="bnq-ol">' . esc_html( $ol ) . '</span><strong>' . esc_html( bnc_odds_fmt( $best ) ) . '</strong>';
			if ( $bop && ! $started && ! empty( $bop['go_url'] ) ) {
				// 0.9.29: sotto la quota il logo del bookmaker (mail di Francesco del 27/11); senza logo resta il nome.
				$blogo  = ( ! empty( $bop['logo_id'] ) || ! empty( $bop['logo_url'] ) ) ? bnc_logo( $bop ) : '';
				$inner .= '' !== $blogo ? '<span class="bnq-bk is-logo">' . $blogo . '</span>' : '<span class="bnq-bk">' . esc_html( bnc_odds_book_label( $bop['name'] ) ) . '</span>';
				$h     .= '<a class="bnq-oc" href="' . esc_url( $bop['go_url'] ) . '" rel="nofollow sponsored noopener"' . ( ! empty( $bop['go_blank'] ) ? ' target="_blank"' : '' ) . ' title="' . esc_attr( 'Quota ' . $ol . ' su ' . $bop['name'] ) . '">' . $inner . '</a>';
			} else {
				$h .= '<span class="bnq-oc is-off">' . $inner . '<span class="bnq-bk">&nbsp;</span></span>';
			}
		}
		$h .= '</div>';
		// 0.9.5: "Quote e pronostico" se c'e' l'articolo, altrimenti "Quote" (pagina partita).
		$pid = function_exists( 'bnc_match_post' ) ? bnc_match_post( (int) $r['id'] ) : 0;
		if ( $pid ) {
			$h .= '<a class="bnq-go is-tip" href="' . esc_url( get_permalink( $pid ) ) . '">Quote e pronostico</a>';
		} elseif ( function_exists( 'bnc_match_url' ) && ! $started && ! bnc_match_expired( $r ) ) {
			// 0.9.31: niente "Quote" verso una pagina partita che risponderebbe 410.
			$h .= '<a class="bnq-go" href="' . esc_url( bnc_match_url( $r ) ) . '">Quote</a>';
		} else {
			$h .= '<span class="bnq-go is-off"></span>';
		}
		$h .= '</div>';
		if ( $r['odds_updated'] ) {
			$latest = max( $latest, strtotime( $r['odds_updated'] . ' UTC' ) );
		}
	}
	$h .= '</div>';
	$note = $latest ? 'Quote migliori tra i nostri bookmaker, aggiornate alle ' . wp_date( 'd/m H:i', $latest ) . '. Tocca una quota per andare sul sito. "Quote e pronostico" apre l\'articolo con le quote; "Quote" apre la pagina della partita con tutti i mercati.' : '';
	return $h . ( $note ? '<p class="bnq-note">' . esc_html( $note ) . '</p>' : '' ) . '<p class="bnq-adm">18+ | Gioca responsabilmente | Le quote possono variare</p></div>';
}

function bnc_odds_table_block( $a ) {
	$a     = bnc_odds_table_attrs( $a );
	$inner = bnc_odds_table_render( $a );
	if ( '' === $inner ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return '<p class="bnq-empty">' . ( 'manuale' === $a['modo'] ? 'Spunta le partite nelle impostazioni del blocco (a destra).' : ( '' === $a['campionato'] ? 'Scegli il campionato nelle impostazioni del blocco (a destra).' : 'Nessuna partita in questo periodo, oppure campionato non ancora letto in Operatori &gt; Quote.' ) ) . '</p>';
		}
		return '';
	}
	wp_enqueue_script( 'betnero-quote' );
	return '<div class="bnq-live" data-tab="' . esc_attr( wp_json_encode( $a ) ) . '" data-url="' . esc_url( rest_url( 'betnero/v1/quote/tabella' ) ) . '">' . $inner . '</div>';
}

add_action( 'rest_api_init', function () {
	// Tabella aggiornata dopo il caricamento della pagina (pubblico, come i dati gia' nella pagina).
	register_rest_route( 'betnero/v1', '/quote/tabella', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array( 'a' => array( 'type' => 'string', 'required' => true ) ),
		'callback'            => function ( $req ) {
			$a   = json_decode( (string) $req['a'], true );
			$res = rest_ensure_response( array( 'html' => bnc_odds_table_render( $a ) ) );
			$res->header( 'Cache-Control', 'public, max-age=120' );
			return $res;
		},
	) );
	// Per l'editor: campionati seguiti e partite in programma da spuntare.
	register_rest_route( 'betnero/v1', '/quote/partite', array(
		'methods'             => 'GET',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => function () {
			global $wpdb;
			$t    = bnc_odds_table();
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, league_slug, league_name, home, away, start FROM {$t} WHERE start > %s AND status IN ('pending','live') ORDER BY start ASC LIMIT 500", gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
			$out  = array( 'campionati' => array(), 'partite' => array() );
			foreach ( (array) bnc_odds_settings()['leagues'] as $slug ) {
				$out['campionati'][ $slug ] = $slug;
			}
			foreach ( $rows as $r ) {
				$out['campionati'][ $r['league_slug'] ] = bnc_odds_league_label( $r['league_name'] );
				$out['partite'][] = array(
					'id'    => (int) $r['id'],
					'label' => wp_date( 'd/m H:i', strtotime( $r['start'] . ' UTC' ) ) . ' ' . bnc_odds_team( $r['home'] ) . ' - ' . bnc_odds_team( $r['away'] ),
					'lega'  => bnc_odds_league_label( $r['league_name'] ),
				);
			}
			return $out;
		},
	) );
} );
