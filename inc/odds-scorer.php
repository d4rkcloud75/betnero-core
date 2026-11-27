<?php
/**
 * Blocco "Marcatori" (0.9.30): i giocatori con la quota "segna in qualsiasi momento".
 *
 * 0.9.30: il mercato Anytime Goalscorer arriva da odds-api.io insieme agli altri
 * (BNC_ODDS_MARKETS in inc/odds.php) e si legge con bnc_odds_labelled().
 * Una riga per giocatore con la partita in piccolo, la quota e il pulsante Gioca,
 * ordinati dalla quota più bassa, per un solo bookmaker scelto nel blocco (di serie Eurobet).
 * Legge solo la copia locale delle quote: nessuna richiesta in più a odds-api.io.
 * Senza dati il blocco non stampa niente sul sito (nell'editor un avviso).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BNC_SCORER_MARKET', 'Anytime Goalscorer' );

add_action( 'init', function () {
	register_block_type( 'betnero/marcatori', array(
		'title'           => 'Marcatori',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-quote',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false ),
		'attributes'      => array(
			'campionato' => array( 'type' => 'string', 'default' => '' ),
			'bookmaker'  => array( 'type' => 'string', 'default' => '' ),
			'giorni'     => array( 'type' => 'number', 'default' => 7 ),
			'limite'     => array( 'type' => 'number', 'default' => 5 ),
			'titolo'     => array( 'type' => 'string', 'default' => '' ),
			'className'  => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_scorer_block',
	) );
}, 24 );

/** Attributi puliti, gli stessi per il blocco e per la pagina campionato. */
function bnc_scorer_attrs( $a ) {
	$a = is_array( $a ) ? $a : array();
	return array(
		'campionato' => isset( $a['campionato'] ) ? sanitize_title( $a['campionato'] ) : '',
		'bookmaker'  => isset( $a['bookmaker'] ) ? sanitize_text_field( $a['bookmaker'] ) : '',
		'giorni'     => max( 1, min( 14, isset( $a['giorni'] ) ? (int) $a['giorni'] : 7 ) ),
		'limite'     => max( 1, min( 30, isset( $a['limite'] ) ? (int) $a['limite'] : 5 ) ),
		'titolo'     => isset( $a['titolo'] ) ? sanitize_text_field( $a['titolo'] ) : '',
	);
}

/**
 * Bookmaker del blocco: quello scelto (nome API o nome corto), altrimenti Eurobet,
 * altrimenti il primo dei bookmaker mostrati. Restituisce [ nome API, operatore ].
 */
function bnc_scorer_book( $name ) {
	$books = bnc_odds_shown_books();
	if ( ! $books ) {
		return array( '', null );
	}
	$name = trim( (string) $name );
	if ( '' !== $name ) {
		foreach ( $books as $n => $op ) {
			if ( 0 === strcasecmp( $n, $name ) || 0 === strcasecmp( bnc_odds_book_label( $op['name'] ), $name ) ) {
				return array( (string) $n, $op );
			}
		}
		return array( '', null ); // scelto ma non più tra i bookmaker mostrati
	}
	foreach ( $books as $n => $op ) {
		if ( false !== stripos( $n, 'eurobet' ) || false !== stripos( $op['name'], 'eurobet' ) ) {
			return array( (string) $n, $op );
		}
	}
	$names = array_keys( $books );
	$n     = (string) $names[0];
	return array( $n, $books[ $n ] );
}

/**
 * Giocatori con la quota "segna in qualsiasi momento" nelle prossime partite,
 * ordinati dalla quota più bassa. Restituisce [ righe, operatore ].
 */
function bnc_scorer_find( $a ) {
	list( $book, $op ) = bnc_scorer_book( $a['bookmaker'] );
	if ( ! $op ) {
		return array( array(), null );
	}
	global $wpdb;
	$t    = bnc_odds_table();
	$now  = time();
	$sql  = "SELECT * FROM {$t} WHERE status = 'pending' AND start > %s AND start <= %s AND odds IS NOT NULL";
	$args = array( gmdate( 'Y-m-d H:i:s', $now ), gmdate( 'Y-m-d H:i:s', $now + $a['giorni'] * DAY_IN_SECONDS ) );
	if ( '' !== $a['campionato'] ) {
		$sql   .= ' AND league_slug = %s';
		$args[] = $a['campionato'];
	}
	$rows = $wpdb->get_results( $wpdb->prepare( $sql . ' ORDER BY start ASC LIMIT 60', $args ), ARRAY_A ); // phpcs:ignore WordPress.DB
	$out  = array();
	foreach ( (array) $rows as $r ) {
		$odds = json_decode( (string) $r['odds'], true );
		$odds = is_array( $odds ) ? $odds : array();
		$list = bnc_odds_labelled( isset( $odds[ $book ] ) ? $odds[ $book ] : array(), BNC_SCORER_MARKET );
		foreach ( $list as $player => $price ) {
			$out[] = array(
				'player' => (string) $player,
				'price'  => (float) $price,
				'home'   => bnc_odds_team( $r['home'] ),
				'away'   => bnc_odds_team( $r['away'] ),
				'start'  => (string) $r['start'],
			);
		}
	}
	usort( $out, function ( $x, $y ) {
		return $x['price'] <=> $y['price'];
	} );
	return array( array_slice( $out, 0, $a['limite'] ), $op );
}

function bnc_scorer_render( $a ) {
	$a = bnc_scorer_attrs( $a );
	list( $items, $op ) = bnc_scorer_find( $a );
	if ( ! $op || ! $items ) {
		return '';
	}
	$label  = bnc_odds_book_label( $op['name'] );
	$titolo = '' !== $a['titolo'] ? $a['titolo'] : 'Marcatori ' . $label;
	$h      = '<div class="bnq bnq-marc"><p class="bnq-head">' . esc_html( $titolo ) . '</p><div class="bnq-ml">';
	foreach ( $items as $it ) {
		$h .= '<div class="bnq-mr"><div class="bnq-mp"><strong>' . esc_html( $it['player'] ) . '</strong>';
		$h .= '<span>' . esc_html( $it['home'] . ' - ' . $it['away'] . ', ' . wp_date( 'j/m H:i', strtotime( $it['start'] . ' UTC' ) ) ) . '</span></div>';
		$h .= '<span class="bnq-mq">' . esc_html( bnc_odds_fmt( $it['price'] ) ) . '</span>';
		$h .= '<div class="bnq-mgo">' . bnc_go_button( $op, 'Gioca', 'bnt-btn bnt-go' ) . '</div></div>';
	}
	$h .= '</div><p class="bnq-note">' . esc_html( 'Quota "segna in qualsiasi momento" di ' . $op['name'] . ' per le prossime partite' . ( '' !== $a['campionato'] ? ' del campionato' : '' ) . '. Quota migliore per giocatore, aggiornata ogni 10 minuti.' ) . '</p>';
	return $h . '<p class="bnq-adm">18+ | Gioca responsabilmente | Le quote possono variare</p></div>';
}

function bnc_scorer_block( $a ) {
	$a     = bnc_scorer_attrs( $a );
	$inner = bnc_scorer_render( $a );
	if ( '' === $inner ) {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$nome = '' !== $a['bookmaker'] ? $a['bookmaker'] : 'Eurobet';
			return '<p class="bnq-empty">' . esc_html( 'Nessuna quota "segna in qualsiasi momento" di ' . $nome . ' nelle prossime partite. Il mercato arriva da odds-api.io: se il bookmaker non lo offre per queste partite, il blocco resta vuoto anche sul sito.' ) . '</p>';
		}
		return '';
	}
	return $inner;
}

/* Per l'editor: i bookmaker delle quote da scegliere nel blocco. */
add_action( 'rest_api_init', function () {
	register_rest_route( 'betnero/v1', '/quote/bookmakers', array(
		'methods'             => 'GET',
		'permission_callback' => function () {
			return current_user_can( 'edit_posts' );
		},
		'callback'            => function () {
			$out = array();
			foreach ( bnc_odds_shown_books() as $name => $op ) {
				$out[] = array(
					'value' => (string) $name,
					'label' => bnc_odds_book_label( $op['name'] ),
				);
			}
			return $out;
		},
	) );
} );
