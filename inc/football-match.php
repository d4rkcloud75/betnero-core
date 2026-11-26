<?php
/**
 * Widget partita con i dati di API-Football (0.9.12).
 *
 * - Calendario dei campionati seguiti, letto con la classifica (due volte al giorno):
 *   serve a collegare la partita delle quote (odds-api.io) alla partita di API-Football.
 * - Dettagli della partita letti dal server e salvati in cache:
 *   prima della partita precedenti, indisponibili e (da un'ora prima) formazioni, ogni 6 ore
 *   (ogni 10 minuti nell'ultima ora); durante la partita ogni 2 minuti; finita, una volta sola.
 * - Widget con le schede: prima Precedenti, Forma, Formazioni, Indisponibili;
 *   durante e dopo Eventi, Statistiche, Formazioni, Giocatori.
 *
 * La chiave resta sul server: nella pagina c'e' solo l'HTML.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Calendario e collegamento con le quote ---------- */

/** Legge il calendario di ogni campionato seguito. Restituisce gli errori. */
function bnc_fdm_sync_fixtures() {
	$err = array();
	$all = array();
	foreach ( bnc_fd_leagues() as $l ) {
		$r = bnc_fd_get( '/fixtures', array(
			'league' => (int) $l['id'],
			'season' => (int) $l['stagione'],
			'from'   => gmdate( 'Y-m-d', time() - 7 * DAY_IN_SECONDS ), // 0.9.14: anche l'ultima giornata giocata
			'to'     => gmdate( 'Y-m-d', time() + 10 * DAY_IN_SECONDS ),
		) );
		if ( is_wp_error( $r ) ) {
			$err[] = $l['nome'] . ' (calendario): ' . $r->get_error_message();
			continue;
		}
		foreach ( (array) $r as $f ) {
			if ( empty( $f['fixture']['id'] ) ) {
				continue;
			}
			$all[] = array(
				'id'    => (int) $f['fixture']['id'],
				'ts'    => (int) $f['fixture']['timestamp'],
				'lega'  => (int) $l['id'],
				'home'  => array( 'id' => (int) $f['teams']['home']['id'], 'name' => (string) $f['teams']['home']['name'] ),
				'away'  => array( 'id' => (int) $f['teams']['away']['id'], 'name' => (string) $f['teams']['away']['name'] ),
				'st'    => (string) $f['fixture']['status']['short'],
				'gol'   => array( $f['goals']['home'], $f['goals']['away'] ),
			);
		}
	}
	update_option( 'bnc_fdm_fixtures', $all, false );
	return $err;
}

function bnc_fdm_norm( $name ) {
	$n = function_exists( 'bnc_odds_team' ) ? bnc_odds_team( $name ) : $name;
	return preg_replace( '/[^a-z0-9]/', '', strtolower( remove_accents( (string) $n ) ) );
}

/** Partita di API-Football per una partita delle quote (stesse squadre, inizio entro 6 ore). */
function bnc_fdm_fixture_for( $ev ) {
	$ts = strtotime( $ev['start'] . ' UTC' );
	$h  = bnc_fdm_norm( $ev['home'] );
	$a  = bnc_fdm_norm( $ev['away'] );
	$same = function ( $x, $y ) {
		return '' !== $x && '' !== $y && ( $x === $y || 0 === strpos( $x, $y ) || 0 === strpos( $y, $x ) );
	};
	foreach ( (array) get_option( 'bnc_fdm_fixtures', array() ) as $f ) {
		if ( abs( (int) $f['ts'] - $ts ) > 6 * HOUR_IN_SECONDS ) {
			continue;
		}
		if ( $same( bnc_fdm_norm( $f['home']['name'] ), $h ) && $same( bnc_fdm_norm( $f['away']['name'] ), $a ) ) {
			return $f;
		}
	}
	return null;
}

/* ---------- Dettagli della partita, in cache ---------- */

function bnc_fdm_live_codes() {
	return array( '1H', 'HT', '2H', 'ET', 'BT', 'P', 'SUSP', 'INT', 'LIVE' );
}

function bnc_fdm_done_codes() {
	return array( 'FT', 'AET', 'PEN', 'AWD', 'WO' );
}

/**
 * Dati del widget per una partita. Struttura:
 * [ 'fixture' => risposta di /fixtures?id= (con events, lineups, statistics, players),
 *   'h2h' => [...], 'injuries' => [...], 'agg' => timestamp ]
 */
function bnc_fdm_data( $f ) {
	$key  = 'bnc_fdm_' . (int) $f['id'];
	$data = get_transient( $key );
	if ( is_array( $data ) ) {
		return $data;
	}
	if ( get_transient( $key . '_lock' ) ) {
		return null; // un'altra visita sta gia' leggendo i dati
	}
	set_transient( $key . '_lock', 1, 60 );
	$data = array( 'fixture' => null, 'h2h' => array(), 'injuries' => array(), 'agg' => time() );

	$fx = bnc_fd_get( '/fixtures', array( 'id' => (int) $f['id'] ) );
	if ( ! is_wp_error( $fx ) && ! empty( $fx[0] ) ) {
		$data['fixture'] = $fx[0];
	}
	$st   = isset( $data['fixture']['fixture']['status']['short'] ) ? (string) $data['fixture']['fixture']['status']['short'] : 'NS';
	$live = in_array( $st, bnc_fdm_live_codes(), true );
	$done = in_array( $st, bnc_fdm_done_codes(), true );
	if ( ! $live && ! $done ) {
		$h2h = bnc_fd_get( '/fixtures/headtohead', array( 'h2h' => (int) $f['home']['id'] . '-' . (int) $f['away']['id'], 'last' => 5 ) );
		$data['h2h'] = is_wp_error( $h2h ) ? array() : (array) $h2h;
		$inj = bnc_fd_get( '/injuries', array( 'fixture' => (int) $f['id'] ) );
		$data['injuries'] = is_wp_error( $inj ) ? array() : (array) $inj;
	}
	$to_start = (int) $f['ts'] - time();
	if ( empty( $data['fixture'] ) ) {
		$ttl = 2 * MINUTE_IN_SECONDS; // 0.9.22: API-Football non ha risposto, si riprova presto
	} elseif ( $done ) {
		$ttl = WEEK_IN_SECONDS;
	} elseif ( $live ) {
		$ttl = MINUTE_IN_SECONDS; // 0.9.13: in diretta, al massimo un minuto di ritardo
	} elseif ( $to_start < 75 * MINUTE_IN_SECONDS ) {
		$ttl = 10 * MINUTE_IN_SECONDS; // formazioni ufficiali in arrivo
	} else {
		$ttl = 6 * HOUR_IN_SECONDS;
	}
	set_transient( $key, $data, $ttl );
	delete_transient( $key . '_lock' );
	return $data;
}

/* ---------- Testi in italiano ---------- */

function bnc_fdm_stat_label( $type ) {
	$map = array(
		'Shots on Goal'     => 'Tiri in porta',
		'Shots off Goal'    => 'Tiri fuori',
		'Total Shots'       => 'Tiri totali',
		'Blocked Shots'     => 'Tiri bloccati',
		'Shots insidebox'   => 'Tiri dentro l\'area',
		'Shots outsidebox'  => 'Tiri fuori area',
		'Fouls'             => 'Falli',
		'Corner Kicks'      => 'Calci d\'angolo',
		'Offsides'          => 'Fuorigioco',
		'Ball Possession'   => 'Possesso palla',
		'Yellow Cards'      => 'Cartellini gialli',
		'Red Cards'         => 'Cartellini rossi',
		'Goalkeeper Saves'  => 'Parate del portiere',
		'Total passes'      => 'Passaggi totali',
		'Passes accurate'   => 'Passaggi precisi',
		'Passes %'          => 'Passaggi %',
		'expected_goals'    => 'Gol attesi (xG)',
	);
	return isset( $map[ $type ] ) ? $map[ $type ] : '';
}

function bnc_fdm_reason( $type, $reason ) {
	$r = strtolower( (string) $reason );
	if ( false !== strpos( $r, 'suspend' ) || false !== strpos( $r, 'card' ) ) {
		$out = 'Squalifica';
	} elseif ( false !== strpos( $r, 'injur' ) || false !== strpos( $r, 'knock' ) || false !== strpos( $r, 'muscle' ) || false !== strpos( $r, 'illness' ) || false !== strpos( $r, 'surgery' ) ) {
		$out = 'Infortunio';
	} elseif ( '' !== $r ) {
		$out = 'Indisponibile';
	} else {
		$out = 'Assente';
	}
	return 'Questionable' === $type ? $out . ' (in dubbio)' : $out;
}

/* ---------- Widget ---------- */

/** HTML del widget per una partita delle quote, '' se non c'e' il collegamento o la chiave. */
function bnc_fdm_widget( $ev ) {
	if ( '' === bnc_fd_key() ) {
		return '';
	}
	$f = bnc_fdm_fixture_for( $ev );
	return $f ? bnc_fdm_render( $f, (int) $ev['id'] ) : '';
}

/** Partita del calendario salvato per id di API-Football (prove e anteprime). */
function bnc_fdm_fixture_by_id( $id ) {
	foreach ( (array) get_option( 'bnc_fdm_fixtures', array() ) as $f ) {
		if ( (int) $f['id'] === (int) $id ) {
			return $f;
		}
	}
	return null;
}

/** Widget di una partita di API-Football. $ev_id = partita delle quote (0 = prova, senza diretta). */
function bnc_fdm_render( $f, $ev_id = 0 ) {
	if ( '' === bnc_fd_key() ) {
		return '';
	}
	$d = bnc_fdm_data( $f );
	if ( ! $d || empty( $d['fixture'] ) ) {
		return '';
	}
	$fx    = $d['fixture'];
	$st    = (string) $fx['fixture']['status']['short'];
	$live  = in_array( $st, bnc_fdm_live_codes(), true );
	$done  = in_array( $st, bnc_fdm_done_codes(), true );
	$after = $live || $done;
	$home  = bnc_odds_team( $fx['teams']['home']['name'] );
	$away  = bnc_odds_team( $fx['teams']['away']['name'] );
	$hid   = (int) $fx['teams']['home']['id'];

	$tabs = array();
	if ( $after ) {
		$tabs['eventi'] = array( 'Eventi', bnc_fdm_tab_events( $fx, $hid ) );
		$tabs['stat']   = array( 'Statistiche', bnc_fdm_tab_stats( $fx, $hid ) );
		$tabs['form']   = array( 'Formazioni', bnc_fdm_tab_lineups( $fx, $hid ) );
		$tabs['gioc']   = array( 'Giocatori', bnc_fdm_tab_players( $fx ) );
	} else {
		$tabs['prec']  = array( 'Precedenti', bnc_fdm_tab_h2h( $d['h2h'], $fx ) );
		$tabs['forma'] = array( 'Forma', bnc_fdm_tab_form( $fx ) );
		$tabs['form']  = array( 'Formazioni', bnc_fdm_tab_lineups( $fx, $hid ) );
		$tabs['inf']   = array( 'Indisponibili', bnc_fdm_tab_injuries( $d['injuries'], $hid, $home, $away ) );
	}
	$tabs = array_filter( $tabs, function ( $t ) {
		return '' !== $t[1];
	} );
	if ( ! $tabs ) {
		return '';
	}

	$title = $after ? 'La partita: eventi, statistiche e formazioni' : 'Prima della partita: precedenti, forma e formazioni';
	$meta  = array();
	if ( ! empty( $fx['fixture']['referee'] ) ) {
		$meta[] = 'Arbitro: ' . preg_replace( '/,.*$/', '', (string) $fx['fixture']['referee'] );
	}
	if ( ! empty( $fx['fixture']['venue']['name'] ) ) {
		$meta[] = (string) $fx['fixture']['venue']['name'];
	}
	$h = '<section class="bnw" data-bnw><div class="bnw-head"><strong>' . esc_html( $title ) . '</strong>' . ( $meta ? '<span>' . esc_html( implode( ' · ', $meta ) ) . '</span>' : '' ) . '</div>';
	if ( $after ) {
		$sc = (int) $fx['goals']['home'] . ' - ' . (int) $fx['goals']['away'];
		$lb = $live ? ( 'HT' === $st ? 'Intervallo' : 'In corso' . ( ! empty( $fx['fixture']['status']['elapsed'] ) ? ', ' . (int) $fx['fixture']['status']['elapsed'] . '\'' : '' ) ) : 'Finale';
		$h .= '<div class="bnw-score"><span>' . esc_html( $home ) . '</span><strong>' . esc_html( $sc ) . '</strong><span>' . esc_html( $away ) . '</span><em class="' . ( $live ? 'is-live' : '' ) . '">' . esc_html( $lb ) . '</em></div>';
	}
	$h .= '<div class="bnw-tabs" role="tablist">';
	$first = true;
	foreach ( $tabs as $k => $t ) {
		$h    .= '<button type="button" role="tab" aria-selected="' . ( $first ? 'true' : 'false' ) . '" data-bnw-tab="' . esc_attr( $k ) . '">' . esc_html( $t[0] ) . '</button>';
		$first = false;
	}
	$h    .= '</div>';
	$first = true;
	foreach ( $tabs as $k => $t ) {
		$h    .= '<div class="bnw-panel" data-bnw-panel="' . esc_attr( $k ) . '"' . ( $first ? '' : ' hidden' ) . '>' . $t[1] . '</div>';
		$first = false;
	}
	$foot = $after ? ( $live ? 'Dati API-Football, aggiornati in diretta durante la partita.' : 'Dati API-Football.' ) : 'Dati API-Football. Le formazioni ufficiali arrivano circa 30 minuti prima del calcio d\'inizio.';
	$h   .= '<p class="bnw-foot">' . esc_html( $foot ) . '</p></section>';
	// 0.9.13: il browser ricarica il widget via REST (le pagine in cache di Velox restano ferme):
	// una volta all'apertura, poi ogni minuto se la partita e' in corso o sta per iniziare.
	$poll = $live || ( ! $done && (int) $f['ts'] - time() < 20 * MINUTE_IN_SECONDS && (int) $f['ts'] - time() > -3 * HOUR_IN_SECONDS );
	wp_enqueue_script( 'betnero-partita' );
	if ( ! $ev_id ) {
		return '<div class="bnw-live">' . $h . '</div>';
	}
	return '<div class="bnw-live" data-bnw-ev="' . (int) $ev_id . '" data-bnw-url="' . esc_url( rest_url( 'betnero/v1/calcio/widget' ) ) . '" data-bnw-poll="' . ( $poll ? '1' : '0' ) . '">' . $h . '</div>';
}

add_action( 'rest_api_init', function () {
	// Pubblico, come i dati gia' nella pagina: solo l'HTML del widget, mai la chiave.
	register_rest_route( 'betnero/v1', '/calcio/widget', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array(
			'evento'  => array( 'type' => 'integer', 'default' => 0 ),
			'partita' => array( 'type' => 'integer', 'default' => 0 ), // 0.9.14: id di API-Football, solo partite del calendario salvato
		),
		'callback'            => function ( $req ) {
			if ( (int) $req['partita'] ) {
				$f    = bnc_fdm_fixture_by_id( (int) $req['partita'] );
				$html = $f ? bnc_fdm_render( $f, 0 ) : '';
			} else {
				$ev   = bnc_odds_event( (int) $req['evento'] );
				$html = $ev ? bnc_fdm_widget( $ev ) : '';
			}
			$poll = false !== strpos( $html, 'data-bnw-poll="1"' );
			$res  = rest_ensure_response( array( 'html' => $html, 'poll' => $poll ) );
			$res->header( 'Cache-Control', 'public, max-age=' . ( $poll ? 45 : 300 ) );
			return $res;
		},
	) );
} );

function bnc_fdm_tab_events( $fx, $hid ) {
	if ( empty( $fx['events'] ) ) {
		return '';
	}
	$h   = '';
	$sec = '';
	foreach ( (array) $fx['events'] as $e ) {
		$min = (int) $e['time']['elapsed'];
		$s   = $min <= 45 ? 'Primo tempo' : ( $min <= 90 ? 'Secondo tempo' : 'Supplementari' );
		if ( $s !== $sec ) {
			$h  .= '<div class="bnw-period">' . esc_html( $s ) . '</div>';
			$sec = $s;
		}
		$type = (string) $e['type'];
		$det  = (string) $e['detail'];
		if ( 'Goal' === $type ) {
			$ico   = '<span class="bnw-ico is-goal" aria-label="Gol">&#9917;</span>';
			$extra = 'Own Goal' === $det ? 'Autogol' : ( 'Penalty' === $det ? 'Rigore' : ( 'Missed Penalty' === $det ? 'Rigore sbagliato' : ( ! empty( $e['assist']['name'] ) ? 'Assist: ' . $e['assist']['name'] : '' ) ) );
		} elseif ( 'Card' === $type ) {
			$ico   = '<span class="bnw-ico ' . ( false !== strpos( $det, 'Red' ) ? 'is-red' : 'is-yellow' ) . '" aria-label="' . ( false !== strpos( $det, 'Red' ) ? 'Cartellino rosso' : 'Cartellino giallo' ) . '"></span>';
			$extra = '';
		} elseif ( 'subst' === $type ) {
			$ico   = '<span class="bnw-ico is-sub" aria-label="Cambio">&#8644;</span>';
			$extra = ! empty( $e['assist']['name'] ) ? 'Cambio con ' . $e['assist']['name'] : 'Cambio';
		} else {
			$ico   = '<span class="bnw-ico is-var" aria-label="VAR">VAR</span>';
			$extra = $det;
		}
		$who  = (string) $e['player']['name']; // nei cambi i due nomi stanno in "player" e "assist"; li mostriamo entrambi
		$side = (int) $e['team']['id'] === $hid ? 'is-home' : 'is-away';
		$h   .= '<div class="bnw-ev ' . $side . '"><span class="bnw-min">' . $min . ( ! empty( $e['time']['extra'] ) ? '+' . (int) $e['time']['extra'] : '' ) . '\'</span>' . $ico . '<span class="bnw-who"><strong>' . esc_html( $who ) . '</strong>' . ( '' !== $extra ? '<em>' . esc_html( $extra ) . '</em>' : '' ) . '</span></div>';
	}
	return $h;
}

function bnc_fdm_tab_stats( $fx, $hid ) {
	if ( empty( $fx['statistics'] ) || count( $fx['statistics'] ) < 2 ) {
		return '';
	}
	$byteam = array();
	foreach ( $fx['statistics'] as $t ) {
		$side = (int) $t['team']['id'] === $hid ? 'h' : 'a';
		foreach ( (array) $t['statistics'] as $s ) {
			$byteam[ $s['type'] ][ $side ] = $s['value'];
		}
	}
	$h = '';
	foreach ( $byteam as $type => $v ) {
		$label = bnc_fdm_stat_label( $type );
		if ( '' === $label ) {
			continue;
		}
		$hv  = isset( $v['h'] ) && null !== $v['h'] ? $v['h'] : 0;
		$av  = isset( $v['a'] ) && null !== $v['a'] ? $v['a'] : 0;
		$hn  = (float) $hv;
		$an  = (float) $av;
		$tot = $hn + $an;
		$hp  = $tot > 0 ? round( $hn / $tot * 100 ) : 0;
		$ap  = $tot > 0 ? round( $an / $tot * 100 ) : 0;
		$h  .= '<div class="bnw-stat"><div class="bnw-stat-t"><strong>' . esc_html( (string) $hv ) . '</strong><span>' . esc_html( $label ) . '</span><strong>' . esc_html( (string) $av ) . '</strong></div><div class="bnw-bars"><div><span class="is-home" style="width:' . (int) $hp . '%"></span></div><div><span class="is-away" style="width:' . (int) $ap . '%"></span></div></div></div>';
	}
	return $h;
}

function bnc_fdm_tab_lineups( $fx, $hid ) {
	if ( empty( $fx['lineups'] ) || count( $fx['lineups'] ) < 2 ) {
		return '<p class="bnw-empty">Le formazioni ufficiali arrivano circa 30 minuti prima del calcio d\'inizio.</p>';
	}
	$dots  = '';
	$cards = '';
	foreach ( $fx['lineups'] as $lu ) {
		$home = (int) $lu['team']['id'] === $hid;
		$rows = array();
		foreach ( (array) $lu['startXI'] as $p ) {
			$g = isset( $p['player']['grid'] ) ? explode( ':', (string) $p['player']['grid'] ) : array();
			if ( 2 === count( $g ) ) {
				$rows[ (int) $g[0] ][] = array( (int) $g[1], $p['player'] );
			}
		}
		$nrows = $rows ? max( array_keys( $rows ) ) : 0;
		foreach ( $rows as $r => $list ) {
			usort( $list, function ( $x, $y ) {
				return $x[0] <=> $y[0];
			} );
			$n = count( $list );
			foreach ( $list as $i => $item ) {
				$x     = $nrows > 1 ? 4 + ( $r - 1 ) * ( 42 / ( $nrows - 1 ) ) : 4;
				$x     = $home ? $x : 100 - $x;
				$y     = ( $i + 1 ) / ( $n + 1 ) * 100;
				$y     = $home ? $y : 100 - $y;
				$dots .= '<span class="bnw-dot ' . ( $home ? 'is-home' : 'is-away' ) . '" style="left:' . round( $x, 1 ) . '%;top:' . round( $y, 1 ) . '%" title="' . esc_attr( $item[1]['name'] ) . '">' . (int) $item[1]['number'] . '</span>';
			}
		}
		$names = array();
		foreach ( (array) $lu['startXI'] as $p ) {
			$names[] = (int) $p['player']['number'] . ' ' . $p['player']['name'];
		}
		$cards .= '<div class="bnw-lu"><span>' . esc_html( bnc_odds_team( $lu['team']['name'] ) . ' · ' . (string) $lu['formation'] . ( ! empty( $lu['coach']['name'] ) ? ' · ' . $lu['coach']['name'] : '' ) ) . '</span><p>' . esc_html( implode( ', ', $names ) ) . '</p></div>';
	}
	return '<div class="bnw-pitch" role="img" aria-label="Formazioni in campo">' . $dots . '</div><div class="bnw-lus">' . $cards . '</div>';
}

function bnc_fdm_tab_players( $fx ) {
	if ( empty( $fx['players'] ) ) {
		return '';
	}
	$h = '';
	foreach ( $fx['players'] as $t ) {
		$list = array();
		foreach ( (array) $t['players'] as $p ) {
			$s = isset( $p['statistics'][0] ) ? $p['statistics'][0] : array();
			if ( empty( $s['games']['minutes'] ) ) {
				continue;
			}
			$list[] = array(
				'n'   => (int) $s['games']['number'],
				'nm'  => (string) $p['player']['name'],
				'min' => (int) $s['games']['minutes'],
				'g'   => (int) ( isset( $s['goals']['total'] ) ? $s['goals']['total'] : 0 ),
				'v'   => isset( $s['games']['rating'] ) && null !== $s['games']['rating'] ? (float) $s['games']['rating'] : 0,
			);
		}
		usort( $list, function ( $x, $y ) {
			return $y['v'] <=> $x['v'];
		} );
		$h .= '<div class="bnw-team">' . esc_html( bnc_odds_team( $t['team']['name'] ) ) . '</div><div class="bnw-pl bnw-pl-h"><span>#</span><span>Giocatore</span><span>Min</span><span>Gol</span><span>Voto</span></div>';
		foreach ( $list as $p ) {
			$h .= '<div class="bnw-pl"><span>' . (int) $p['n'] . '</span><span>' . esc_html( $p['nm'] ) . '</span><span>' . (int) $p['min'] . '</span><span>' . (int) $p['g'] . '</span><span class="bnw-rate' . ( $p['v'] >= 7 ? ' is-good' : '' ) . '">' . ( $p['v'] ? esc_html( number_format_i18n( $p['v'], 1 ) ) : '-' ) . '</span></div>';
		}
	}
	return $h;
}

function bnc_fdm_tab_h2h( $h2h, $fx ) {
	if ( ! $h2h ) {
		return '';
	}
	$hid = (int) $fx['teams']['home']['id'];
	$w   = array( 'h' => 0, 'd' => 0, 'a' => 0 );
	$rows = '';
	foreach ( $h2h as $m ) {
		$gh = $m['goals']['home'];
		$ga = $m['goals']['away'];
		if ( null === $gh || null === $ga ) {
			continue;
		}
		if ( $gh === $ga ) {
			$w['d']++;
		} else {
			$winner = $gh > $ga ? (int) $m['teams']['home']['id'] : (int) $m['teams']['away']['id'];
			$w[ $winner === $hid ? 'h' : 'a' ]++;
		}
		$rows .= '<div class="bnw-h2h"><span>' . esc_html( wp_date( 'd/m/Y', (int) $m['fixture']['timestamp'] ) ) . '</span><span>' . esc_html( bnc_odds_team( $m['teams']['home']['name'] ) . ' – ' . bnc_odds_team( $m['teams']['away']['name'] ) ) . '</span><strong>' . (int) $gh . ' - ' . (int) $ga . '</strong></div>';
	}
	if ( '' === $rows ) {
		return '';
	}
	$home = bnc_odds_team( $fx['teams']['home']['name'] );
	$away = bnc_odds_team( $fx['teams']['away']['name'] );
	return '<div class="bnw-wins"><div><strong class="is-home">' . $w['h'] . '</strong><span>Vittorie ' . esc_html( $home ) . '</span></div><div><strong>' . $w['d'] . '</strong><span>Pareggi</span></div><div><strong class="is-away">' . $w['a'] . '</strong><span>Vittorie ' . esc_html( $away ) . '</span></div></div>' . $rows . '<p class="bnw-note">Ultimi scontri diretti.</p>';
}

function bnc_fdm_tab_form( $fx ) {
	$lid = isset( $fx['league']['id'] ) ? (int) $fx['league']['id'] : 0;
	$all = get_option( 'bnc_fd_standings', array() );
	if ( ! $lid || empty( $all[ $lid ]['righe'] ) ) {
		return '';
	}
	$chip = array( 'W' => array( 'V', 'is-w' ), 'D' => array( 'N', 'is-d' ), 'L' => array( 'P', 'is-l' ) );
	$h    = '';
	foreach ( array( 'home', 'away' ) as $side ) {
		foreach ( $all[ $lid ]['righe'] as $r ) {
			if ( (int) $r['id'] !== (int) $fx['teams'][ $side ]['id'] ) {
				continue;
			}
			$h .= '<div class="bnw-form"><span><strong>' . esc_html( bnc_odds_team( $r['nome'] ) ) . '</strong><em>' . (int) $r['pos'] . 'º posto · ' . (int) $r['pt'] . ' punti</em></span><span class="bnw-chips">';
			foreach ( str_split( substr( (string) $r['forma'], -5 ) ) as $c ) {
				if ( isset( $chip[ $c ] ) ) {
					$h .= '<i class="' . $chip[ $c ][1] . '">' . $chip[ $c ][0] . '</i>';
				}
			}
			$h .= '</span></div>';
		}
	}
	return '' !== $h ? $h . '<p class="bnw-note">Classifica e ultime 5 partite di campionato.</p>' : '';
}

function bnc_fdm_tab_injuries( $inj, $hid, $home, $away ) {
	$by = array( 'h' => array(), 'a' => array() );
	foreach ( (array) $inj as $i ) {
		$side          = (int) $i['team']['id'] === $hid ? 'h' : 'a';
		$by[ $side ][] = array( (string) $i['player']['name'], bnc_fdm_reason( isset( $i['player']['type'] ) ? $i['player']['type'] : '', isset( $i['player']['reason'] ) ? $i['player']['reason'] : '' ) );
	}
	$h = '<div class="bnw-inj">';
	foreach ( array( 'h' => $home, 'a' => $away ) as $k => $team ) {
		$h .= '<div><strong>' . esc_html( $team ) . '</strong>';
		if ( ! $by[ $k ] ) {
			$h .= '<span class="bnw-none">Nessun indisponibile segnalato</span>';
		}
		foreach ( $by[ $k ] as $p ) {
			$h .= '<span><span>' . esc_html( $p[0] ) . '</span><em>' . esc_html( $p[1] ) . '</em></span>';
		}
		$h .= '</div>';
	}
	return $h . '</div>';
}

/* ---------- 0.9.14: prova del widget in Operatori > Dati calcio ---------- */

add_action( 'bnc_fd_page_after', function () {
	$list = array_filter( (array) get_option( 'bnc_fdm_fixtures', array() ), function ( $f ) {
		return isset( $f['st'] ) && in_array( $f['st'], bnc_fdm_done_codes(), true );
	} );
	usort( $list, function ( $a, $b ) {
		return $b['ts'] <=> $a['ts'];
	} );
	echo '<h2>4. Prova del widget</h2>';
	if ( ! $list ) {
		echo '<p>Nessuna partita finita nel calendario salvato: premi "Aggiorna adesso".</p>';
		return;
	}
	$sel = isset( $_GET['bnc_prova'] ) ? absint( $_GET['bnc_prova'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification
	echo '<form method="get"><input type="hidden" name="post_type" value="' . esc_attr( BNC_POST_TYPE ) . '"><input type="hidden" name="page" value="bnc-calcio"><select name="bnc_prova">';
	foreach ( $list as $f ) {
		echo '<option value="' . (int) $f['id'] . '"' . selected( $sel, (int) $f['id'], false ) . '>' . esc_html( wp_date( 'd/m H:i', (int) $f['ts'] ) . ' ' . bnc_odds_team( $f['home']['name'] ) . ' ' . (int) $f['gol'][0] . '-' . (int) $f['gol'][1] . ' ' . bnc_odds_team( $f['away']['name'] ) ) . ' (id ' . (int) $f['id'] . ')</option>';
	}
	echo '</select> <button class="button">Mostra il widget</button></form>';
	if ( $sel ) {
		$f = bnc_fdm_fixture_by_id( $sel );
		wp_enqueue_style( 'betnero-quote' );
		echo '<div style="max-width:900px;margin-top:14px">' . ( $f ? bnc_fdm_render( $f, 0 ) : '' ) . '</div>';
	}
} );

/* ---------- 0.9.15: widget anche negli articoli dei pronostici ---------- */

/**
 * Articolo con partita collegata (riquadro "Quote del pronostico"): il widget va subito dopo
 * le quote, cioe' prima del primo titolo H2 (dove la Core mette le quote automatiche).
 * Senza H2, in fondo all'articolo. Gira dopo l'inserimento automatico delle quote.
 */
add_filter( 'the_content', function ( $content ) {
	if ( is_admin() || is_feed() || wp_is_json_request() || doing_action( 'wp_head' ) ) {
		return $content;
	}
	if ( ! is_singular( 'post' ) || get_the_ID() !== get_queried_object_id() || false !== strpos( $content, 'class="bnw-live"' ) ) {
		return $content;
	}
	$ev_id = (int) get_post_meta( get_the_ID(), '_bn_quote_evento', true );
	$ev    = $ev_id ? bnc_odds_event( $ev_id ) : null;
	$w     = $ev ? bnc_fdm_widget( $ev ) : '';
	if ( '' === $w ) {
		return $content;
	}
	wp_enqueue_style( 'betnero-quote' );
	$w   = '<div class="bnw-post">' . $w . '</div>';
	$pos = stripos( $content, '<h2' );
	return false !== $pos ? substr( $content, 0, $pos ) . $w . substr( $content, $pos ) : $content . $w;
}, PHP_INT_MAX );
