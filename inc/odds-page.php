<?php
/**
 * Design "Quote" versione 3 (0.9.5): pagina partita e blocchi della pagina Quote.
 *
 * - Pagina partita: /quote-partita/<id>-<squadre>/ mostra la pagina WordPress con slug
 *   "quote-partita", dove va messo il blocco "Pagina partita" (piu' colonna laterale a piacere).
 *   Testata con quote migliori 1X2 e probabilita', filtri per gruppo, un blocco per mercato
 *   con i bookmaker in colonna e la quota migliore in verde. I mercati offerti da tutti i
 *   bookmaker stanno in alto; quelli offerti solo da alcuni in "Altri mercati".
 * - Blocchi: Bonus laterale (TOP N), Partite in evidenza, Ultimi pronostici.
 * - Tabella partite: pulsante "Quote e pronostico" (se c'e' l'articolo) o "Quote" (pagina partita).
 *
 * Legge solo la copia locale delle quote: nessuna richiesta in piu' a odds-api.io.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BNC_MATCH_SLUG', 'quote-partita' );

/* ---------- Mercati della pagina partita ---------- */

/** Nome API => titolo e gruppo. L'ordine e' quello della pagina. */
function bnc_match_markets() {
	return array(
		'ML'                  => array( 'Esito finale 1X2', 'principali' ),
		'Double Chance'       => array( 'Doppia chance', 'principali' ),
		'Draw No Bet'         => array( 'Rimborso in caso di pareggio', 'principali' ),
		'Totals'              => array( 'Under/Over gol', 'gol' ),
		'Both Teams To Score' => array( 'Goal/No Goal', 'gol' ),
		'Correct Score'       => array( 'Risultato esatto', 'gol' ),
		'ML HT'               => array( 'Primo tempo: esito 1X2', 'primotempo' ),
		'Totals HT'           => array( 'Primo tempo: Under/Over', 'primotempo' ),
		'Half Time / Full Time' => array( 'Parziale/finale', 'primotempo' ),
		'Spread'              => array( 'Handicap asiatico', 'handicap' ),
		'European Handicap'   => array( 'Handicap', 'handicap' ),
		'Corners'             => array( 'Calci d\'angolo', 'speciali' ),
		'Corners Totals'      => array( 'Calci d\'angolo: Under/Over', 'speciali' ),
		'Bookings Totals'     => array( 'Cartellini: Under/Over', 'speciali' ),
	);
}

function bnc_match_groups() {
	return array(
		'tutti'      => 'Tutti i mercati',
		'principali' => 'Principali',
		'gol'        => 'Gol',
		'primotempo' => 'Primo tempo',
		'handicap'   => 'Handicap',
		'speciali'   => 'Speciali',
	);
}

/** Etichetta di un esito. */
function bnc_match_out_label( $key, $home, $away ) {
	$k   = strtolower( (string) $key );
	$map = array(
		'home'    => '1 · ' . $home,
		'draw'    => 'X · Pareggio',
		'away'    => '2 · ' . $away,
		'over'    => 'Over',
		'under'   => 'Under',
		'exactly' => 'Esatto',
		'yes'     => 'Goal',
		'no'      => 'No Goal',
		'none'    => 'Nessuno',
		'neither' => 'Nessuno',
	);
	if ( isset( $map[ $k ] ) ) {
		return $map[ $k ];
	}
	$dc = bnc_odds_dc_key( $key );
	return '' !== $dc ? $dc : (string) $key;
}

/** Linea (hdp) leggibile: 2.5 diventa "2,5", -1 diventa "-1". */
function bnc_match_line( $hdp, $signed = false ) {
	$v = (float) $hdp;
	return ( $signed && $v > 0 ? '+' : '' ) . str_replace( '.', ',', rtrim( rtrim( number_format( $v, 2, '.', '' ), '0' ), '.' ) );
}

/**
 * Tabelle di tutti i mercati di una partita.
 * Restituisce [ nome API => [ 'books' => [nomi], 'lines' => [ linea => [ esito => [ book => quota ] ] ] ] ].
 */
function bnc_match_tables( $ev, $books ) {
	$out   = array();
	$known = bnc_match_markets();
	foreach ( $books as $bname => $op ) {
		$list = isset( $ev['odds'][ $bname ] ) ? (array) $ev['odds'][ $bname ] : array();
		foreach ( $list as $m ) {
			$name = isset( $m['name'] ) ? (string) $m['name'] : '';
			$kn   = '';
			foreach ( array_keys( $known ) as $k ) {
				if ( 0 === strcasecmp( $k, $name ) ) {
					$kn = $k;
				}
			}
			if ( '' === $kn || empty( $m['odds'] ) ) {
				continue;
			}
			foreach ( (array) $m['odds'] as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$line = isset( $row['hdp'] ) ? (string) (float) $row['hdp'] : '';
				foreach ( $row as $ok => $ov ) {
					if ( in_array( strtolower( (string) $ok ), array( 'hdp', 'updatedat', 'max', 'label' ), true ) || ! is_scalar( $ov ) || (float) $ov <= 1 ) {
						continue;
					}
					$out[ $kn ]['lines'][ $line ][ (string) $ok ][ $bname ] = (float) $ov;
				}
			}
			if ( isset( $out[ $kn ] ) ) {
				$out[ $kn ]['books'][ $bname ] = true;
			}
		}
	}
	$sorted = array();
	foreach ( array_keys( $known ) as $k ) {
		if ( isset( $out[ $k ] ) ) {
			uksort( $out[ $k ]['lines'], function ( $a, $b ) {
				return (float) $a <=> (float) $b;
			} );
			$sorted[ $k ] = $out[ $k ];
		}
	}
	return $sorted;
}

/** Linea mostrata per prima: 2,5 per i gol, altrimenti la piu' equilibrata. */
function bnc_match_main_line( $lines, $name ) {
	$keys = array_keys( $lines );
	if ( 1 === count( $keys ) ) {
		return (string) $keys[0];
	}
	foreach ( array( '2.5', '1.5', '9.5', '4.5', '0', '-0.5', '0.5' ) as $pref ) {
		if ( isset( $lines[ $pref ] ) && ( false !== stripos( $name, 'total' ) || in_array( $pref, array( '0', '-0.5', '0.5' ), true ) ) ) {
			return $pref;
		}
	}
	$best = (string) $keys[0];
	$gap  = INF;
	foreach ( $lines as $l => $outs ) {
		$v = array_values( $outs );
		if ( count( $v ) >= 2 ) {
			$a = $v[0] ? max( $v[0] ) : 0;
			$b = $v[1] ? max( $v[1] ) : 0;
			if ( $a && $b && abs( $a - $b ) < $gap ) {
				$gap  = abs( $a - $b );
				$best = (string) $l;
			}
		}
	}
	return $best;
}

/* ---------- Indirizzi ---------- */

function bnc_match_url( $ev ) {
	$slug = sanitize_title( bnc_odds_team( $ev['home'] ) . '-' . bnc_odds_team( $ev['away'] ) );
	return home_url( '/' . BNC_MATCH_SLUG . '/' . (int) $ev['id'] . '-' . $slug . '/' );
}

/** Articolo pubblicato collegato a una partita (0 se non c'e'). */
function bnc_match_post( $event_id ) {
	static $cache = array();
	$event_id = (int) $event_id;
	if ( ! isset( $cache[ $event_id ] ) ) {
		$ids                = get_posts( array(
			'post_type'   => 'post',
			'post_status' => 'publish',
			'numberposts' => 1,
			'fields'      => 'ids',
			'meta_key'    => '_bn_quote_evento', // phpcs:ignore WordPress.DB.SlowDBQuery
			'meta_value'  => (string) $event_id, // phpcs:ignore WordPress.DB.SlowDBQuery
		) );
		$cache[ $event_id ] = $ids ? (int) $ids[0] : 0;
	}
	return $cache[ $event_id ];
}

add_action( 'init', function () {
	add_rewrite_tag( '%bn_partita%', '([0-9]+)' );
	add_rewrite_rule( '^' . BNC_MATCH_SLUG . '/([0-9]+)(?:-[^/]*)?/?$', 'index.php?pagename=' . BNC_MATCH_SLUG . '&bn_partita=$matches[1]', 'top' );
	if ( get_option( 'bnc_match_rw' ) !== BETNERO_CORE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( 'bnc_match_rw', BETNERO_CORE_VERSION, false );
	}
} );

/**
 * 0.9.6: la regola punta alla pagina con slug "quote-partita" ovunque si trovi
 * (i permalink delle pagine possono essere /blog/quote-partita.html), e niente
 * redirect canonico verso l'indirizzo della pagina vuota.
 */
add_filter( 'request', function ( $vars ) {
	if ( empty( $vars['bn_partita'] ) ) {
		return $vars;
	}
	$ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'publish', 'name' => BNC_MATCH_SLUG, 'numberposts' => 1, 'fields' => 'ids' ) );
	if ( $ids ) {
		return array( 'page_id' => (int) $ids[0], 'bn_partita' => (int) $vars['bn_partita'] );
	}
	return $vars;
} );
add_filter( 'redirect_canonical', function ( $url ) {
	return get_query_var( 'bn_partita' ) ? false : $url;
} );

/** Partita della richiesta corrente (pagina partita). */
function bnc_match_current() {
	static $ev = false;
	if ( false === $ev ) {
		$id = (int) get_query_var( 'bn_partita' );
		if ( ! $id && isset( $_GET['partita'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			$id = absint( $_GET['partita'] ); // phpcs:ignore WordPress.Security.NonceVerification
		}
		$ev = $id ? bnc_odds_event( $id ) : null;
	}
	return $ev;
}

/* Titolo della pagina: "Quote Venezia-Bologna" */
function bnc_match_title() {
	$ev = bnc_match_current();
	return $ev ? 'Quote ' . bnc_odds_team( $ev['home'] ) . '-' . bnc_odds_team( $ev['away'] ) : '';
}
add_filter( 'pre_get_document_title', function ( $t ) {
	$m = bnc_match_title();
	return '' !== $m ? $m . ' | ' . get_bloginfo( 'name' ) : $t;
}, 20 );
add_filter( 'wpseo_title', function ( $t ) {
	$m = bnc_match_title();
	return '' !== $m ? $m . ' | ' . get_bloginfo( 'name' ) : $t;
} );
add_filter( 'wpseo_canonical', function ( $c ) {
	$ev = bnc_match_current();
	return $ev ? bnc_match_url( $ev ) : $c;
} );
/* Il titolo e' nella testata del blocco: niente titolo della pagina dal tema (un solo H1). */
add_filter( 'render_block', function ( $html, $block ) {
	// 0.9.7: anche autore e data della pagina (vengono dal modello del tema) non servono.
	if ( ! get_query_var( 'bn_partita' ) || ! bnc_match_current() || empty( $block['blockName'] ) ) {
		return $html;
	}
	return in_array( $block['blockName'], array( 'core/post-title', 'core/post-author', 'core/post-author-name', 'core/post-date', 'core/post-terms' ), true ) ? '' : $html;
}, 10, 2 );
/* 0.9.8: il tema mette autore e data dopo il primo H1 del contenuto, cioe' dentro la testata della partita: qui non servono. */
add_filter( 'render_block_core/post-content', function ( $html ) {
	return ( get_query_var( 'bn_partita' ) && bnc_match_current() ) ? preg_replace( '#<p class="bn-byline">.*?</p>#s', '', $html, 1 ) : $html;
}, 30 );
/* 0.9.7: briciole di pane di Yoast con il nome della partita invece di "Quote partita". */
add_filter( 'wpseo_breadcrumb_links', function ( $links ) {
	$t = bnc_match_title();
	if ( '' !== $t && $links ) {
		$links[ count( $links ) - 1 ]['text'] = $t;
	}
	return $links;
} );
/* Partita inesistente o vecchia: 404 (e niente pagina vuota indicizzata) */
add_action( 'template_redirect', function () {
	if ( get_query_var( 'bn_partita' ) && ! bnc_match_current() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
} );

/* ---------- Blocco Pagina partita ---------- */

add_action( 'init', function () {
	wp_register_script( 'betnero-partita', BETNERO_CORE_URL . 'assets/js/partita.js', array(), BETNERO_CORE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	$common = array(
		'editor_script' => 'betnero-blocks-editor',
		'style'         => 'betnero-quote',
		'category'      => 'betnero',
		'supports'      => array( 'html' => false, 'align' => array( 'wide' ) ),
	);
	register_block_type( 'betnero/pagina-partita', $common + array(
		'title'           => 'Pagina partita',
		'attributes'      => array( 'className' => array( 'type' => 'string' ) ),
		'render_callback' => 'bnc_match_block',
	) );
	register_block_type( 'betnero/bonus-laterale', $common + array(
		'title'           => 'Migliori bonus (colonna laterale)',
		'attributes'      => array(
			'tipo'      => array( 'type' => 'string', 'default' => 'scommesse' ),
			'limite'    => array( 'type' => 'number', 'default' => 10 ),
			'titolo'    => array( 'type' => 'string', 'default' => '' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_side_bonus_render',
	) );
	register_block_type( 'betnero/partite-evidenza', $common + array(
		'title'           => 'Partite in evidenza',
		'attributes'      => array(
			'limite'    => array( 'type' => 'number', 'default' => 3 ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_featured_render',
	) );
	register_block_type( 'betnero/ultimi-pronostici', $common + array(
		'title'           => 'Ultimi pronostici',
		'attributes'      => array(
			'categoria' => array( 'type' => 'string', 'default' => 'pronostici' ),
			'limite'    => array( 'type' => 'number', 'default' => 5 ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_latest_tips_render',
	) );
}, 22 );

function bnc_match_block() {
	$ev = bnc_match_current();
	if ( ! $ev && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		// Anteprima nell'editor: la prossima partita con quote.
		global $wpdb;
		$t  = bnc_odds_table();
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE status = 'pending' AND start > %s AND odds_updated IS NOT NULL ORDER BY start ASC LIMIT 1", gmdate( 'Y-m-d H:i:s' ) ) ); // phpcs:ignore WordPress.DB
		$ev = $id ? bnc_odds_event( $id ) : null;
		if ( ! $ev ) {
			return '<p class="bnq-empty">Nessuna partita con quote nella copia locale.</p>';
		}
	}
	if ( ! $ev ) {
		return '';
	}
	wp_enqueue_script( 'betnero-partita' );
	wp_enqueue_style( 'betnero-tables' );
	// 0.9.9: pagina completa come nel design: toplist a scorrimento in alto (solo mobile),
	// contenuto e colonna laterale (bonus, partite in evidenza, ultimi pronostici).
	$ops  = array_slice( bnc_operators( 'scommesse' ), 0, 10 );
	$h    = '<div class="bnm-page">';
	$h   .= $ops ? '<div class="bnm-only-mobile">' . bnc_bonus_slider( $ops, 'I migliori bookmaker', '' ) . '</div>' : '';
	$feat = bnc_featured_render( array( 'limite' => 3 ) );
	$main = bnc_match_render( $ev );
	// Su mobile "Partite in evidenza" subito dopo le quote, prima dei bonus a confronto.
	$main = '' !== $feat ? preg_replace( '#<section class="bnb-slider" id="confronto"#', '<div class="bnm-only-mobile">' . $feat . '</div>$0', $main, 1 ) : $main;
	$h   .= '<div class="bnm-cols"><div class="bnm-main">' . $main . '</div>';
	$h   .= '<aside class="bnm-aside" aria-label="Colonna laterale"><div class="bnm-only-desktop">' . bnc_side_bonus_render( array( 'limite' => 10 ) ) . $feat . '</div>' . bnc_latest_tips_render( array() ) . '</aside></div></div>';
	return $h;
}

function bnc_match_render( $ev ) {
	$books   = bnc_odds_shown_books();
	$home    = bnc_odds_team( $ev['home'] );
	$away    = bnc_odds_team( $ev['away'] );
	$ts      = strtotime( $ev['start'] . ' UTC' );
	$started = 'pending' !== $ev['status'] || $ts <= time();
	$stale   = bnc_odds_stale( $ev );
	$tables  = 2 === $stale ? array() : bnc_match_tables( $ev, $books );
	$with    = array();
	foreach ( $tables as $tb ) {
		$with += $tb['books'];
	}
	$n_books = count( $with );
	$known   = bnc_match_markets();
	$ini     = function ( $n ) {
		return function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $n, 0, 3 ) ) : strtoupper( substr( $n, 0, 3 ) );
	};
	$upd     = $ev['odds_updated'] ? wp_date( 'd/m H:i', strtotime( $ev['odds_updated'] . ' UTC' ) ) : '';

	// Testata
	$h  = '<div class="bnm" data-bnm>';
	$h .= '<section class="bnm-hero"><div class="bnm-hero-in"><div class="bnm-row1">';
	// 0.9.10: stemmi da API-Football (salvati sul sito), altrimenti le sigle.
	$crest = function ( $n ) use ( $ini ) {
		$src = function_exists( 'bnc_fd_crest' ) ? bnc_fd_crest( $n ) : '';
		return '<span' . ( $src ? ' class="has-img"' : '' ) . '>' . ( $src ? '<img src="' . esc_url( $src ) . '" alt="" width="34" height="34" decoding="async">' : esc_html( $ini( $n ) ) ) . '</span>';
	};
	$h .= '<span class="bnm-crests" aria-hidden="true">' . $crest( $home ) . $crest( $away ) . '</span>';
	$h .= '<h1 class="bnm-title">Quote ' . esc_html( $home . '-' . $away ) . '</h1>';
	$h .= '<div class="bnm-meta"><strong>' . esc_html( ucfirst( wp_date( 'l j F · H:i', $ts ) ) ) . '</strong><span>' . esc_html( bnc_odds_league_label( $ev['league_name'] ) ) . '</span></div></div>';

	if ( isset( $tables['ML']['lines'][''] ) ) {
		$ml    = $tables['ML']['lines'][''];
		$keys  = array( 'home' => '1 · ' . $home, 'draw' => 'X · Pareggio', 'away' => '2 · ' . $away );
		$bests = array();
		$h    .= '<div class="bnm-row2"><div class="bnm-best">';
		foreach ( $keys as $k => $lab ) {
			$v   = isset( $ml[ $k ] ) ? $ml[ $k ] : array();
			$max = $v ? max( $v ) : 0;
			$bk  = $max ? array_search( $max, $v, true ) : '';
			$op  = $bk && isset( $books[ $bk ] ) ? $books[ $bk ] : null;
			$bests[ $k ] = $max;
			$in  = '<span>' . esc_html( $lab ) . '</span><strong>' . esc_html( bnc_odds_fmt( $max ) ) . '</strong><em>' . esc_html( $op ? bnc_odds_book_label( $op['name'] ) : '' ) . '</em>';
			$h  .= ( $op && ! $started && $op['go_url'] ) ? '<a href="' . esc_url( $op['go_url'] ) . '" rel="nofollow sponsored noopener"' . ( $op['go_blank'] ? ' target="_blank"' : '' ) . '>' . $in . '</a>' : '<span class="is-off">' . $in . '</span>';
		}
		$h .= '</div>';
		if ( count( array_filter( $bests ) ) === 3 ) {
			$inv = array_map( function ( $x ) {
				return 1 / $x;
			}, $bests );
			$sum = array_sum( $inv );
			$lab = array( 'home' => $home, 'draw' => 'Pareggio', 'away' => $away );
			$h  .= '<div class="bnm-prob"><span class="bnm-prob-t">Probabilità secondo i bookmaker</span><div class="bnm-bar">';
			foreach ( $inv as $k => $x ) {
				$p  = (int) round( $x / $sum * 100 );
				$h .= '<span class="is-' . esc_attr( $k ) . '" style="flex:' . $p . ' 1 0">' . esc_html( $lab[ $k ] . ' ' . $p . '%' ) . '</span>';
			}
			$h .= '</div><span class="bnm-prob-n">Dalle quote migliori, tolto il margine dei bookmaker.</span></div>';
		}
		$h .= '</div>';
	}
	$h .= '</div><div class="bnm-hero-foot"><span>' . esc_html( $n_books ? sprintf( 'Quote di %d bookmaker ADM%s', $n_books, $upd ? ', aggiornate alle ' . $upd : '' ) : 'Quote non ancora disponibili' ) . '</span><span>18+ · Gioca responsabilmente</span></div></section>';

	// 0.9.12: widget con i dati di API-Football (precedenti, forma, formazioni, eventi, statistiche...)
	if ( function_exists( 'bnc_fdm_widget' ) ) {
		$h .= bnc_fdm_widget( $ev );
	}

	// 0.9.9: box BONUS con ancora verso i bonus a confronto
	$h .= '<a class="bnm-bonusbox" href="#confronto"><span class="bnm-bb-k">BONUS</span><span class="bnm-bb-t"><strong>' . esc_html( 'Scommetti su ' . $home . '-' . $away . ' con i bonus di benvenuto' ) . '</strong><span>I bonus dei bookmaker ADM delle quote qui sotto</span></span><span class="bnm-bb-go">Vedi i bonus &darr;</span></a>';

	// Articolo collegato
	$post_id = bnc_match_post( $ev['id'] );
	if ( $post_id ) {
		$h .= '<a class="bnm-tip" href="' . esc_url( get_permalink( $post_id ) ) . '"><span class="bnm-tip-k">IL NOSTRO PRONOSTICO</span><strong>' . esc_html( get_the_title( $post_id ) ) . '</strong><span class="bnm-tip-go">Leggi il pronostico &rsaquo;</span></a>';
	}

	if ( ! $tables ) {
		return $h . '<p class="bnq-note">Quote non ancora disponibili per questa partita.</p></div>';
	}

	// Filtri
	$groups_used = array( 'tutti' => true );
	foreach ( $tables as $name => $tb ) {
		$groups_used[ $known[ $name ][1] ] = true;
	}
	$h .= '<div class="bnm-filters" role="toolbar" aria-label="Filtra i mercati">';
	foreach ( bnc_match_groups() as $g => $gl ) {
		if ( isset( $groups_used[ $g ] ) ) {
			$h .= '<button type="button" data-bnm-group="' . esc_attr( $g ) . '"' . ( 'tutti' === $g ? ' aria-pressed="true"' : ' aria-pressed="false"' ) . '>' . esc_html( $gl ) . '</button>';
		}
	}
	$h .= '</div>';

	// Blocchi: completi (tutti i bookmaker) e "Altri mercati"
	$main  = '';
	$other = '';
	foreach ( $tables as $name => $tb ) {
		$full  = count( $tb['books'] ) >= max( 1, $n_books );
		$block = bnc_match_market_html( $name, $tb, $books, $home, $away, $full, $started );
		if ( $full ) {
			$main .= $block;
		} else {
			$other .= $block;
		}
	}
	$h .= $main;
	if ( '' !== $other ) {
		$h .= '<div class="bnm-others" data-bnm-others><h2 class="bnm-h2">Altri mercati</h2><p class="bnm-sub">Mercati offerti solo da alcuni dei nostri bookmaker: per ognuno trovi le quote di chi lo offre.</p>' . $other . '</div>';
	}
	$ops = array_slice( bnc_operators( 'scommesse' ), 0, 10 );
	if ( $ops ) {
		$h .= bnc_bonus_slider( $ops, 'Tutti i bonus a confronto', 'confronto' );
	}
	$h .= bnc_match_others( $ev );
	$h .= bnc_match_faq( $ev, $tables, $books, $home, $away, $n_books );
	return $h . '<p class="bnq-adm">18+ | Gioca responsabilmente | Le quote possono variare</p></div>';
}

/** 0.9.9: bonus a scorrimento (schede come nel design). $id = ancora, vuoto = nessuna. */
/** Scheda bonus (logo, bonus, stelle, Vai al sito e Recensione): slider e colonna laterale. */
function bnc_bonus_card( $op, $n ) {
	list( $b1, $b2, $b3 ) = bnc_bonus_lines( $op['bonus'] );
	$h  = '<div class="bnb-card"><span class="bnb-n">' . (int) $n . '</span><span class="bnb-logo">' . bnc_logo( $op ) . '</span>';
	$h .= ( '' !== $b1 ? '<span class="bnb-s">' . esc_html( $b1 ) . '</span>' : '' ) . '<strong class="bnb-big">' . esc_html( $b2 ) . '</strong>' . ( '' !== $b3 ? '<span class="bnb-s">' . esc_html( $b3 ) . '</span>' : '' );
	$h .= ( '' !== $op['rating'] ? bnc_stars( $op['rating'] ) : '' ) . '<span class="bnb-btns">' . bnc_go_button( $op, 'Vai al sito', 'bnb-go' ) . ( $op['review_url'] ? '<a class="bnb-rev" href="' . esc_url( $op['review_url'] ) . '">Recensione</a>' : '' ) . '</span></div>';
	return $h;
}

function bnc_bonus_slider( $ops, $title, $id ) {
	$h = '<section class="bnb-slider"' . ( $id ? ' id="' . esc_attr( $id ) . '"' : '' ) . '><div class="bnb-head"><strong>' . esc_html( $title ) . '</strong><span>Bonus di benvenuto scommesse, bookmaker con licenza ADM</span></div><div class="bnb-track">';
	foreach ( $ops as $i => $op ) {
		$h .= bnc_bonus_card( $op, $i + 1 );
	}
	return $h . '</div><p class="bnb-foot">Scorri per vedere gli altri &rarr; · 18+ · Bonus soggetti a termini e condizioni degli operatori</p></section>';
}

/** 0.9.9: altre partite dello stesso campionato nei prossimi giorni. */
function bnc_match_others( $ev ) {
	global $wpdb;
	$t    = bnc_odds_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$t} WHERE league_slug = %s AND id <> %d AND status = 'pending' AND start > %s ORDER BY start ASC LIMIT 6", $ev['league_slug'], (int) $ev['id'], gmdate( 'Y-m-d H:i:s' ) ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $rows ) {
		return '';
	}
	$books = bnc_odds_shown_books();
	$h     = '<section class="bnm-others-m"><div class="bnm-om-head"><strong>Altre partite della giornata</strong></div>';
	foreach ( $rows as $r ) {
		$r['odds'] = json_decode( (string) $r['odds'], true );
		$r['odds'] = is_array( $r['odds'] ) ? $r['odds'] : array();
		$stale     = 2 === bnc_odds_stale( $r );
		$h        .= '<div class="bnm-om-row"><span class="bnm-om-m"><span>' . esc_html( ucfirst( wp_date( 'D j/n · H:i', strtotime( $r['start'] . ' UTC' ) ) ) ) . '</span><strong>' . esc_html( bnc_odds_team( $r['home'] ) . ' – ' . bnc_odds_team( $r['away'] ) ) . '</strong></span>';
		foreach ( array( '1', 'X', '2' ) as $o ) {
			$best = 0.0;
			if ( ! $stale ) {
				foreach ( $books as $name => $op ) {
					$best = max( $best, bnc_odds_price( isset( $r['odds'][ $name ] ) ? $r['odds'][ $name ] : array(), '1x2', $o ) );
				}
			}
			$h .= '<span class="bnm-om-o"><em>' . $o . '</em>' . esc_html( bnc_odds_fmt( $best ) ) . '</span>';
		}
		$pid = bnc_match_post( (int) $r['id'] );
		$h  .= $pid ? '<a class="bnq-go is-tip" href="' . esc_url( get_permalink( $pid ) ) . '">Quote e pronostico</a>' : '<a class="bnq-go" href="' . esc_url( bnc_match_url( $r ) ) . '">Quote</a>';
		$h  .= '</div>';
	}
	return $h . '</section>';
}

/** 0.9.9: domande frequenti con le risposte prese dai dati della pagina. */
function bnc_match_faq( $ev, $tables, $books, $home, $away, $n_books ) {
	$qa = array();
	if ( isset( $tables['ML']['lines'][''] ) ) {
		$ml   = $tables['ML']['lines'][''];
		$best = array();
		foreach ( array( 'home' => '1 (' . $home . ')', 'draw' => 'X (pareggio)', 'away' => '2 (' . $away . ')' ) as $k => $lab ) {
			if ( ! empty( $ml[ $k ] ) ) {
				$max    = max( $ml[ $k ] );
				$bk     = array_search( $max, $ml[ $k ], true );
				$best[] = $lab . ' a ' . bnc_odds_fmt( $max ) . ' su ' . bnc_odds_book_label( $books[ $bk ]['name'] );
			}
		}
		if ( $best ) {
			$qa[] = array( 'Quali sono le quote migliori per ' . $home . '-' . $away . '?', 'Tra i ' . $n_books . ' bookmaker ADM che confrontiamo, le quote più alte sull\'esito finale sono: ' . implode( '; ', $best ) . '.' );
		}
	}
	$qa[] = array( 'Ogni quanto si aggiornano le quote?', 'Le quote si aggiornano in automatico ogni 10 minuti fino al fischio d\'inizio. Dopo l\'inizio della partita restano le ultime quote pre-partita.' );
	$qa[] = array( 'Come si calcolano le probabilità?', 'Partiamo dalle quote migliori di ogni esito, calcoliamo la probabilità che esprimono e togliamo il margine dei bookmaker, così le percentuali sommano 100.' );
	$qa[] = array( 'Dove posso scommettere su ' . $home . '-' . $away . '?', 'Sui bookmaker con concessione ADM che trovi nei bonus a confronto qui sopra: ognuno offre un bonus di benvenuto diverso.' );
	$h = '<section class="bnm-faq"><h2 class="bnm-h2">Domande frequenti su ' . esc_html( $home . '-' . $away ) . '</h2>';
	foreach ( $qa as $i => $x ) {
		$h .= '<details' . ( 0 === $i ? ' open' : '' ) . '><summary>' . esc_html( $x[0] ) . '</summary><p>' . esc_html( $x[1] ) . '</p></details>';
	}
	return $h . '</section>';
}

/** Un mercato: barra apribile, eventuali linee, tabella con i bookmaker in colonna. */
function bnc_match_market_html( $name, $tb, $books, $home, $away, $full, $started ) {
	$known = bnc_match_markets();
	$title = $known[ $name ][0];
	$group = $known[ $name ][1];
	$cols  = array_values( array_intersect( array_keys( $books ), array_keys( $tb['books'] ) ) );
	$lines = $tb['lines'];
	$main  = bnc_match_main_line( $lines, $name );
	$open  = $full ? ' open' : '';
	$who   = $full ? '' : ( 1 === count( $cols ) ? 'quote di ' . bnc_odds_book_label( $books[ $cols[0] ]['name'] ) : implode( ' e ', array_map( function ( $b ) use ( $books ) {
		return bnc_odds_book_label( $books[ $b ]['name'] );
	}, $cols ) ) );

	$h  = '<details class="bnm-mk' . ( $full ? '' : ' is-other' ) . '" data-bnm-mk="' . esc_attr( $group ) . '"' . $open . '><summary><strong>' . esc_html( $title ) . '</strong>' . ( $who ? '<span>' . esc_html( $who ) . '</span>' : '' ) . '</summary>';
	if ( count( $lines ) > 1 ) {
		$h .= '<div class="bnm-lines" role="toolbar" aria-label="Linea">';
		foreach ( array_keys( $lines ) as $l ) {
			$h .= '<button type="button" data-bnm-line="' . esc_attr( $l ) . '" aria-pressed="' . ( (string) $l === (string) $main ? 'true' : 'false' ) . '">' . esc_html( bnc_match_line( $l, false === stripos( $name, 'total' ) && 'Corners' !== $name ) ) . '</button>';
		}
		$h .= '</div>';
	}
	$grid = 'grid-template-columns: minmax(118px, 1.4fr) repeat(' . count( $cols ) . ', minmax(' . ( $full ? '58px' : '84px' ) . ', ' . ( $full ? '1fr' : '120px' ) . '))';
	$h   .= '<div class="bnm-scroll"><div class="bnm-tab' . ( $full ? '' : ' is-narrow' ) . '">';
	$h   .= '<div class="bnm-tr bnm-th" style="' . esc_attr( $grid ) . '"><span>Esito</span>';
	foreach ( $cols as $b ) {
		$h .= '<span class="bnm-logo">' . bnc_logo( $books[ $b ] ) . '</span>';
	}
	$h .= '</div>';
	foreach ( $lines as $l => $outs ) {
		$h .= '<div class="bnm-line" data-line="' . esc_attr( $l ) . '"' . ( (string) $l === (string) $main ? '' : ' hidden' ) . '>';
		$inv = array();
		foreach ( $outs as $ok => $vals ) {
			$inv[ $ok ] = $vals ? 1 / max( $vals ) : 0;
		}
		$sum = array_sum( $inv );
		foreach ( $outs as $ok => $vals ) {
			$max  = $vals ? max( $vals ) : 0;
			$okl  = strtolower( (string) $ok );
			$lab  = bnc_match_out_label( $ok, $home, $away );
			if ( '' !== (string) $l ) {
				if ( in_array( $okl, array( 'home', 'away' ), true ) ) {
					$lab .= ' (' . bnc_match_line( 'home' === $okl ? $l : - (float) $l, true ) . ')';
				} elseif ( in_array( $okl, array( 'over', 'under', 'exactly' ), true ) ) {
					$lab .= ' ' . bnc_match_line( $l );
				}
			}
			$pct  = ( $sum > 0 && $max > 0 && count( $outs ) <= 3 ) ? (int) round( $inv[ $ok ] / $sum * 100 ) . '% · ' : '';
			$bk   = $max ? array_search( $max, $vals, true ) : '';
			$h   .= '<div class="bnm-tr" style="' . esc_attr( $grid ) . '"><span class="bnm-out"><strong>' . esc_html( $lab ) . '</strong>' . ( $full && $bk ? '<em>' . esc_html( $pct . 'migliore su ' . bnc_odds_book_label( $books[ $bk ]['name'] ) ) . '</em>' : '' ) . '</span>';
			foreach ( $cols as $b ) {
				$v    = isset( $vals[ $b ] ) ? $vals[ $b ] : 0;
				$best = $v > 0 && abs( $v - $max ) < 0.0001 && count( $vals ) > 1 && abs( $max - min( $vals ) ) > 0.0001; // tutte uguali: nessuna in verde
				$cls  = 'bnm-odd' . ( $best ? ' is-best' : '' );
				$op   = $books[ $b ];
				$h   .= ( $v > 0 && ! $started && $op['go_url'] ) ? '<a class="' . $cls . '" href="' . esc_url( $op['go_url'] ) . '" rel="nofollow sponsored noopener"' . ( $op['go_blank'] ? ' target="_blank"' : '' ) . ' title="' . esc_attr( $op['name'] ) . '">' . esc_html( bnc_odds_fmt( $v ) ) . '</a>' : '<span class="' . $cls . ( $v > 0 ? '' : ' is-off' ) . '">' . esc_html( bnc_odds_fmt( $v ) ) . '</span>';
			}
			$h .= '</div>';
		}
		$h .= '</div>';
	}
	return $h . '</div></div></details>';
}

/* ---------- Blocco Migliori bonus (colonna laterale) ---------- */

/** Righe del bonus: [ piccola sopra, grande, piccola sotto ]. */
function bnc_bonus_lines( $html ) {
	$small = array();
	$big   = '';
	if ( preg_match_all( '#<(p|h[1-6])[^>]*>(.*?)</\1>#is', (string) $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $b ) {
			$txt = trim( wp_strip_all_tags( $b[2] ) );
			if ( '' === $txt ) {
				continue;
			}
			if ( 'p' !== strtolower( $b[1] ) && '' === $big ) {
				$big = $txt;
			} else {
				$small[] = array( $txt, '' === $big );
			}
		}
	}
	if ( '' === $big ) {
		$big = $small ? array_shift( $small )[0] : trim( wp_strip_all_tags( (string) $html ) );
	}
	$top = '';
	$bot = '';
	foreach ( $small as $s ) {
		if ( $s[1] ) {
			$top .= ( '' !== $top ? ' ' : '' ) . $s[0];
		} else {
			$bot .= ( '' !== $bot ? ' ' : '' ) . $s[0];
		}
	}
	return array( $top, $big, $bot );
}

function bnc_side_bonus_render( $a ) {
	$tipo  = ( isset( $a['tipo'] ) && 'casino' === $a['tipo'] ) ? 'casino' : 'scommesse';
	$limit = max( 1, min( 20, isset( $a['limite'] ) ? (int) $a['limite'] : 10 ) );
	$ops   = array_slice( bnc_operators( $tipo ), 0, $limit );
	if ( ! $ops ) {
		return '';
	}
	wp_enqueue_style( 'betnero-tables' );
	$titolo = ! empty( $a['titolo'] ) ? $a['titolo'] : ( 'casino' === $tipo ? 'Migliori Bonus Casinò' : 'Migliori Bonus Scommesse' );
	$h      = '<section class="bns-side"><div class="bns-side-head"><strong>' . esc_html( $titolo ) . '</strong><span>TOP ' . count( $ops ) . '</span></div><div class="bns-side-list">';
	foreach ( $ops as $i => $op ) {
		$h .= bnc_bonus_card( $op, $i + 1 ); // 0.9.21: stesse schede dello slider, in verticale (mail di Francesco del 26/11)
	}
	return $h . '</div><p class="bns-side-adm">18+ | Gioca responsabilmente | Solo operatori ADM</p></section>';
}

/* ---------- Blocco Partite in evidenza ---------- */

function bnc_featured_render( $a ) {
	global $wpdb;
	$limit = max( 1, min( 6, isset( $a['limite'] ) ? (int) $a['limite'] : 3 ) );
	$t     = bnc_odds_table();
	$rows  = $wpdb->get_results( $wpdb->prepare( // phpcs:ignore WordPress.DB
		"SELECT p.ID AS post_id, q.id AS ev FROM {$wpdb->posts} p
		 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_bn_quote_evento'
		 INNER JOIN {$t} q ON q.id = CAST(m.meta_value AS UNSIGNED)
		 WHERE p.post_status = 'publish' AND p.post_type = 'post' AND q.status = 'pending' AND q.start > %s
		 ORDER BY q.start ASC LIMIT %d", // phpcs:ignore WordPress.DB
		gmdate( 'Y-m-d H:i:s' ),
		$limit
	), ARRAY_A );
	if ( ! $rows ) {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? '<p class="bnq-empty">Nessun pronostico pubblicato con una partita in programma.</p>' : '';
	}
	$books = bnc_odds_shown_books();
	$h     = '<section class="bnf"><div class="bnf-head"><strong>Partite in evidenza</strong></div><div class="bnf-list">';
	foreach ( $rows as $r ) {
		$ev = bnc_odds_event( (int) $r['ev'] );
		if ( ! $ev ) {
			continue;
		}
		$h .= '<div class="bnf-card"><span class="bnf-when">' . esc_html( ucfirst( wp_date( 'l j/n · H:i', strtotime( $ev['start'] . ' UTC' ) ) ) ) . '</span><strong class="bnf-match">' . esc_html( bnc_odds_team( $ev['home'] ) . ' – ' . bnc_odds_team( $ev['away'] ) ) . '</strong><div class="bnf-odds">';
		foreach ( array( '1', 'X', '2' ) as $o ) {
			$best = 0.0;
			$bop  = null;
			if ( 2 !== bnc_odds_stale( $ev ) ) {
				foreach ( $books as $name => $op ) {
					$p = bnc_odds_price( isset( $ev['odds'][ $name ] ) ? $ev['odds'][ $name ] : array(), '1x2', $o );
					if ( $p > $best ) {
						$best = $p;
						$bop  = $op;
					}
				}
			}
			$in = '<span>' . $o . '</span><strong>' . esc_html( bnc_odds_fmt( $best ) ) . '</strong><em>' . esc_html( $bop ? bnc_odds_book_label( $bop['name'] ) : '' ) . '</em>';
			$h .= ( $bop && $bop['go_url'] ) ? '<a href="' . esc_url( $bop['go_url'] ) . '" rel="nofollow sponsored noopener"' . ( $bop['go_blank'] ? ' target="_blank"' : '' ) . '>' . $in . '</a>' : '<span class="is-off">' . $in . '</span>';
		}
		$h .= '</div><a class="bnf-go" href="' . esc_url( get_permalink( (int) $r['post_id'] ) ) . '">Leggi il pronostico</a></div>';
	}
	return $h . '</div></section>';
}

/* ---------- Blocco Ultimi pronostici ---------- */

function bnc_latest_tips_render( $a ) {
	$cat   = isset( $a['categoria'] ) ? sanitize_title( $a['categoria'] ) : 'pronostici';
	$limit = max( 1, min( 10, isset( $a['limite'] ) ? (int) $a['limite'] : 5 ) );
	$posts = get_posts( array( 'post_type' => 'post', 'post_status' => 'publish', 'numberposts' => $limit, 'category_name' => $cat ) );
	if ( ! $posts ) {
		return ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ? '<p class="bnq-empty">Nessun articolo nella categoria "' . esc_html( $cat ) . '".</p>' : '';
	}
	$term = get_category_by_slug( $cat );
	$h    = '<section class="bnl"><div class="bnl-head"><strong>Ultimi Pronostici</strong></div>';
	foreach ( $posts as $p ) {
		$img = get_the_post_thumbnail( $p->ID, 'thumbnail', array( 'loading' => 'lazy', 'decoding' => 'async', 'alt' => '' ) );
		$h  .= '<a class="bnl-item" href="' . esc_url( get_permalink( $p ) ) . '"><span class="bnl-img">' . $img . '</span><span class="bnl-t"><strong>' . esc_html( get_the_title( $p ) ) . '</strong><span>' . esc_html( get_the_date( 'j F Y', $p ) ) . '</span></span></a>';
	}
	return $h . ( $term ? '<a class="bnl-all" href="' . esc_url( get_category_link( $term ) ) . '">Tutti i pronostici</a>' : '' ) . '</section>';
}

/* ---------- 0.9.17: colonna laterale nuova negli articoli dei pronostici ---------- */

/**
 * Nel modello degli articoli del tema la colonna laterale e' la parte "sidebar"
 * (ultimi casino e banner). Negli articoli dei pronostici (partita collegata o categoria
 * "pronostici") la sostituiamo con quella del design: Migliori bonus (solo desktop),
 * Partite in evidenza, Ultimi pronostici.
 */
function bnc_is_tip_post( $post_id ) {
	return (bool) get_post_meta( $post_id, '_bn_quote_evento', true ) || has_category( 'pronostici', $post_id );
}

add_filter( 'render_block_core/template-part', function ( $html, $block ) {
	if ( empty( $block['attrs']['slug'] ) || 'sidebar' !== $block['attrs']['slug'] || ! is_singular( 'post' ) ) {
		return $html;
	}
	$pid = get_queried_object_id();
	if ( ! $pid || ! bnc_is_tip_post( $pid ) ) {
		return $html;
	}
	wp_enqueue_style( 'betnero-quote' );
	wp_enqueue_style( 'betnero-tables' );
	$inner = '<div class="bnm-only-desktop">' . bnc_side_bonus_render( array( 'limite' => 10 ) ) . '</div>' . bnc_featured_render( array( 'limite' => 3 ) ) . bnc_latest_tips_render( array() );
	return '<aside class="wp-block-template-part bnm-aside-post" aria-label="Colonna laterale">' . $inner . '</aside>';
}, 20, 2 );

/* 0.9.17: in fondo ai pronostici con partita collegata, i bonus a confronto (schede a scorrimento) come nella pagina partita. */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || is_feed() || wp_is_json_request() || ! is_singular( 'post' ) || get_the_ID() !== get_queried_object_id() || false !== strpos( $content, 'id="confronto"' ) ) {
		return $content;
	}
	$ev_id = (int) get_post_meta( get_the_ID(), '_bn_quote_evento', true );
	$ev    = $ev_id ? bnc_odds_event( $ev_id ) : null;
	$ops   = $ev ? array_slice( bnc_operators( 'scommesse' ), 0, 10 ) : array();
	if ( ! $ops ) {
		return $content;
	}
	wp_enqueue_style( 'betnero-quote' );
	return $content . '<div class="bnw-post">' . bnc_bonus_slider( $ops, 'Tutti i bonus a confronto', 'confronto' ) . '</div>';
}, PHP_INT_MAX );
