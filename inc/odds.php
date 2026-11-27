<?php
/**
 * Quote da odds-api.io (0.6.0).
 *
 * - Operatori > Quote: chiave, bookmaker (quelli scelti nel pannello di odds-api.io), campionati.
 * - Lettura programmata ogni 10 minuti con una copia locale nella tabella {prefix}bnc_quote:
 *   le pagine leggono solo la copia, quindi le richieste non dipendono dal traffico e, se
 *   odds-api.io non risponde, restano visibili le ultime quote salvate.
 * - Riquadro "Quote del pronostico" negli articoli: partita e pronostico.
 * - Blocchi betnero/quota-pronostico e betnero/confronto-quote, aggiornati anche via REST
 *   dopo il caricamento (le pagine in cache di Velox mostrano comunque le quote fresche).
 *
 * La chiave non viene mai mostrata ne' inviata al browser.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BNC_ODDS_API', 'https://api.odds-api.io/v3' );
define( 'BNC_ODDS_DB', '1' );
// 0.9.5: anche i mercati della pagina partita (massimo 20 per richiesta, una richiesta ogni 10 partite).
define( 'BNC_ODDS_MARKETS', 'ML,Double Chance,Totals,Both Teams To Score,Draw No Bet,Correct Score,ML HT,Totals HT,Half Time / Full Time,Spread,European Handicap,Corners,Corners Totals,Bookings Totals' );

/* ---------- Impostazioni ---------- */

function bnc_odds_settings() {
	$s = get_option( 'bnc_odds', array() );
	return wp_parse_args( is_array( $s ) ? $s : array(), array(
		'bookmakers' => array(), // nomi API scelti nel pannello di odds-api.io
		'map'        => array(), // nome API => id operatore Betnero (0 = non mostrare)
		'leagues'    => array(), // slug dei campionati seguiti
		'days'       => 7,       // giorni in avanti
		'auto'       => '1',     // 0.6.5: blocchi automatici nei pronostici con partita collegata
	) );
}

function bnc_odds_key() {
	return (string) get_option( 'bnc_odds_key', '' );
}

function bnc_odds_table() {
	global $wpdb;
	return $wpdb->prefix . 'bnc_quote';
}

add_action( 'plugins_loaded', function () {
	if ( get_option( 'bnc_odds_db' ) === BNC_ODDS_DB ) {
		return;
	}
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	$t = bnc_odds_table();
	dbDelta( "CREATE TABLE {$t} (
		id bigint(20) unsigned NOT NULL,
		sport varchar(40) NOT NULL DEFAULT '',
		league_slug varchar(160) NOT NULL DEFAULT '',
		league_name varchar(190) NOT NULL DEFAULT '',
		home varchar(190) NOT NULL DEFAULT '',
		away varchar(190) NOT NULL DEFAULT '',
		start datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		status varchar(20) NOT NULL DEFAULT '',
		score varchar(20) NOT NULL DEFAULT '',
		odds longtext NULL,
		urls longtext NULL,
		updated datetime NULL,
		odds_updated datetime NULL,
		PRIMARY KEY  (id),
		KEY start (start),
		KEY league_slug (league_slug)
	) {$wpdb->get_charset_collate()};" );
	update_option( 'bnc_odds_db', BNC_ODDS_DB, false );
} );

/* ---------- Chiamate a odds-api.io ---------- */

/**
 * GET su odds-api.io. Restituisce l'array decodificato o WP_Error.
 * Conta le richieste dell'ora corrente (limite del piano: 5.000 all'ora).
 */
function bnc_odds_get( $path, $args = array(), $with_key = true ) {
	if ( $with_key ) {
		$key = bnc_odds_key();
		if ( '' === $key ) {
			return new WP_Error( 'bnc_odds_nokey', 'Chiave di odds-api.io non impostata.' );
		}
		$args = array( 'apiKey' => $key ) + $args;
	}
	$url = BNC_ODDS_API . $path . ( $args ? '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) : '' );
	$res = wp_remote_get( $url, array( 'timeout' => 25, 'headers' => array( 'Accept' => 'application/json' ) ) );

	$hour = gmdate( 'YmdH' );
	$cnt  = get_option( 'bnc_odds_count', array() );
	$cnt  = ( is_array( $cnt ) && isset( $cnt['h'] ) && $cnt['h'] === $hour ) ? $cnt : array( 'h' => $hour, 'n' => 0 );
	$cnt['n']++;
	update_option( 'bnc_odds_count', $cnt, false );

	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code < 200 || $code >= 300 ) {
		$msg = is_array( $body ) && ! empty( $body['error'] ) ? (string) $body['error'] : ( is_array( $body ) && ! empty( $body['message'] ) ? (string) $body['message'] : 'HTTP ' . $code );
		return new WP_Error( 'bnc_odds_http', $msg . ' (' . $path . ')', array( 'status' => $code ) );
	}
	return is_array( $body ) ? $body : array();
}

/** Nomi dei bookmaker in una risposta di /bookmakers/selected, qualunque forma abbia. */
function bnc_odds_names( $data ) {
	$out = array();
	if ( is_string( $data ) ) {
		return array( $data );
	}
	if ( ! is_array( $data ) ) {
		return $out;
	}
	if ( isset( $data['name'] ) && is_string( $data['name'] ) ) {
		return array( $data['name'] );
	}
	foreach ( $data as $k => $v ) {
		if ( is_string( $v ) ) {
			$out[] = $v;
		} elseif ( is_array( $v ) ) {
			$out = array_merge( $out, bnc_odds_names( $v ) );
		} elseif ( true === $v && is_string( $k ) ) {
			$out[] = $k;
		}
	}
	return array_values( array_unique( array_filter( array_map( 'trim', $out ) ) ) );
}

/* ---------- Lettura programmata ---------- */

add_filter( 'cron_schedules', function ( $s ) {
	$s['bnc_10min'] = array( 'interval' => 600, 'display' => 'Ogni 10 minuti (Betnero quote)' );
	return $s;
} );

/** Programma (o ferma) l'aggiornamento automatico. 0.6.1: chiamata anche subito dopo i salvataggi. */
function bnc_odds_schedule() {
	$active = '' !== bnc_odds_key() && ! empty( bnc_odds_settings()['leagues'] );
	$next   = wp_next_scheduled( 'bnc_odds_sync' );
	if ( $active && ! $next ) {
		wp_schedule_event( time() + 60, 'bnc_10min', 'bnc_odds_sync' );
	} elseif ( ! $active && $next ) {
		wp_clear_scheduled_hook( 'bnc_odds_sync' );
	}
}
add_action( 'init', 'bnc_odds_schedule' );

add_action( 'bnc_odds_sync', 'bnc_odds_sync' );

function bnc_odds_log( $msg, $ok = false ) {
	update_option( 'bnc_odds_state', array_merge( (array) get_option( 'bnc_odds_state', array() ), array(
		$ok ? 'last_ok' : 'last_error' => current_time( 'mysql', true ),
		$ok ? 'last_ok_msg' : 'last_error_msg' => $msg,
	) ), false );
}

function bnc_odds_dt( $iso ) {
	$ts = strtotime( (string) $iso );
	return $ts ? gmdate( 'Y-m-d H:i:s', $ts ) : '0000-00-00 00:00:00';
}

function bnc_odds_score( $ev ) {
	if ( isset( $ev['scores']['home'], $ev['scores']['away'] ) && null !== $ev['scores']['home'] && null !== $ev['scores']['away'] ) {
		return (int) $ev['scores']['home'] . '-' . (int) $ev['scores']['away'];
	}
	return '';
}

/**
 * Un giro di aggiornamento:
 * 1. calendario dei campionati seguiti (partite da ieri a +N giorni, con stato e risultato);
 * 2. quote delle partite non ancora iniziate, 10 per richiesta con /odds/multi.
 *    Le partite delle prossime 48 ore ogni giro, le altre al massimo ogni ora.
 *    Dal fischio d'inizio le quote non si aggiornano piu' (restano le ultime pre-partita).
 */
function bnc_odds_sync( $force = false ) {
	if ( get_transient( 'bnc_odds_lock' ) && ! $force ) {
		return array( 'skip' => 'giro gia\' in corso' );
	}
	set_transient( 'bnc_odds_lock', 1, 5 * MINUTE_IN_SECONDS );
	global $wpdb;
	$t   = bnc_odds_table();
	$s   = bnc_odds_settings();
	$now = time();
	$rep = array( 'partite' => 0, 'quote' => 0, 'richieste' => 0, 'errori' => array() );

	foreach ( (array) $s['leagues'] as $slug ) {
		$evs = bnc_odds_get( '/events', array(
			'sport'  => 'football',
			'league' => $slug,
			'status' => 'pending,live,settled',
			'from'   => gmdate( 'Y-m-d\TH:i:s\Z', $now - DAY_IN_SECONDS ),
			'to'     => gmdate( 'Y-m-d\TH:i:s\Z', $now + max( 1, (int) $s['days'] ) * DAY_IN_SECONDS ),
		) );
		$rep['richieste']++;
		if ( is_wp_error( $evs ) ) {
			$rep['errori'][] = $evs->get_error_message();
			continue;
		}
		foreach ( $evs as $ev ) {
			if ( empty( $ev['id'] ) ) {
				continue;
			}
			$row = array(
				'id'          => (int) $ev['id'],
				'sport'       => isset( $ev['sport']['slug'] ) ? (string) $ev['sport']['slug'] : 'football',
				'league_slug' => isset( $ev['league']['slug'] ) ? (string) $ev['league']['slug'] : (string) $slug,
				'league_name' => isset( $ev['league']['name'] ) ? (string) $ev['league']['name'] : '',
				'home'        => (string) ( isset( $ev['home'] ) ? $ev['home'] : '' ),
				'away'        => (string) ( isset( $ev['away'] ) ? $ev['away'] : '' ),
				'start'       => bnc_odds_dt( isset( $ev['date'] ) ? $ev['date'] : '' ),
				'status'      => (string) ( isset( $ev['status'] ) ? $ev['status'] : '' ),
				'score'       => bnc_odds_score( $ev ),
				'updated'     => current_time( 'mysql', true ),
			);
			$exists = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$t} WHERE id = %d", $row['id'] ) ); // phpcs:ignore WordPress.DB
			if ( $exists ) {
				$wpdb->update( $t, $row, array( 'id' => $row['id'] ) ); // phpcs:ignore WordPress.DB
			} else {
				$wpdb->insert( $t, $row ); // phpcs:ignore WordPress.DB
			}
			$rep['partite']++;
		}
		// Partita rimpiazzata da odds-api.io: il pronostico collegato passa al nuovo id.
		foreach ( $evs as $ev ) {
			if ( ! empty( $ev['replacedBy'] ) && ! empty( $ev['id'] ) ) {
				$wpdb->update( $wpdb->postmeta, array( 'meta_value' => (string) (int) $ev['replacedBy'] ), array( 'meta_key' => '_bn_quote_evento', 'meta_value' => (string) (int) $ev['id'] ) ); // phpcs:ignore WordPress.DB
			}
		}
	}

	$books = array_values( array_filter( (array) $s['bookmakers'] ) );
	if ( $books ) {
		$soon  = gmdate( 'Y-m-d H:i:s', $now + 2 * DAY_IN_SECONDS );
		$nowdt = gmdate( 'Y-m-d H:i:s', $now );
		$hour  = gmdate( 'Y-m-d H:i:s', $now - HOUR_IN_SECONDS );
		$ids   = $wpdb->get_col( $wpdb->prepare( // phpcs:ignore WordPress.DB
			"SELECT id FROM {$t} WHERE status = 'pending' AND start > %s AND ( start <= %s OR odds_updated IS NULL OR odds_updated < %s ) ORDER BY start ASC LIMIT 300", // phpcs:ignore WordPress.DB
			$nowdt,
			$soon,
			$hour
		) );
		foreach ( array_chunk( array_map( 'intval', $ids ), 10 ) as $chunk ) {
			$res = bnc_odds_get( '/odds/multi', array(
				'eventIds'   => implode( ',', $chunk ),
				'bookmakers' => implode( ',', $books ),
				'markets'    => BNC_ODDS_MARKETS,
			) );
			$rep['richieste']++;
			if ( is_wp_error( $res ) ) {
				$rep['errori'][] = $res->get_error_message();
				continue;
			}
			foreach ( $res as $ev ) {
				if ( empty( $ev['id'] ) ) {
					continue;
				}
				if ( empty( get_option( 'bnc_odds_sample' ) ) || ! empty( $ev['bookmakers'] ) ) {
					update_option( 'bnc_odds_sample', $ev, false ); // esempio grezzo per Operatori > Quote
				}
				$wpdb->update( $t, array( // phpcs:ignore WordPress.DB
					'odds'         => wp_json_encode( isset( $ev['bookmakers'] ) ? $ev['bookmakers'] : array() ),
					'urls'         => wp_json_encode( isset( $ev['urls'] ) ? $ev['urls'] : array() ),
					'status'       => (string) ( isset( $ev['status'] ) ? $ev['status'] : 'pending' ),
					'odds_updated' => current_time( 'mysql', true ),
				), array( 'id' => (int) $ev['id'] ) );
				$rep['quote']++;
			}
		}
	}

	// Pulizia: partite vecchie non collegate a nessun pronostico.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$t} WHERE start < %s AND id NOT IN ( SELECT CAST(meta_value AS UNSIGNED) FROM {$wpdb->postmeta} WHERE meta_key = '_bn_quote_evento' )", gmdate( 'Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS ) ) ); // phpcs:ignore WordPress.DB

	delete_transient( 'bnc_odds_lock' );
	$msg = sprintf( '%d partite, %d con quote, %d richieste', $rep['partite'], $rep['quote'], $rep['richieste'] );
	if ( $rep['errori'] ) {
		bnc_odds_log( implode( ' | ', array_slice( array_unique( $rep['errori'] ), 0, 3 ) ) );
	}
	bnc_odds_log( $msg, true );
	return $rep;
}

/* ---------- Nomi da mostrare (0.7.1) ---------- */

/** Campionato senza il paese davanti: "Italy - Serie A" diventa "Serie A". */
function bnc_odds_league_label( $name ) {
	$name = (string) $name;
	$pos  = strpos( $name, ' - ' );
	$out  = false !== $pos ? substr( $name, $pos + 3 ) : $name;
	return (string) apply_filters( 'bnc_odds_league_label', $out, $name );
}

/** Squadra con il nome usato in Italia: "Juventus Turin" diventa "Juventus", "SSC Napoli" diventa "Napoli". */
function bnc_odds_team( $name ) {
	$orig  = (string) $name;
	$fixed = array(
		'AC Milan'      => 'Milan',
		'Inter Milano'  => 'Inter',
		'Inter Milan'   => 'Inter',
		'Lazio Rome'    => 'Lazio',
		'Juventus Turin' => 'Juventus',
		'Hellas Verona' => 'Verona',
		'Paris Saint-Germain FC' => 'PSG',
		'Paris Saint Germain' => 'PSG',
	);
	if ( isset( $fixed[ $orig ] ) ) {
		$out = $fixed[ $orig ];
	} else {
		$out = preg_replace( '/^(?:SSC|AS|AC|US|ACF|SS|UC|FC|AFC|SC|CFC)\s+/u', '', $orig );
		$out = preg_replace( '/\s+(?:FC|CF|Calcio|BC|CFC|SC|1907|1913|1909|1919)$/u', '', $out );
		$out = '' !== trim( $out ) ? $out : $orig;
	}
	return (string) apply_filters( 'bnc_odds_team', $out, $orig );
}

/** Nome corto del bookmaker: "Marathonbet Scommesse" diventa "Marathonbet". */
function bnc_odds_book_label( $name ) {
	return trim( preg_replace( '/\s+(?:Scommesse|Sport|Casino|Casin\x{00F2})$/iu', '', (string) $name ) );
}

/* ---------- Lettura della copia locale ---------- */

function bnc_odds_event( $id ) {
	global $wpdb;
	$t = bnc_odds_table();
	$r = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$t} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $r ) {
		return null;
	}
	$r['odds'] = json_decode( (string) $r['odds'], true );
	$r['urls'] = json_decode( (string) $r['urls'], true );
	$r['odds'] = is_array( $r['odds'] ) ? $r['odds'] : array();
	$r['urls'] = is_array( $r['urls'] ) ? $r['urls'] : array();
	return $r;
}

/** Mercati del pronostico: chiave => etichetta, mercato API e esiti. */
function bnc_odds_markets() {
	return array(
		'1x2' => array( 'label' => 'Esito finale 1X2', 'api' => 'ML', 'out' => array( '1' => '1', 'X' => 'X', '2' => '2' ) ),
		'dc'  => array( 'label' => 'Doppia chance', 'api' => 'Double Chance', 'out' => array( '1X' => '1X', 'X2' => 'X2', '12' => '12' ) ),
		'uo'  => array( 'label' => 'Under/Over 2,5', 'api' => 'Totals', 'out' => array( 'over' => 'Over 2,5', 'under' => 'Under 2,5' ) ),
		'gg'  => array( 'label' => 'Goal/No Goal', 'api' => 'Both Teams To Score', 'out' => array( 'yes' => 'Goal', 'no' => 'No Goal' ) ),
	);
}

/** Chiave di un esito di Doppia chance, qualunque nome usi l'API. */
function bnc_odds_dc_key( $k ) {
	$k = strtolower( preg_replace( '/[^a-z0-9]/i', '', (string) $k ) );
	$map = array(
		'1x' => '1X', 'homedraw' => '1X', 'homeordraw' => '1X', 'drawhome' => '1X',
		'x2' => 'X2', 'drawaway' => 'X2', 'awayordraw' => 'X2', 'draworaway' => 'X2', 'awaydraw' => 'X2',
		'12' => '12', 'homeaway' => '12', 'homeoraway' => '12', 'awayhome' => '12',
	);
	return isset( $map[ $k ] ) ? $map[ $k ] : '';
}

/**
 * Quota di un esito per un bookmaker. $odds = elenco dei mercati del bookmaker.
 * Restituisce un float o 0 se manca.
 */
function bnc_odds_price( $odds, $mkt, $out ) {
	$out     = (string) $out; // 0.6.2: le chiavi "1", "2" e "12" arrivano come numeri dagli array PHP
	$markets = bnc_odds_markets();
	if ( ! isset( $markets[ $mkt ] ) || ! is_array( $odds ) ) {
		return 0.0;
	}
	$api = strtolower( $markets[ $mkt ]['api'] );
	foreach ( $odds as $m ) {
		if ( ! isset( $m['name'] ) || strtolower( (string) $m['name'] ) !== $api || empty( $m['odds'] ) ) {
			continue;
		}
		foreach ( (array) $m['odds'] as $row ) {
			if ( 'uo' === $mkt ) {
				if ( ! isset( $row['hdp'] ) || abs( (float) $row['hdp'] - 2.5 ) > 0.001 ) {
					continue;
				}
				$v = isset( $row[ $out ] ) ? $row[ $out ] : ( 'over' === $out && isset( $row['home'] ) ? $row['home'] : ( 'under' === $out && isset( $row['away'] ) ? $row['away'] : 0 ) );
				return (float) $v;
			}
			if ( '1x2' === $mkt ) {
				$k = array( '1' => 'home', 'X' => 'draw', '2' => 'away' );
				return isset( $row[ $k[ $out ] ] ) ? (float) $row[ $k[ $out ] ] : 0.0;
			}
			if ( 'gg' === $mkt ) {
				return isset( $row[ $out ] ) ? (float) $row[ $out ] : 0.0;
			}
			if ( 'dc' === $mkt ) {
				foreach ( $row as $rk => $rv ) {
					if ( is_scalar( $rv ) && bnc_odds_dc_key( $rk ) === $out ) {
						return (float) $rv;
					}
				}
			}
			return 0.0;
		}
	}
	return 0.0;
}

/** Bookmaker da mostrare: nome API => operatore Betnero (con logo e "Vai al sito"). */
function bnc_odds_shown_books() {
	$s   = bnc_odds_settings();
	$out = array();
	foreach ( (array) $s['bookmakers'] as $name ) {
		$op_id = isset( $s['map'][ $name ] ) ? (int) $s['map'][ $name ] : 0;
		if ( $op_id && 'publish' === get_post_status( $op_id ) ) {
			$out[ $name ] = bnc_operator( $op_id );
		}
	}
	return $out;
}

/* ---------- Riquadro "Quote del pronostico" negli articoli ---------- */

add_action( 'init', function () {
	foreach ( array( '_bn_quote_evento', '_bn_quote_scelta' ) as $k ) {
		register_post_meta( 'post', $k, array(
			'type'          => 'string',
			'single'        => true,
			'show_in_rest'  => false, // si salvano dal riquadro, non dall'editor a blocchi
			'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
				return current_user_can( 'edit_post', $post_id );
			},
		) );
	}
} );

add_action( 'add_meta_boxes_post', function () {
	add_meta_box( 'bnc-quote', 'Quote del pronostico', 'bnc_odds_metabox', 'post', 'side', 'high' );
} );

function bnc_odds_metabox( $post ) {
	global $wpdb;
	wp_nonce_field( 'bnc_quote_meta', 'bnc_quote_nonce' );
	$sel_ev = (int) get_post_meta( $post->ID, '_bn_quote_evento', true );
	$sel    = (string) get_post_meta( $post->ID, '_bn_quote_scelta', true );
	$t      = bnc_odds_table();
	// 0.9.16: anche le partite finite negli ultimi 7 giorni (pronostici scritti dopo, prove del widget a fine partita).
	$rows   = $wpdb->get_results( $wpdb->prepare( "SELECT id, league_name, home, away, start, status, score FROM {$t} WHERE ( start > %s AND status IN ('pending','live') ) OR ( start > %s AND status = 'settled' ) OR id = %d ORDER BY league_name ASC, start ASC LIMIT 500", gmdate( 'Y-m-d H:i:s', time() - 3 * HOUR_IN_SECONDS ), gmdate( 'Y-m-d H:i:s', time() - 7 * DAY_IN_SECONDS ), $sel_ev ), ARRAY_A ); // phpcs:ignore WordPress.DB
	if ( ! $rows && '' === bnc_odds_key() ) {
		echo '<p>Imposta la chiave e i campionati in <a href="' . esc_url( admin_url( 'edit.php?post_type=' . BNC_POST_TYPE . '&page=bnc-quote' ) ) . '">Operatori &gt; Quote</a>.</p>';
		return;
	}
	// 0.6.5: ricerca per squadra o campionato
	echo '<p><label for="bnc-quote-cerca"><strong>Partita</strong></label><br><input type="search" id="bnc-quote-cerca" placeholder="Cerca squadra o campionato" style="width:100%;margin-bottom:4px" oninput="bncQuoteCerca(this.value)"><br><select id="bnc-quote-ev" name="bnc_quote_evento" style="width:100%"><option value="">Nessuna</option>';
	$group = null;
	foreach ( $rows as $r ) {
		if ( $group !== $r['league_name'] ) {
			echo ( null !== $group ? '</optgroup>' : '' ) . '<optgroup label="' . esc_attr( bnc_odds_league_label( $r['league_name'] ) ) . '">';
			$group = $r['league_name'];
		}
		$when = wp_date( 'd/m H:i', strtotime( $r['start'] . ' UTC' ) );
		$fin  = 'settled' === $r['status'] ? ' (finita' . ( '' !== (string) $r['score'] ? ' ' . $r['score'] : '' ) . ')' : '';
		echo '<option value="' . (int) $r['id'] . '"' . selected( $sel_ev, (int) $r['id'], false ) . '>' . esc_html( $when . ' ' . bnc_odds_team( $r['home'] ) . ' - ' . bnc_odds_team( $r['away'] ) . $fin ) . '</option>';
	}
	echo ( null !== $group ? '</optgroup>' : '' ) . '</select></p>';
	echo '<p><label for="bnc-quote-sel"><strong>Pronostico</strong></label><br><select id="bnc-quote-sel" name="bnc_quote_scelta" style="width:100%"><option value="">Nessuno</option>';
	foreach ( bnc_odds_markets() as $mk => $m ) {
		echo '<optgroup label="' . esc_attr( $m['label'] ) . '">';
		foreach ( $m['out'] as $ok => $ol ) {
			$v = $mk . ':' . $ok;
			echo '<option value="' . esc_attr( $v ) . '"' . selected( $sel, $v, false ) . '>' . esc_html( $ol ) . '</option>';
		}
		echo '</optgroup>';
	}
	echo '</select></p>';
	echo bnc_odds_metabox_preview( $post->ID ); // phpcs:ignore WordPress.Security.EscapeOutput
	$auto = '1' === (string) bnc_odds_settings()['auto'];
	echo '<p class="description">' . ( $auto ? 'Le quote compaiono da sole prima del primo titolo H2 (pronostico e quattro mercati principali), il resto dei mercati va in fondo all\'articolo. Per metterle in un altro punto inserisci i blocchi "Quota del pronostico" e "Confronto quote".' : 'Inserisci i blocchi "Quota del pronostico" e "Confronto quote".' ) . ' Le partite arrivano dai campionati scelti in Operatori &gt; Quote.</p>';
	echo "<script>function bncQuoteCerca(q){q=(q||'').toLowerCase().trim();var s=document.getElementById('bnc-quote-ev');if(!s)return;s.querySelectorAll('optgroup').forEach(function(g){var any=false;var gl=(g.label||'').toLowerCase();g.querySelectorAll('option').forEach(function(o){var ok=!q||o.textContent.toLowerCase().indexOf(q)>-1||gl.indexOf(q)>-1||o.selected;o.hidden=!ok;o.disabled=!ok&&!o.selected;any=any||ok;});g.hidden=!any;});}</script>";
}

/** 0.6.5: sotto le scelte, le quote che vedranno i lettori (dopo il salvataggio). */
function bnc_odds_metabox_preview( $post_id ) {
	$ev_id = (int) get_post_meta( $post_id, '_bn_quote_evento', true );
	if ( ! $ev_id ) {
		return '';
	}
	$ev = bnc_odds_event( $ev_id );
	if ( ! $ev ) {
		return '<p style="color:#b32d2e">Partita non piu\' presente nella copia locale.</p>';
	}
	$sel             = (string) get_post_meta( $post_id, '_bn_quote_scelta', true );
	list( $mk, $out ) = array_pad( explode( ':', $sel, 2 ), 2, '' );
	$m               = bnc_odds_markets();
	$books           = bnc_odds_shown_books();
	$with            = 0;
	$best            = 0.0;
	$bname           = '';
	foreach ( $books as $name => $op ) {
		if ( ! empty( $ev['odds'][ $name ] ) ) {
			$with++;
		}
		if ( isset( $m[ $mk ]['out'][ $out ] ) ) {
			$p = bnc_odds_price( isset( $ev['odds'][ $name ] ) ? $ev['odds'][ $name ] : array(), $mk, $out );
			if ( $p > $best ) {
				$best  = $p;
				$bname = $op['name'];
			}
		}
	}
	$upd = $ev['odds_updated'] ? wp_date( 'd/m H:i', strtotime( $ev['odds_updated'] . ' UTC' ) ) : 'mai';
	$h   = '<div style="background:#f6f7f7;border:1px solid #dcdcde;border-radius:4px;padding:8px 10px;margin:0 0 10px">';
	$h  .= '<strong>' . esc_html( bnc_odds_team( $ev['home'] ) . ' - ' . bnc_odds_team( $ev['away'] ) ) . '</strong><br>';
	$h  .= esc_html( sprintf( '%d bookmaker su %d con quote, aggiornate alle %s.', $with, count( $books ), $upd ) );
	if ( isset( $m[ $mk ]['out'][ $out ] ) ) {
		$h .= '<br>' . esc_html( $m[ $mk ]['label'] . ': ' . $m[ $mk ]['out'][ $out ] ) . ' &rarr; ' . ( $best > 0 ? '<strong>' . esc_html( bnc_odds_fmt( $best ) ) . '</strong> ' . esc_html( $bname ) : '<span style="color:#b32d2e">nessuna quota per questo esito</span>' );
	}
	$stale = bnc_odds_stale( $ev );
	if ( $stale ) {
		$h .= '<br><span style="color:#b32d2e">' . esc_html( 2 === $stale ? 'Quote ferme da piu\' di 24 ore: sul sito non si vedono.' : 'Quote ferme da piu\' di 3 ore.' ) . '</span>';
	}
	return $h . '</div>';
}

/**
 * 0.6.5: quote vecchie di una partita non ancora iniziata.
 * 0 = fresche, 1 = ferme da piu' di 3 ore (si avvisa), 2 = da piu' di 24 ore (non si mostrano).
 */
function bnc_odds_stale( $ev ) {
	$start = strtotime( $ev['start'] . ' UTC' );
	if ( 'pending' !== $ev['status'] || $start <= time() ) {
		return 0;
	}
	$upd = $ev['odds_updated'] ? strtotime( $ev['odds_updated'] . ' UTC' ) : 0;
	if ( ! $upd ) {
		return 2;
	}
	$age = time() - $upd;
	return $age > DAY_IN_SECONDS ? 2 : ( $age > 3 * HOUR_IN_SECONDS ? 1 : 0 );
}

add_action( 'save_post_post', function ( $post_id ) {
	if ( ! isset( $_POST['bnc_quote_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bnc_quote_nonce'] ) ), 'bnc_quote_meta' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$ev  = isset( $_POST['bnc_quote_evento'] ) ? absint( $_POST['bnc_quote_evento'] ) : 0;
	$sel = isset( $_POST['bnc_quote_scelta'] ) ? sanitize_text_field( wp_unslash( $_POST['bnc_quote_scelta'] ) ) : '';
	$ok  = false;
	if ( $sel ) {
		list( $mk, $out ) = array_pad( explode( ':', $sel, 2 ), 2, '' );
		$m  = bnc_odds_markets();
		$ok = isset( $m[ $mk ]['out'][ $out ] );
	}
	$ev ? update_post_meta( $post_id, '_bn_quote_evento', (string) $ev ) : delete_post_meta( $post_id, '_bn_quote_evento' );
	$ok ? update_post_meta( $post_id, '_bn_quote_scelta', $sel ) : delete_post_meta( $post_id, '_bn_quote_scelta' );
} );

/* ---------- Blocchi ---------- */

add_action( 'init', function () {
	wp_register_style( 'betnero-quote', BETNERO_CORE_URL . 'assets/css/quote.css', array( 'betnero-tables' ), BETNERO_CORE_VERSION );
	wp_register_script( 'betnero-quote', BETNERO_CORE_URL . 'assets/js/quote.js', array(), BETNERO_CORE_VERSION, array( 'in_footer' => true, 'strategy' => 'defer' ) );
	$common = array(
		'editor_script' => 'betnero-blocks-editor',
		'style'         => 'betnero-quote',
		'category'      => 'betnero',
		'supports'      => array( 'html' => false ),
		'uses_context'  => array( 'postId' ),
	);
	register_block_type( 'betnero/quota-pronostico', $common + array(
		'title'           => 'Quota del pronostico',
		'attributes'      => array( 'className' => array( 'type' => 'string' ) ),
		'render_callback' => function ( $a, $c, $block ) {
			return bnc_odds_block( 'pronostico', bnc_odds_block_post( $block ), array() );
		},
	) );
	register_block_type( 'betnero/confronto-quote', $common + array(
		'title'           => 'Confronto quote',
		'attributes'      => array(
			'mercato'   => array( 'type' => 'string', 'default' => 'auto' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => function ( $a, $c, $block ) {
			return bnc_odds_block( 'confronto', bnc_odds_block_post( $block ), $a );
		},
	) );
}, 20 );

function bnc_odds_block_post( $block ) {
	if ( isset( $_GET['post_id'] ) && defined( 'REST_REQUEST' ) && REST_REQUEST ) { // phpcs:ignore WordPress.Security.NonceVerification
		return absint( $_GET['post_id'] ); // anteprima nell'editor
	}
	if ( is_object( $block ) && ! empty( $block->context['postId'] ) ) {
		return (int) $block->context['postId'];
	}
	return (int) get_the_ID();
}

/** Contenitore che il JS ricarica via REST dopo il caricamento della pagina. */
function bnc_odds_block( $tipo, $post_id, $a ) {
	$mercato = isset( $a['mercato'] ) ? sanitize_key( $a['mercato'] ) : 'auto';
	$inner   = bnc_odds_render( $tipo, $post_id, $mercato );
	if ( in_array( $tipo, array( 'entrambi-top', 'entrambi-rest' ), true ) && '' === $inner ) {
		return ''; // articolo senza quote: i segnaposto si svuotano e il testo resta
	}
	if ( '' === $inner ) {
		if ( ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return '';
		}
		return get_post_meta( $post_id, '_bn_quote_evento', true )
			? '<p class="bnq-empty">Quote non disponibili per ora: non ancora lette, oppure ferme da piu\' di 24 ore. Controlla in Operatori &gt; Quote.</p>'
			: '<p class="bnq-empty">Collega partita e pronostico nel riquadro "Quote del pronostico" (a destra), salva, poi aggiorna l\'anteprima.</p>';
	}
	wp_enqueue_script( 'betnero-quote' );
	return '<div class="bnq-live" data-post="' . (int) $post_id . '" data-tipo="' . esc_attr( $tipo ) . '" data-mercato="' . esc_attr( $mercato ) . '" data-url="' . esc_url( rest_url( 'betnero/v1/quote/blocco' ) ) . '">' . $inner . '</div>';
}

function bnc_odds_when( $start ) {
	return wp_date( 'j F Y, H:i', strtotime( $start . ' UTC' ) );
}

function bnc_odds_fmt( $v ) {
	return $v > 0 ? number_format_i18n( $v, 2 ) : '-';
}

/**
 * 0.9.19: l'esito e' uscito? Dal risultato finale "h-a". true / false / null (non si sa).
 */
function bnc_odds_won( $mk, $ok, $score ) {
	if ( ! preg_match( '/^\s*(\d+)\s*-\s*(\d+)\s*$/', (string) $score, $m ) ) {
		return null;
	}
	$h  = (int) $m[1];
	$a  = (int) $m[2];
	$ok = (string) $ok;
	$r  = $h > $a ? '1' : ( $h < $a ? '2' : 'X' );
	switch ( $mk ) {
		case '1x2':
			return $ok === $r;
		case 'dc':
			return false !== strpos( $ok, $r ) || ( '12' === $ok && 'X' !== $r );
		case 'uo':
			return 'over' === $ok ? $h + $a > 2.5 : ( 'under' === $ok ? $h + $a < 2.5 : null );
		case 'gg':
			return 'yes' === $ok ? ( $h > 0 && $a > 0 ) : ( 'no' === $ok ? ! ( $h > 0 && $a > 0 ) : null );
	}
	return null;
}

/**
 * 0.9.25: le due parti del riquadro automatico negli articoli. La prima e' il pronostico piu'
 * i quattro mercati principali, la seconda gli altri mercati. Senza quote la seconda e' vuota.
 */
function bnc_odds_article_parts( $post_id ) {
	$ev_id = (int) get_post_meta( $post_id, '_bn_quote_evento', true );
	$ev    = $ev_id ? bnc_odds_event( $ev_id ) : null;
	$top   = bnc_odds_render( 'pronostico', $post_id, 'auto' );
	$mk    = ( $ev && function_exists( 'bnc_match_render' ) ) ? bnc_match_render( $ev, true ) : '';
	if ( '' !== $mk ) {
		$mk = (string) apply_filters( 'bnc_odds_entrambi_after', $mk, $post_id );
	}
	$parts = explode( '<!--bn-split-->', $mk, 2 );
	return array( $top . $parts[0], isset( $parts[1] ) ? $parts[1] : '' );
}

function bnc_odds_render( $tipo, $post_id, $mercato = 'auto' ) {
	if ( 'entrambi-top' === $tipo || 'entrambi-rest' === $tipo ) { // 0.9.25: articolo diviso in due parti
		$p = bnc_odds_article_parts( $post_id );
		return 'entrambi-top' === $tipo ? $p[0] : $p[1];
	}
	if ( 'entrambi' === $tipo ) { // 0.6.5: inserimento automatico, pronostico e confronto insieme
		$a = bnc_odds_render( 'pronostico', $post_id, 'auto' );
		$GLOBALS['bnc_odds_nohead'] = '' !== $a;
		$b = bnc_odds_render( 'confronto', $post_id, 'auto' );
		unset( $GLOBALS['bnc_odds_nohead'] );
		return '' === $a . $b ? '' : (string) apply_filters( 'bnc_odds_entrambi_after', $a . $b, $post_id ); // 0.8.0: quote maggiorate della partita
	}
	$ev_id = (int) get_post_meta( $post_id, '_bn_quote_evento', true );
	$ev    = $ev_id ? bnc_odds_event( $ev_id ) : null;
	if ( ! $ev ) {
		return '';
	}
	$sel             = (string) get_post_meta( $post_id, '_bn_quote_scelta', true );
	list( $mk, $out ) = array_pad( explode( ':', $sel, 2 ), 2, '' );
	$markets         = bnc_odds_markets();
	$books           = bnc_odds_shown_books();
	$started         = 'pending' !== $ev['status'] || strtotime( $ev['start'] . ' UTC' ) <= time();
	$stale           = bnc_odds_stale( $ev );
	if ( 2 === $stale ) {
		return ''; // 0.6.5: quote ferme da piu' di 24 ore, meglio non mostrarle
	}
	$upd             = $ev['odds_updated'] ? wp_date( 'd/m H:i', strtotime( $ev['odds_updated'] . ' UTC' ) ) : '';
	$note            = $started ? ( '' !== $ev['score'] && 'settled' === $ev['status'] ? 'Risultato finale ' . $ev['score'] . '. Quote pre-partita.' : 'Partita iniziata: quote pre-partita.' ) : ( $upd ? 'Quote aggiornate alle ' . $upd . ( $stale ? ' (aggiornamento in ritardo, controlla sul sito del bookmaker).' : '.' ) : '' );
	$final           = 'settled' === $ev['status'] && '' !== $ev['score'];
	$res_badge       = $final ? '<span class="bnq-res">Finale ' . esc_html( str_replace( '-', ' - ', $ev['score'] ) ) . '</span>' : ( $started ? '<span class="bnq-res is-live">Partita iniziata</span>' : '' );
	if ( $final ) {
		$note = 'Partita finita ' . $ev['score'] . ': queste erano le quote prima del calcio d\'inizio.';
	}
	$head            = '<div class="bnq-match"><span class="bnq-league">' . esc_html( bnc_odds_league_label( $ev['league_name'] ) ) . ' &middot; ' . esc_html( bnc_odds_when( $ev['start'] ) ) . $res_badge . '</span><strong class="bnq-teams">' . esc_html( bnc_odds_team( $ev['home'] ) ) . ' <span>-</span> ' . esc_html( bnc_odds_team( $ev['away'] ) ) . '</strong></div>';

	if ( 'pronostico' === $tipo ) {
		if ( ! isset( $markets[ $mk ]['out'][ $out ] ) ) {
			return '';
		}
		$best = 0.0;
		$bop  = null;
		foreach ( $books as $name => $op ) {
			$p = bnc_odds_price( isset( $ev['odds'][ $name ] ) ? $ev['odds'][ $name ] : array(), $mk, $out );
			if ( $p > $best ) {
				$best = $p;
				$bop  = $op;
			}
		}
		$won = $final ? bnc_odds_won( $mk, $out, $ev['score'] ) : null;
		$tag = null === $won ? '' : ( $won ? '<span class="bnq-won is-yes">&#10003; Pronostico vincente</span>' : '<span class="bnq-won is-no">Pronostico non vincente</span>' );
		$h  = '<div class="bnq bnq-pick' . ( $final ? ' is-final' : '' ) . '">' . $head . '<div class="bnq-pick-row"><div class="bnq-pick-sel"><span class="bnq-k">Il nostro pronostico</span><strong>' . esc_html( $markets[ $mk ]['label'] . ': ' . $markets[ $mk ]['out'][ $out ] ) . '</strong>' . $tag . '</div>';
		if ( $bop ) {
			$h .= '<div class="bnq-pick-best"><span class="bnq-k">' . ( $started ? 'Quota migliore pre-partita' : 'Quota migliore' ) . '</span><strong class="bnq-odd">' . esc_html( bnc_odds_fmt( $best ) ) . '</strong></div><div class="bnq-pick-op"><div class="bnq-logo">' . bnc_logo( $bop ) . '</div>' . ( $started ? '' : bnc_go_button( $bop, 'Vai al sito', 'bnt-btn bnt-go' ) ) . '</div>';
		}
		return $h . '</div>' . ( $note ? '<p class="bnq-note">' . esc_html( $note ) . '</p>' : '' ) . '<p class="bnq-adm">18+ | Gioca responsabilmente | Le quote possono variare</p></div>';
	}

	// Confronto
	$cm = ( 'auto' === $mercato || ! isset( $markets[ $mercato ] ) ) ? ( isset( $markets[ $mk ] ) ? $mk : '1x2' ) : $mercato;
	$cols = $markets[ $cm ]['out'];
	$grid = array();
	$best = array_fill_keys( array_keys( $cols ), 0.0 );
	foreach ( $books as $name => $op ) {
		$row = array();
		$any = false;
		foreach ( $cols as $ck => $cl ) {
			$row[ $ck ] = bnc_odds_price( isset( $ev['odds'][ $name ] ) ? $ev['odds'][ $name ] : array(), $cm, $ck );
			$any        = $any || $row[ $ck ] > 0;
			$best[ $ck ] = max( $best[ $ck ], $row[ $ck ] );
		}
		if ( $any ) {
			$grid[ $name ] = array( 'op' => $op, 'p' => $row );
		}
	}
	if ( ! $grid ) {
		return '<div class="bnq bnq-cmp">' . $head . '<p class="bnq-note">Quote non ancora disponibili per questa partita.</p></div>';
	}
	$solo = ( ! empty( $GLOBALS['bnc_odds_nohead'] ) || has_block( 'betnero/quota-pronostico', $post_id ) ) ? '' : $head; // la testata c'e' gia' nel blocco del pronostico
	$h  = '<div class="bnq bnq-cmp' . ( $started ? ' is-final' : '' ) . '">' . $solo . '<p class="bnq-title">' . esc_html( ( $started ? 'Quote pre-partita: ' : 'Confronto quote: ' ) . $markets[ $cm ]['label'] ) . ( '' === $solo ? $res_badge : '' ) . '</p>';
	$h .= '<div class="bnq-table" style="--bnq-cols:' . count( $cols ) . '"><div class="bnq-tr bnq-th"><span>Bookmaker</span>';
	foreach ( $cols as $ck => $cl ) {
		$is_pick = $cm === $mk && (string) $ck === (string) $out;
		$is_won  = $final && true === bnc_odds_won( $cm, $ck, $ev['score'] );
		$h      .= '<span class="' . ( $is_pick ? 'is-pick' : '' ) . ( $is_won ? ' is-won' : '' ) . '"' . ( $is_pick ? ' title="Il nostro pronostico"' : '' ) . '>' . esc_html( $cl ) . ( $is_pick ? ' &#9733;' : '' ) . ( $is_won ? ' &#10003;' : '' ) . '</span>';
	}
	$h .= '<span></span></div>';
	foreach ( $grid as $g ) {
		$h .= '<div class="bnq-tr"><span class="bnq-logo">' . bnc_logo( $g['op'] ) . '</span>';
		foreach ( $cols as $ck => $cl ) {
			$is_best = $g['p'][ $ck ] > 0 && abs( $g['p'][ $ck ] - $best[ $ck ] ) < 0.0001;
			$is_pick = $cm === $mk && (string) $ck === (string) $out;
			$is_won  = $final && true === bnc_odds_won( $cm, $ck, $ev['score'] );
			$h      .= '<span class="bnq-odd' . ( $is_best ? ' is-best' : '' ) . ( $is_pick ? ' is-pick' : '' ) . ( $is_won ? ' is-won' : '' ) . '" data-label="' . esc_attr( $cl ) . '">' . esc_html( bnc_odds_fmt( $g['p'][ $ck ] ) ) . '</span>';
		}
		// 0.9.19: a partita iniziata niente "Vai al sito" (le quote non si possono piu' giocare).
		$h .= '<span>' . ( $started ? '' : bnc_go_button( $g['op'], 'Vai al sito', 'bnt-btn bnt-go' ) ) . '</span></div>';
	}
	$legend = $final ? ' In verde la quota più alta di ogni esito; con il segno &#10003; l\'esito uscito.' : ' In verde la quota migliore.';
	return $h . '</div>' . ( $note ? '<p class="bnq-note">' . esc_html( $note ) . wp_kses_post( $legend ) . '</p>' : '' ) . '<p class="bnq-adm">18+ | Gioca responsabilmente | Le quote possono variare</p></div>';
}

/* ---------- 0.6.5: quote automatiche nei pronostici ---------- */

/**
 * Articolo con partita collegata e senza i blocchi delle quote: due segnaposto, uno prima del primo H2
 * (priorita' 19, prima della toplist automatica che usa la 20), o dopo il primo paragrafo, e uno in fondo.
 * All'ultima priorita' i segnaposto diventano le due parti del riquadro, cosi' altri plugin non ci infilano banner.
 * 0.9.25: il primo prende il pronostico e i quattro mercati principali, il secondo il resto dei mercati,
 * con il testo dell'articolo in mezzo.
 */
add_filter( 'the_content', function ( $content ) {
	if ( '1' !== (string) bnc_odds_settings()['auto'] || is_admin() || is_feed() || wp_is_json_request() || doing_action( 'wp_head' ) ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) || get_the_ID() !== get_queried_object_id() ) {
		return $content;
	}
	$pid = get_the_ID();
	if ( ! get_post_meta( $pid, '_bn_quote_evento', true ) || has_block( 'betnero/quota-pronostico', $pid ) || has_block( 'betnero/confronto-quote', $pid ) || false !== strpos( $content, 'class="bnq-live" data-post=' ) ) {
		return $content;
	}
	$top  = '<div class="bnq-auto" data-bnq-auto="top"></div>';
	$rest = '<div class="bnq-auto" data-bnq-auto="rest"></div>';
	$pos  = stripos( $content, '<h2' );
	if ( false !== $pos ) {
		$content = substr( $content, 0, $pos ) . $top . substr( $content, $pos );
	} else {
		$pos     = stripos( $content, '</p>' );
		$content = false !== $pos ? substr( $content, 0, $pos + 4 ) . $top . substr( $content, $pos + 4 ) : $content . $top;
	}
	return $content . $rest;
}, 19 );

add_filter( 'the_content', function ( $content ) {
	if ( false === strpos( $content, 'data-bnq-auto="top"' ) && false === strpos( $content, 'data-bnq-auto="rest"' ) ) {
		return $content;
	}
	wp_enqueue_style( 'betnero-quote' );
	$pid     = get_the_ID();
	$top     = bnc_odds_block( 'entrambi-top', $pid, array() );
	$rest    = bnc_odds_block( 'entrambi-rest', $pid, array() );
	if ( '' !== $top || '' !== $rest ) {
		wp_enqueue_script( 'betnero-partita' ); // 0.9.26: schede e linee dei mercati anche negli articoli
	}
	$content = str_replace( '<div class="bnq-auto" data-bnq-auto="top"></div>', $top, $content );
	// 0.9.25: il secondo segnaposto si sostituisce sempre, anche con stringa vuota: il testo non deve sparire.
	return str_replace( '<div class="bnq-auto" data-bnq-auto="rest"></div>', $rest, $content );
}, PHP_INT_MAX );

/* ---------- REST: blocchi aggiornati e anteprima ---------- */

add_action( 'rest_api_init', function () {
	register_rest_route( 'betnero/v1', '/quote/blocco', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'post'    => array( 'type' => 'integer', 'required' => true ),
			'tipo'    => array( 'type' => 'string', 'enum' => array( 'pronostico', 'confronto', 'entrambi', 'entrambi-top', 'entrambi-rest' ), 'required' => true ),
			'mercato' => array( 'type' => 'string', 'default' => 'auto' ),
		),
		'callback'            => function ( $req ) {
			$pid = (int) $req['post'];
			if ( 'publish' !== get_post_status( $pid ) && ! current_user_can( 'edit_post', $pid ) ) {
				return new WP_Error( 'bnc_not_found', 'Non trovato', array( 'status' => 404 ) );
			}
			$res = rest_ensure_response( array( 'html' => bnc_odds_render( (string) $req['tipo'], $pid, sanitize_key( $req['mercato'] ) ) ) );
			$res->header( 'Cache-Control', 'public, max-age=120' );
			return $res;
		},
	) );
} );

/* ---------- Operatori > Quote ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=' . BNC_POST_TYPE, 'Quote (odds-api.io)', 'Quote', 'manage_options', 'bnc-quote', 'bnc_odds_page' );
}, 21 );

function bnc_odds_guess_operator( $api_name, $ops ) {
	$n = strtolower( preg_replace( '/\s+IT$/i', '', $api_name ) );
	$n = preg_replace( '/[^a-z0-9]/', '', $n );
	foreach ( $ops as $op ) {
		$o = preg_replace( '/[^a-z0-9]/', '', strtolower( preg_replace( '/\s+(scommesse|sport)$/i', '', $op['name'] ) ) );
		if ( '' !== $o && ( 0 === strpos( $o, $n ) || 0 === strpos( $n, $o ) ) ) {
			return (int) $op['id'];
		}
	}
	return 0;
}

function bnc_odds_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	global $wpdb;
	$s      = bnc_odds_settings();
	$notice = array();

	if ( isset( $_POST['bnc_odds_action'] ) && check_admin_referer( 'bnc_odds' ) ) {
		$act = sanitize_key( wp_unslash( $_POST['bnc_odds_action'] ) );
		if ( 'key' === $act ) {
			$k = isset( $_POST['bnc_odds_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bnc_odds_key'] ) ) ) : '';
			if ( '' !== $k ) {
				update_option( 'bnc_odds_key', $k, false );
				$act = 'books'; // subito dopo, elenco dei bookmaker scelti
				$notice[] = array( 'success', 'Chiave salvata.' );
			}
		}
		if ( 'books' === $act ) {
			$r = bnc_odds_get( '/bookmakers/selected' );
			if ( is_wp_error( $r ) ) {
				$notice[] = array( 'error', 'Bookmaker non letti: ' . $r->get_error_message() );
			} else {
				$s['bookmakers'] = bnc_odds_names( $r );
				$ops             = bnc_operators( 'scommesse', false );
				foreach ( $s['bookmakers'] as $b ) {
					if ( ! isset( $s['map'][ $b ] ) ) {
						$s['map'][ $b ] = bnc_odds_guess_operator( $b, $ops );
					}
				}
				update_option( 'bnc_odds', $s, false );
				$notice[] = array( 'success', count( $s['bookmakers'] ) . ' bookmaker letti da odds-api.io.' );
			}
		}
		if ( 'leagues_load' === $act ) {
			$r = bnc_odds_get( '/leagues', array( 'sport' => 'football' ) );
			if ( is_wp_error( $r ) ) {
				$notice[] = array( 'error', 'Campionati non letti: ' . $r->get_error_message() );
			} else {
				update_option( 'bnc_odds_leagues', $r, false );
				$notice[] = array( 'success', count( $r ) . ' campionati letti.' );
			}
		}
		if ( 'save' === $act ) {
			$in   = isset( $_POST['bnc_odds'] ) ? wp_unslash( $_POST['bnc_odds'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$map  = array();
			foreach ( (array) $s['bookmakers'] as $b ) {
				$map[ $b ] = isset( $in['map'][ md5( $b ) ] ) ? absint( $in['map'][ md5( $b ) ] ) : 0;
			}
			$s['map']     = $map;
			$s['leagues'] = array_values( array_filter( array_map( 'sanitize_title', isset( $in['leagues'] ) ? (array) $in['leagues'] : array() ) ) );
			$s['days']    = max( 1, min( 14, absint( isset( $in['days'] ) ? $in['days'] : 7 ) ) );
			$s['auto']    = empty( $in['auto'] ) ? '0' : '1';
			update_option( 'bnc_odds', $s, false );
			$notice[] = array( 'success', 'Impostazioni salvate.' );
		}
		if ( 'sync' === $act ) {
			$rep      = bnc_odds_sync( true );
			$notice[] = array( empty( $rep['errori'] ) ? 'success' : 'warning', 'Aggiornamento: ' . ( isset( $rep['partite'] ) ? sprintf( '%d partite, %d con quote, %d richieste.', $rep['partite'], $rep['quote'], $rep['richieste'] ) : '' ) . ( empty( $rep['errori'] ) ? '' : ' Errori: ' . implode( ' | ', array_slice( array_unique( $rep['errori'] ), 0, 3 ) ) ) );
		}
		if ( 'key_delete' === $act ) {
			delete_option( 'bnc_odds_key' );
			$notice[] = array( 'success', 'Chiave cancellata: la lettura delle quote e\' ferma.' );
		}
	}

	if ( $notice ) {
		bnc_odds_schedule();
	}
	$key   = bnc_odds_key();
	$state = (array) get_option( 'bnc_odds_state', array() );
	$cnt   = (array) get_option( 'bnc_odds_count', array() );
	$t     = bnc_odds_table();
	$n_ev  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t}" ); // phpcs:ignore WordPress.DB
	$n_od  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$t} WHERE odds_updated IS NOT NULL" ); // phpcs:ignore WordPress.DB
	$form  = function ( $action, $label, $class = 'button' ) {
		return '<form method="post" style="display:inline-block;margin:0 6px 6px 0">' . wp_nonce_field( 'bnc_odds', '_wpnonce', true, false ) . '<input type="hidden" name="bnc_odds_action" value="' . esc_attr( $action ) . '"><button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	};

	echo '<div class="wrap"><h1>Quote (odds-api.io)</h1>';
	foreach ( $notice as $n ) {
		echo '<div class="notice notice-' . esc_attr( $n[0] ) . '"><p>' . esc_html( $n[1] ) . '</p></div>';
	}

	echo '<h2>1. Chiave</h2>';
	if ( $key ) {
		echo '<p>Chiave salvata, finisce con <code>&hellip;' . esc_html( substr( $key, -4 ) ) . '</code>. Non viene mai mostrata per intero.</p>';
		echo $form( 'key_delete', 'Cancella la chiave' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<form method="post">';
	wp_nonce_field( 'bnc_odds' );
	echo '<input type="hidden" name="bnc_odds_action" value="key"><input type="text" name="bnc_odds_key" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" style="-webkit-text-security:disc" class="regular-text" placeholder="' . ( $key ? 'Nuova chiave (per sostituirla)' : 'Incolla la chiave di odds-api.io' ) . '"> <button class="button button-primary">Salva la chiave</button></form>';

	if ( ! $key ) {
		echo '</div>';
		return;
	}

	echo '<h2>2. Bookmaker e campionati</h2>';
	echo '<p>I bookmaker sono quelli scelti nel pannello di odds-api.io. Se li cambi nel pannello, premi "Rileggi i bookmaker".</p>';
	echo $form( 'books', 'Rileggi i bookmaker' ) . $form( 'leagues_load', 'Carica i campionati' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '<form method="post">';
	wp_nonce_field( 'bnc_odds' );
	echo '<input type="hidden" name="bnc_odds_action" value="save">';
	$ops = bnc_operators( 'scommesse', false );
	echo '<table class="widefat striped" style="max-width:720px"><thead><tr><th>Bookmaker su odds-api.io</th><th>Operatore Betnero (logo e "Vai al sito")</th></tr></thead><tbody>';
	if ( ! $s['bookmakers'] ) {
		echo '<tr><td colspan="2">Nessun bookmaker letto: premi "Rileggi i bookmaker".</td></tr>';
	}
	foreach ( (array) $s['bookmakers'] as $b ) {
		$cur = isset( $s['map'][ $b ] ) ? (int) $s['map'][ $b ] : 0;
		echo '<tr><td>' . esc_html( $b ) . '</td><td><select name="bnc_odds[map][' . esc_attr( md5( $b ) ) . ']"><option value="0">Non mostrare</option>';
		foreach ( $ops as $op ) {
			echo '<option value="' . (int) $op['id'] . '"' . selected( $cur, (int) $op['id'], false ) . '>' . esc_html( $op['name'] ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table><p class="description">Un bookmaker senza operatore Betnero non compare: non avremmo un link affiliato.</p>';

	$leagues = (array) get_option( 'bnc_odds_leagues', array() );
	echo '<h3>Campionati da seguire</h3>';
	if ( ! $leagues && ! $s['leagues'] ) {
		echo '<p>Premi "Carica i campionati".</p>';
	} else {
		$known = array();
		foreach ( $leagues as $l ) {
			if ( ! empty( $l['slug'] ) ) {
				$known[ $l['slug'] ] = ( isset( $l['name'] ) ? $l['name'] : $l['slug'] ) . ( isset( $l['eventsCount'] ) ? ' (' . (int) $l['eventsCount'] . ')' : '' );
			}
		}
		foreach ( $s['leagues'] as $sl ) {
			if ( ! isset( $known[ $sl ] ) ) {
				$known[ $sl ] = $sl;
			}
		}
		uksort( $known, function ( $a, $b ) use ( $s ) {
			$ia = in_array( $a, $s['leagues'], true );
			$ib = in_array( $b, $s['leagues'], true );
			return $ia === $ib ? strcmp( $a, $b ) : ( $ia ? -1 : 1 );
		} );
		echo '<p><input type="search" placeholder="Cerca (es. italy, england, uefa)" oninput="var q=this.value.toLowerCase();document.querySelectorAll(\'#bnc-leagues label\').forEach(function(l){l.style.display=l.textContent.toLowerCase().indexOf(q)>-1?\'block\':\'none\'})" class="regular-text"></p>';
		echo '<div id="bnc-leagues" style="max-height:320px;overflow:auto;border:1px solid #dcdcde;background:#fff;padding:8px 12px;max-width:720px">';
		foreach ( $known as $slug => $label ) {
			echo '<label style="display:block;margin:2px 0"><input type="checkbox" name="bnc_odds[leagues][]" value="' . esc_attr( $slug ) . '"' . checked( in_array( $slug, $s['leagues'], true ), true, false ) . '> ' . esc_html( $label ) . ' <code style="font-size:11px">' . esc_html( $slug ) . '</code></label>';
		}
		echo '</div><p class="description">Tra parentesi le partite in programma. Ogni campionato costa una richiesta ogni 10 minuti.</p>';
	}
	echo '<p><label>Partite fino a <input type="number" min="1" max="14" name="bnc_odds[days]" value="' . (int) $s['days'] . '" style="width:60px"> giorni in avanti</label></p>';
	echo '<p><label><input type="checkbox" name="bnc_odds[auto]" value="1"' . checked( '1', (string) $s['auto'], false ) . '> Negli articoli con partita collegata mostra le quote da sole, prima del primo titolo H2 (se non ci sono gia\' i blocchi)</label></p>';
	echo '<p><button class="button button-primary">Salva bookmaker e campionati</button></p></form>';

	echo '<h2>3. Stato</h2><table class="widefat" style="max-width:720px"><tbody>';
	$next = wp_next_scheduled( 'bnc_odds_sync' );
	$rows = array(
		'Prossimo aggiornamento automatico' => $next ? wp_date( 'd/m H:i:s', $next ) : 'non programmato (servono chiave e almeno un campionato)',
		'Ultimo aggiornamento riuscito'    => ! empty( $state['last_ok'] ) ? wp_date( 'd/m H:i:s', strtotime( $state['last_ok'] . ' UTC' ) ) . ': ' . $state['last_ok_msg'] : '-',
		'Ultimo errore'                    => ! empty( $state['last_error'] ) ? wp_date( 'd/m H:i:s', strtotime( $state['last_error'] . ' UTC' ) ) . ': ' . $state['last_error_msg'] : '-',
		'Partite nella copia locale'       => $n_ev . ' (con quote: ' . $n_od . ')',
		'Richieste in quest\'ora'           => ( isset( $cnt['h'] ) && gmdate( 'YmdH' ) === $cnt['h'] ? (int) $cnt['n'] : 0 ) . ' su 5.000',
	);
	foreach ( $rows as $k => $v ) {
		echo '<tr><th style="width:240px">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
	}
	echo '</tbody></table><p>';
	echo $form( 'sync', 'Aggiorna adesso', 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</p>';

	$sample = get_option( 'bnc_odds_sample' );
	if ( $sample ) {
		echo '<details style="max-width:900px"><summary>Esempio di risposta di odds-api.io (una partita, per controllo)</summary><pre style="max-height:420px;overflow:auto;background:#fff;border:1px solid #dcdcde;padding:10px;font-size:11px">' . esc_html( wp_json_encode( $sample, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) . '</pre></details>';
	}
	echo '</div>';
}
