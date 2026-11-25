<?php
/**
 * Dati di calcio da API-Football (0.9.4): classifica dei campionati.
 *
 * - Operatori > Dati calcio: chiave di API-Football e campionati (id e stagione).
 * - Lettura programmata due volte al giorno, salvata in un'opzione: le pagine leggono
 *   solo la copia salvata, quindi le visite non consumano richieste (piano Free: 100 al giorno).
 * - Blocco betnero/classifica-campionato: la classifica con stemmi, forma e zone.
 *
 * Il piano Free legge solo le stagioni dal 2022 al 2024: sullo staging si usa il 2024.
 * La chiave non viene mai mostrata ne' inviata al browser.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BNC_FD_API', 'https://v3.football.api-sports.io' );

/* ---------- Impostazioni ---------- */

function bnc_fd_key() {
	return (string) get_option( 'bnc_fd_key', '' );
}

/** Campionati seguiti: [ [ 'id' => 135, 'nome' => 'Serie A', 'stagione' => 2024 ], ... ]. */
function bnc_fd_leagues() {
	$l = get_option( 'bnc_fd_leagues', null );
	if ( ! is_array( $l ) ) {
		$l = array( array( 'id' => 135, 'nome' => 'Serie A', 'stagione' => 2024 ) );
	}
	return array_values( array_filter( $l, function ( $x ) {
		return ! empty( $x['id'] ) && ! empty( $x['stagione'] );
	} ) );
}

function bnc_fd_league( $id ) {
	foreach ( bnc_fd_leagues() as $l ) {
		if ( (int) $l['id'] === (int) $id ) {
			return $l;
		}
	}
	return null;
}

/* ---------- Chiamate ad API-Football ---------- */

/** GET su API-Football. Restituisce 'response' o WP_Error. Conta le richieste del giorno. */
function bnc_fd_get( $path, $args = array() ) {
	$key = bnc_fd_key();
	if ( '' === $key ) {
		return new WP_Error( 'bnc_fd_nokey', 'Chiave di API-Football non impostata.' );
	}
	$url = BNC_FD_API . $path . ( $args ? '?' . http_build_query( $args, '', '&', PHP_QUERY_RFC3986 ) : '' );
	$res = wp_remote_get( $url, array( 'timeout' => 25, 'headers' => array( 'x-apisports-key' => $key, 'Accept' => 'application/json' ) ) );

	$day = gmdate( 'Ymd' );
	$cnt = get_option( 'bnc_fd_count', array() );
	$cnt = ( is_array( $cnt ) && isset( $cnt['d'] ) && $cnt['d'] === $day ) ? $cnt : array( 'd' => $day, 'n' => 0 );
	$cnt['n']++;
	update_option( 'bnc_fd_count', $cnt, false );

	if ( is_wp_error( $res ) ) {
		return $res;
	}
	$code = (int) wp_remote_retrieve_response_code( $res );
	$body = json_decode( wp_remote_retrieve_body( $res ), true );
	if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
		return new WP_Error( 'bnc_fd_http', 'HTTP ' . $code . ' (' . $path . ')' );
	}
	// API-Football risponde 200 anche con gli errori: sono nel campo "errors".
	if ( ! empty( $body['errors'] ) ) {
		$e = is_array( $body['errors'] ) ? implode( ' ', array_map( 'strval', $body['errors'] ) ) : (string) $body['errors'];
		return new WP_Error( 'bnc_fd_api', $e . ' (' . $path . ')' );
	}
	return isset( $body['response'] ) ? $body['response'] : array();
}

/* ---------- Stemmi (0.9.10) ---------- */

/**
 * Stemma salvato una volta in wp-content/uploads/betnero-stemmi/<id>.png:
 * le pagine non dipendono dal server delle immagini di API-Football.
 * Se il download non riesce resta il link originale.
 */
function bnc_fd_crest_local( $team_id, $remote ) {
	if ( ! $team_id || '' === $remote ) {
		return $remote;
	}
	$up  = wp_upload_dir();
	$dir = trailingslashit( $up['basedir'] ) . 'betnero-stemmi';
	$url = trailingslashit( $up['baseurl'] ) . 'betnero-stemmi/' . (int) $team_id . '.png';
	$file = $dir . '/' . (int) $team_id . '.png';
	if ( file_exists( $file ) && filesize( $file ) > 0 ) {
		return $url;
	}
	wp_mkdir_p( $dir );
	$res = wp_remote_get( $remote, array( 'timeout' => 15 ) );
	if ( is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ) {
		return $remote;
	}
	$body = wp_remote_retrieve_body( $res );
	if ( '' === $body || false === strpos( (string) wp_remote_retrieve_header( $res, 'content-type' ), 'image' ) ) {
		return $remote;
	}
	return false !== file_put_contents( $file, $body ) ? $url : $remote; // phpcs:ignore WordPress.WP.AlternativeFunctions
}

/** Stemma di una squadra dal nome usato nelle quote (es. "Venezia"), '' se non c'e'. */
function bnc_fd_crest( $team ) {
	static $map = null;
	if ( null === $map ) {
		$map = array();
		foreach ( (array) get_option( 'bnc_fd_standings', array() ) as $data ) {
			foreach ( isset( $data['righe'] ) ? (array) $data['righe'] : array() as $r ) {
				$n = function_exists( 'bnc_odds_team' ) ? bnc_odds_team( $r['nome'] ) : $r['nome'];
				$map[ strtolower( remove_accents( $n ) ) ] = $r['logo'];
				$map[ strtolower( remove_accents( $r['nome'] ) ) ] = $r['logo'];
			}
		}
	}
	$k = strtolower( remove_accents( (string) $team ) );
	return isset( $map[ $k ] ) ? $map[ $k ] : '';
}

/* ---------- Lettura programmata ---------- */

function bnc_fd_schedule() {
	$on = '' !== bnc_fd_key() && bnc_fd_leagues();
	$ts = wp_next_scheduled( 'bnc_fd_sync' );
	if ( $on && ! $ts ) {
		wp_schedule_event( time() + 120, 'twicedaily', 'bnc_fd_sync' );
	} elseif ( ! $on && $ts ) {
		wp_unschedule_event( $ts, 'bnc_fd_sync' );
	}
}
add_action( 'init', 'bnc_fd_schedule' );
add_action( 'bnc_fd_sync', 'bnc_fd_sync' );

/** Legge la classifica di ogni campionato seguito. Una richiesta per campionato. */
function bnc_fd_sync() {
	$all = get_option( 'bnc_fd_standings', array() );
	$all = is_array( $all ) ? $all : array();
	$rep = array( 'ok' => 0, 'errori' => array() );
	// 0.9.10: piano e limite giornaliero, per la pagina di stato (una richiesta).
	$st = bnc_fd_get( '/status' );
	if ( ! is_wp_error( $st ) && isset( $st['subscription'] ) ) {
		update_option( 'bnc_fd_plan', array( 'plan' => (string) $st['subscription']['plan'], 'limit' => isset( $st['requests']['limit_day'] ) ? (int) $st['requests']['limit_day'] : 0 ), false );
	}
	foreach ( bnc_fd_leagues() as $l ) {
		$r = bnc_fd_get( '/standings', array( 'league' => (int) $l['id'], 'season' => (int) $l['stagione'] ) );
		if ( is_wp_error( $r ) ) {
			$rep['errori'][] = $l['nome'] . ': ' . $r->get_error_message();
			continue;
		}
		$groups = isset( $r[0]['league']['standings'] ) ? (array) $r[0]['league']['standings'] : array();
		$rows   = array();
		foreach ( isset( $groups[0] ) ? (array) $groups[0] : array() as $t ) {
			$rows[] = array(
				'pos'   => (int) $t['rank'],
				'id'    => (int) $t['team']['id'],
				'nome'  => (string) $t['team']['name'],
				'logo'  => bnc_fd_crest_local( (int) $t['team']['id'], (string) $t['team']['logo'] ),
				'g'     => (int) $t['all']['played'],
				'v'     => (int) $t['all']['win'],
				'n'     => (int) $t['all']['draw'],
				'p'     => (int) $t['all']['lose'],
				'gf'    => (int) $t['all']['goals']['for'],
				'gs'    => (int) $t['all']['goals']['against'],
				'pt'    => (int) $t['points'],
				'forma' => (string) $t['form'],
				'zona'  => (string) $t['description'],
			);
		}
		if ( $rows ) {
			$all[ (int) $l['id'] ] = array( 'stagione' => (int) $l['stagione'], 'agg' => gmdate( 'Y-m-d H:i:s' ), 'righe' => $rows );
			$rep['ok']++;
		} else {
			$rep['errori'][] = $l['nome'] . ': classifica vuota';
		}
	}
	update_option( 'bnc_fd_standings', $all, false );
	update_option( 'bnc_fd_state', array( 'quando' => gmdate( 'Y-m-d H:i:s' ), 'rep' => $rep ), false );
	return $rep;
}

/* ---------- Blocco Classifica campionato ---------- */

add_action( 'init', function () {
	register_block_type( 'betnero/classifica-campionato', array(
		'title'           => 'Classifica campionato',
		'editor_script'   => 'betnero-blocks-editor',
		'style'           => 'betnero-quote',
		'category'        => 'betnero',
		'supports'        => array( 'html' => false, 'align' => array( 'wide' ) ),
		'attributes'      => array(
			'campionato' => array( 'type' => 'number', 'default' => 135 ),
			'titolo'     => array( 'type' => 'string', 'default' => '' ),
			'align'      => array( 'type' => 'string' ),
			'className'  => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_fd_standings_block',
	) );
}, 21 );

/** Zona della classifica dalla descrizione di API-Football. */
function bnc_fd_zone( $desc ) {
	$d = strtolower( (string) $desc );
	if ( '' === $d ) {
		return '';
	}
	if ( false !== strpos( $d, 'relegation' ) ) {
		return 'rel';
	}
	if ( false !== strpos( $d, 'champions league' ) ) {
		return 'ucl';
	}
	if ( false !== strpos( $d, 'europa' ) || false !== strpos( $d, 'conference' ) ) {
		return 'uel';
	}
	return '';
}

function bnc_fd_standings_render( $league_id, $titolo = '' ) {
	$all  = get_option( 'bnc_fd_standings', array() );
	$data = is_array( $all ) && isset( $all[ (int) $league_id ] ) ? $all[ (int) $league_id ] : null;
	if ( ! $data || empty( $data['righe'] ) ) {
		return '';
	}
	$l      = bnc_fd_league( $league_id );
	$nome   = $l ? $l['nome'] : 'Campionato';
	$season = (int) $data['stagione'];
	$titolo = '' !== $titolo ? $titolo : 'Classifica ' . $nome;
	$form   = array( 'W' => array( 'V', 'is-w', 'Vittoria' ), 'D' => array( 'N', 'is-d', 'Pareggio' ), 'L' => array( 'P', 'is-l', 'Sconfitta' ) );

	$h  = '<div class="bnc-cl"><div class="bnc-cl-head"><p class="bnc-cl-title">' . esc_html( $titolo ) . '</p>';
	$h .= '<span class="bnc-cl-season">Stagione ' . $season . '/' . substr( (string) ( $season + 1 ), -2 ) . '</span></div>';
	$h .= '<div class="bnc-cl-scroll"><table class="bnc-cl-table"><thead><tr>';
	$h .= '<th class="is-pos" scope="col">#</th><th class="is-team" scope="col">Squadra</th>';
	foreach ( array( 'G' => 'Giocate', 'V' => 'Vinte', 'N' => 'Pareggiate', 'P' => 'Perse', 'GF' => 'Gol fatti', 'GS' => 'Gol subiti', 'Pt' => 'Punti' ) as $k => $t ) {
		$h .= '<th scope="col"><abbr title="' . esc_attr( $t ) . '">' . esc_html( $k ) . '</abbr></th>';
	}
	$h .= '<th class="is-form" scope="col">Ultime 5</th></tr></thead><tbody>';
	foreach ( $data['righe'] as $r ) {
		$z  = bnc_fd_zone( $r['zona'] );
		$h .= '<tr' . ( $z ? ' class="is-' . esc_attr( $z ) . '"' : '' ) . '><td class="is-pos">' . (int) $r['pos'] . '</td>';
		$h .= '<th class="is-team" scope="row"><span>' . ( $r['logo'] ? '<img src="' . esc_url( $r['logo'] ) . '" alt="" width="20" height="20" loading="lazy" decoding="async">' : '' ) . esc_html( function_exists( 'bnc_odds_team' ) ? bnc_odds_team( $r['nome'] ) : $r['nome'] ) . '</span></th>';
		foreach ( array( 'g', 'v', 'n', 'p', 'gf', 'gs' ) as $k ) {
			$h .= '<td>' . (int) $r[ $k ] . '</td>';
		}
		$h .= '<td class="is-pt">' . (int) $r['pt'] . '</td><td class="is-form"><span>';
		foreach ( str_split( substr( (string) $r['forma'], -5 ) ) as $c ) {
			if ( isset( $form[ $c ] ) ) {
				$h .= '<i class="' . esc_attr( $form[ $c ][1] ) . '" title="' . esc_attr( $form[ $c ][2] ) . '">' . esc_html( $form[ $c ][0] ) . '</i>';
			}
		}
		$h .= '</span></td></tr>';
	}
	$h .= '</tbody></table></div>';
	$h .= '<p class="bnc-cl-legend"><span class="is-ucl">Champions League</span><span class="is-uel">Europa e Conference League</span><span class="is-rel">Retrocessione</span></p>';
	$h .= '<p class="bnq-note">Dati API-Football, aggiornati il ' . esc_html( wp_date( 'd/m H:i', strtotime( $data['agg'] . ' UTC' ) ) ) . '.</p></div>';
	return $h;
}

function bnc_fd_standings_block( $a ) {
	$id    = isset( $a['campionato'] ) ? (int) $a['campionato'] : 135;
	$inner = bnc_fd_standings_render( $id, isset( $a['titolo'] ) ? sanitize_text_field( $a['titolo'] ) : '' );
	if ( '' === $inner && defined( 'REST_REQUEST' ) && REST_REQUEST ) {
		return '<p class="bnq-empty">Classifica non ancora letta: imposta la chiave e premi "Aggiorna adesso" in Operatori &gt; Dati calcio.</p>';
	}
	return $inner;
}

add_action( 'rest_api_init', function () {
	// Pubblico, come i dati gia' nella pagina: utile per controllare la classifica salvata.
	register_rest_route( 'betnero/v1', '/calcio/classifica', array(
		'methods'             => 'GET',
		'permission_callback' => '__return_true',
		'args'                => array( 'campionato' => array( 'type' => 'integer', 'default' => 135 ) ),
		'callback'            => function ( $req ) {
			$all  = get_option( 'bnc_fd_standings', array() );
			$id   = (int) $req['campionato'];
			$data = is_array( $all ) && isset( $all[ $id ] ) ? $all[ $id ] : null;
			$res  = rest_ensure_response( array(
				'campionato' => $id,
				'stagione'   => $data ? (int) $data['stagione'] : null,
				'aggiornata' => $data ? $data['agg'] : null,
				'squadre'    => $data ? count( $data['righe'] ) : 0,
				'html'       => bnc_fd_standings_render( $id ),
			) );
			$res->header( 'Cache-Control', 'public, max-age=300' );
			return $res;
		},
	) );
} );

function bnc_fd_plan_label() {
	$pl = (array) get_option( 'bnc_fd_plan', array() );
	return ! empty( $pl['limit'] ) ? number_format_i18n( (int) $pl['limit'] ) . ' (piano ' . ( '' !== $pl['plan'] ? $pl['plan'] : '?' ) . ')' : '100 (piano Free)';
}

/* ---------- Operatori > Dati calcio ---------- */

add_action( 'admin_menu', function () {
	add_submenu_page( 'edit.php?post_type=' . BNC_POST_TYPE, 'Dati calcio (API-Football)', 'Dati calcio', 'manage_options', 'bnc-calcio', 'bnc_fd_page' );
}, 22 );

function bnc_fd_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$notice = array();
	if ( isset( $_POST['bnc_fd_action'] ) && check_admin_referer( 'bnc_fd' ) ) {
		$act = sanitize_key( wp_unslash( $_POST['bnc_fd_action'] ) );
		if ( 'key' === $act ) {
			$k = isset( $_POST['bnc_fd_key'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['bnc_fd_key'] ) ) ) : '';
			if ( '' !== $k ) {
				update_option( 'bnc_fd_key', $k, false );
				$st       = bnc_fd_get( '/status' );
				$notice[] = is_wp_error( $st )
					? array( 'error', 'Chiave salvata, ma API-Football risponde: ' . $st->get_error_message() )
					: array( 'success', 'Chiave salvata e funzionante. Piano: ' . ( isset( $st['subscription']['plan'] ) ? $st['subscription']['plan'] : '?' ) . '.' );
				if ( ! is_wp_error( $st ) ) {
					update_option( 'bnc_fd_plan', array( 'plan' => isset( $st['subscription']['plan'] ) ? (string) $st['subscription']['plan'] : '', 'limit' => isset( $st['requests']['limit_day'] ) ? (int) $st['requests']['limit_day'] : 0 ), false );
				}
			}
		}
		if ( 'key_delete' === $act ) {
			delete_option( 'bnc_fd_key' );
			$notice[] = array( 'success', 'Chiave cancellata: la lettura dei dati e\' ferma.' );
		}
		if ( 'leagues' === $act ) {
			$in  = isset( $_POST['bnc_fd'] ) ? (array) wp_unslash( $_POST['bnc_fd'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$out = array();
			foreach ( $in as $row ) {
				$id = isset( $row['id'] ) ? absint( $row['id'] ) : 0;
				$se = isset( $row['stagione'] ) ? absint( $row['stagione'] ) : 0;
				if ( $id && $se ) {
					$out[] = array( 'id' => $id, 'nome' => sanitize_text_field( isset( $row['nome'] ) ? $row['nome'] : '' ), 'stagione' => $se );
				}
			}
			update_option( 'bnc_fd_leagues', $out, false );
			$notice[] = array( 'success', count( $out ) . ' campionati salvati.' );
		}
		if ( 'sync' === $act ) {
			$rep      = bnc_fd_sync();
			$notice[] = array( empty( $rep['errori'] ) ? 'success' : 'warning', 'Classifiche lette: ' . $rep['ok'] . ( $rep['errori'] ? '. Errori: ' . implode( ' | ', $rep['errori'] ) : '.' ) );
		}
		bnc_fd_schedule();
	}

	$key   = bnc_fd_key();
	$cnt   = (array) get_option( 'bnc_fd_count', array() );
	$state = (array) get_option( 'bnc_fd_state', array() );
	$all   = (array) get_option( 'bnc_fd_standings', array() );
	$form  = function ( $action, $label, $class = 'button' ) {
		return '<form method="post" style="display:inline-block;margin:0 6px 6px 0">' . wp_nonce_field( 'bnc_fd', '_wpnonce', true, false ) . '<input type="hidden" name="bnc_fd_action" value="' . esc_attr( $action ) . '"><button class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></form>';
	};

	echo '<div class="wrap"><h1>Dati calcio (API-Football)</h1>';
	foreach ( $notice as $n ) {
		echo '<div class="notice notice-' . esc_attr( $n[0] ) . '"><p>' . esc_html( $n[1] ) . '</p></div>';
	}

	echo '<h2>1. Chiave</h2><p>La chiave si trova nella dashboard di API-Football, in Account &gt; My Access.</p>';
	if ( $key ) {
		echo '<p>Chiave salvata, finisce con <code>&hellip;' . esc_html( substr( $key, -4 ) ) . '</code>. Non viene mai mostrata per intero.</p>';
		echo $form( 'key_delete', 'Cancella la chiave' ); // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '<form method="post">';
	wp_nonce_field( 'bnc_fd' );
	echo '<input type="hidden" name="bnc_fd_action" value="key"><input type="text" name="bnc_fd_key" autocomplete="off" autocorrect="off" autocapitalize="off" spellcheck="false" data-lpignore="true" data-1p-ignore="true" style="-webkit-text-security:disc" class="regular-text" placeholder="' . ( $key ? 'Nuova chiave (per sostituirla)' : 'Incolla la chiave di API-Football' ) . '"> <button class="button button-primary">Salva la chiave</button></form>';

	if ( ! $key ) {
		echo '</div>';
		return;
	}

	echo '<h2>2. Campionati</h2><p>Id del campionato su API-Football (Serie A = 135) e stagione (l\'anno di inizio: 2024 = 2024/25). Il piano Free legge solo le stagioni dal 2022 al 2024.</p>';
	echo '<form method="post">';
	wp_nonce_field( 'bnc_fd' );
	echo '<input type="hidden" name="bnc_fd_action" value="leagues"><table class="widefat striped" style="max-width:560px"><thead><tr><th>Nome</th><th>Id</th><th>Stagione</th></tr></thead><tbody>';
	$rows   = bnc_fd_leagues();
	$rows[] = array( 'id' => '', 'nome' => '', 'stagione' => '' );
	foreach ( $rows as $i => $l ) {
		echo '<tr><td><input type="text" name="bnc_fd[' . (int) $i . '][nome]" value="' . esc_attr( $l['nome'] ) . '" placeholder="Nuovo campionato"></td>';
		echo '<td><input type="number" min="1" name="bnc_fd[' . (int) $i . '][id]" value="' . esc_attr( $l['id'] ) . '" style="width:90px"></td>';
		echo '<td><input type="number" min="2000" max="2100" name="bnc_fd[' . (int) $i . '][stagione]" value="' . esc_attr( $l['stagione'] ) . '" style="width:90px"></td></tr>';
	}
	echo '</tbody></table><p class="description">Per togliere un campionato svuota il suo id. Ogni campionato costa una richiesta per aggiornamento (due al giorno).</p>';
	echo '<p><button class="button button-primary">Salva i campionati</button></p></form>';

	echo '<h2>3. Stato</h2><table class="widefat" style="max-width:720px"><tbody>';
	$next  = wp_next_scheduled( 'bnc_fd_sync' );
	$lines = array(
		'Prossimo aggiornamento automatico' => $next ? wp_date( 'd/m H:i', $next ) : 'non programmato',
		'Ultimo aggiornamento'              => ! empty( $state['quando'] ) ? wp_date( 'd/m H:i:s', strtotime( $state['quando'] . ' UTC' ) ) . ( ! empty( $state['rep']['errori'] ) ? ', errori: ' . implode( ' | ', $state['rep']['errori'] ) : ', riuscito' ) : '-',
		'Richieste di oggi'                 => ( isset( $cnt['d'] ) && gmdate( 'Ymd' ) === $cnt['d'] ? (int) $cnt['n'] : 0 ) . ' su ' . bnc_fd_plan_label(),
	);
	foreach ( bnc_fd_leagues() as $l ) {
		$d = isset( $all[ (int) $l['id'] ] ) ? $all[ (int) $l['id'] ] : null;
		$lines[ 'Classifica ' . $l['nome'] ] = $d ? count( $d['righe'] ) . ' squadre, stagione ' . (int) $d['stagione'] . ', letta il ' . wp_date( 'd/m H:i', strtotime( $d['agg'] . ' UTC' ) ) : 'non ancora letta';
	}
	foreach ( $lines as $k => $v ) {
		echo '<tr><th style="width:260px">' . esc_html( $k ) . '</th><td>' . esc_html( $v ) . '</td></tr>';
	}
	echo '</tbody></table><p>';
	echo $form( 'sync', 'Aggiorna adesso', 'button button-primary' ); // phpcs:ignore WordPress.Security.EscapeOutput
	echo '</p><p class="description">Per mostrare la classifica in una pagina: blocco "Classifica campionato" (categoria Betnero).</p></div>';
}
