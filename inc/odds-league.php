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
			'completa'   => array( 'type' => 'boolean', 'default' => true ),
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
	$h .= '</section>';
	if ( isset( $a['completa'] ) && ! $a['completa'] ) {
		return $h;
	}
	return bnc_league_page( $h, $slug, $label, $giorni );
}

/**
 * 0.9.11: pagina completa come nel design v3, senza comporla nell'editor.
 * Box BONUS, schede, quote maggiorate e piu' alte, bonus a confronto, FAQ; colonna laterale.
 */
function bnc_league_page( $head, $slug, $label, $giorni ) {
	wp_enqueue_style( 'betnero-tables' );
	$ops  = function_exists( 'bnc_bonus_slider' ) ? array_slice( bnc_operators( 'scommesse' ), 0, 10 ) : array();
	$feat = function_exists( 'bnc_featured_render' ) ? bnc_featured_render( array( 'limite' => 3 ) ) : '';
	$main = '';
	if ( $ops ) {
		$main .= '<a class="bnm-bonusbox" href="#confronto"><span class="bnm-bb-k">BONUS</span><span class="bnm-bb-t"><strong>' . esc_html( 'Scommetti sulla ' . $label . ' con i bonus di benvenuto' ) . '</strong><span>Confronta i bonus dei bookmaker ADM che usiamo per le quote</span></span><span class="bnm-bb-go">Confronta i bonus &darr;</span></a>';
	}
	$main .= $head;
	$main .= bnc_boost_render( bnc_boost_active( 0, 5 ), 'Quote maggiorate' );
	$main .= bnc_top_render( array( 'giorni' => min( 7, $giorni ), 'limite' => 5, 'campionato' => $slug, 'titolo' => 'Le quote più alte della ' . $label ) );
	$main .= '' !== $feat ? '<div class="bnm-only-mobile">' . $feat . '</div>' : '';
	if ( $ops ) {
		$main .= bnc_bonus_slider( $ops, 'Confronta i bonus dei bookmaker per la ' . $label, 'confronto' );
	}
	$main .= bnc_league_faq( $slug, $label );

	$h  = '<div class="bnm-page bnqc-page">';
	$h .= $ops ? '<div class="bnm-only-mobile">' . bnc_bonus_slider( $ops, 'I migliori bookmaker', '' ) . '</div>' : '';
	$h .= '<div class="bnm-cols"><div class="bnm-main bnqc-main">' . $main . '</div>';
	$h .= '<aside class="bnm-aside" aria-label="Colonna laterale"><div class="bnm-only-desktop">' . bnc_side_bonus_render( array( 'limite' => 10 ) ) . $feat . '</div>' . bnc_latest_tips_render( array() ) . '</aside></div></div>';
	return $h;
}

/** Domande frequenti della pagina campionato, con le risposte prese dai dati. */
function bnc_league_faq( $slug, $label ) {
	global $wpdb;
	$t     = bnc_odds_table();
	$n     = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$t} WHERE league_slug = %s AND status = 'pending' AND start > %s", $slug, gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB
	$books = array_map( function ( $op ) {
		return bnc_odds_book_label( $op['name'] );
	}, array_values( bnc_odds_shown_books() ) );
	$qa    = array(
		array( 'Dove trovo le quote più alte per la ' . $label . '?', 'Nella scheda Partite: per ogni partita mostriamo la quota più alta di ogni esito tra i nostri bookmaker' . ( $n ? ' (ora ' . $n . ' partite in programma)' : '' ) . '. Il pulsante "Quote" apre tutti i mercati della partita.' ),
		array( 'Ogni quanto si aggiornano le quote?', 'In automatico ogni 10 minuti fino al fischio d\'inizio.' ),
		array( 'Quali bookmaker confrontate?', $books ? 'Solo bookmaker con concessione ADM: ' . implode( ', ', $books ) . '.' : 'Solo bookmaker con concessione ADM.' ),
		array( 'Che differenza c\'è tra quota normale e quota maggiorata?', 'La quota maggiorata è un\'offerta del bookmaker su un esito preciso, più alta del normale e valida fino a una scadenza. Le quote più alte sono invece quote normali sopra la media degli altri bookmaker.' ),
		array( 'Dove trovo la classifica della ' . $label . '?', 'Nella scheda Classifica, con la forma delle ultime 5 partite. I dati si aggiornano due volte al giorno.' ),
	);
	$h = '<section class="bnm-faq"><h2 class="bnm-h2">Domande frequenti sulle quote ' . esc_html( $label ) . '</h2>';
	foreach ( $qa as $i => $x ) {
		$h .= '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $x[0] ) . '</summary><p>' . esc_html( $x[1] ) . '</p></details>';
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
