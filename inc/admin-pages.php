<?php
/**
 * Pagine di amministrazione: Operatori > Passaggio da Crocoblock e Operatori > Impostazioni.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'admin_menu', function () {
	$parent = 'edit.php?post_type=' . BNC_POST_TYPE;
	add_submenu_page( $parent, 'Passaggio da Crocoblock', 'Passaggio da Crocoblock', 'manage_options', 'bnc-migrazione', 'bnc_page_migration' );
	add_submenu_page( $parent, 'Impostazioni Betnero', 'Impostazioni', 'manage_options', 'bnc-impostazioni', 'bnc_page_settings' );
}, 20 );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( false !== strpos( $hook, 'bnc-' ) ) {
		wp_enqueue_style( 'bnc-admin', BETNERO_CORE_URL . 'assets/css/admin.css', array(), BETNERO_CORE_VERSION );
	}
} );

/* ---------- Passaggio da Crocoblock ---------- */

function bnc_page_migration() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	$tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'stato'; // phpcs:ignore WordPress.Security.NonceVerification
	$base   = admin_url( 'edit.php?post_type=' . BNC_POST_TYPE . '&page=bnc-migrazione' );
	$tabs   = array( 'stato' => 'Stato', 'migrazione' => 'Importa da JetEngine', 'recensioni' => 'Recensioni', 'inventario' => 'Inventario' );
	echo '<div class="wrap"><h1>Passaggio da Crocoblock</h1><nav class="nav-tab-wrapper">';
	foreach ( $tabs as $k => $l ) {
		echo '<a class="nav-tab' . ( $tab === $k ? ' nav-tab-active' : '' ) . '" href="' . esc_url( add_query_arg( 'tab', $k, $base ) ) . '">' . esc_html( $l ) . '</a>';
	}
	echo '</nav>';

	switch ( $tab ) {
		case 'migrazione':
			bnc_tab_migration( $base );
			break;
		case 'recensioni':
			bnc_tab_reviews( $base );
			break;
		case 'inventario':
			bnc_tab_inventory();
			break;
		default:
			bnc_tab_status( $base );
	}
	echo '</div>';
}

function bnc_tab_status( $base ) {
	$r = bnc_readiness();
	echo '<p>Quando tutte le righe sono verdi si possono disattivare i plugin Crocoblock. I dati di JetEngine restano nel database, quindi si può sempre tornare indietro riattivandoli.</p>';
	echo '<table class="widefat striped bnc-report" style="max-width:900px"><tbody>';
	foreach ( $r['checks'] as $c ) {
		echo '<tr><td>' . esc_html( $c[0] ) . '</td><td>' . esc_html( $c[1] ) . '</td><td class="' . ( $c[2] ? 'bnc-ok">OK' : 'bnc-warn">Da fare' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Plugin Crocoblock installati</h2><table class="widefat striped" style="max-width:900px"><thead><tr><th>Plugin</th><th>Versione</th><th>Stato</th></tr></thead><tbody>';
	foreach ( bnc_crocoblock_plugins() as $p ) {
		echo '<tr><td>' . esc_html( $p['name'] ) . '</td><td>' . esc_html( $p['version'] ) . '</td><td>' . ( $p['active'] ? 'attivo' : 'spento' ) . '</td></tr>';
	}
	echo '</tbody></table>';
	$when = get_option( 'bnc_migrated_at' );
	echo '<p>Ultima importazione: ' . esc_html( $when ? $when : 'mai' ) . ' &middot; <a href="' . esc_url( add_query_arg( 'tab', 'migrazione', $base ) ) . '">Importa da JetEngine</a></p>';
}

function bnc_report_table( $report ) {
	echo '<table class="widefat striped bnc-report"><thead><tr><th>Tipo</th><th>Nome</th><th>Azione</th><th>Posizione</th><th>In tabella</th><th>Stato</th><th>Note</th></tr></thead><tbody>';
	foreach ( $report as $r ) {
		$name = isset( $r['post_id'] ) && $r['post_id'] ? '<a href="' . esc_url( get_edit_post_link( $r['post_id'] ) ) . '">' . esc_html( $r['nome'] ) . '</a>' : esc_html( $r['nome'] );
		$note = isset( $r['note'] ) ? (array) $r['note'] : array();
		if ( ! empty( $r['extra'] ) ) {
			$note[] = 'campi extra conservati: ' . implode( ', ', $r['extra'] );
		}
		echo '<tr><td>' . esc_html( $r['tipo'] ) . '</td><td>' . $name . '</td><td>' . esc_html( $r['azione'] ) . '</td><td>' . esc_html( isset( $r['ordine'] ) ? (string) $r['ordine'] : '' ) . '</td><td>' . esc_html( isset( $r['tabella'] ) ? $r['tabella'] : '' ) . '</td><td>' . esc_html( isset( $r['stato'] ) ? $r['stato'] : '' ) . '</td><td>' . esc_html( implode( '; ', $note ) ) . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput
	}
	echo '</tbody></table>';
}

function bnc_tab_migration( $base ) {
	$tables = array_filter( array( bnc_cct_table( 'casino' ), bnc_cct_table( 'scommesse' ) ) );
	if ( ! $tables ) {
		echo '<div class="notice notice-warning"><p>Non trovo le tabelle di JetEngine (jet_cct_casino e jet_cct_scommesse) nel database.</p></div>';
		return;
	}
	$run = isset( $_POST['bnc_run'] ) && check_admin_referer( 'bnc_migrate' );
	if ( $run ) {
		$report = bnc_run_migration( false );
		echo '<div class="notice notice-success"><p>Importazione fatta. Controlla gli operatori e le tabelle sul sito.</p></div>';
	} else {
		$report = bnc_run_migration( true );
		echo '<p>Anteprima: cosa succede premendo <strong>Importa</strong>. Niente viene cancellato: JetEngine e i suoi dati restano come sono. Si può ripetere quando vuoi, gli operatori già importati vengono aggiornati con i dati di JetEngine.</p>';
		if ( ! class_exists( '\Jet_Engine\Query_Builder\Manager' ) ) {
			echo '<div class="notice notice-warning inline"><p>JetEngine non è attivo: l\'ordine sarà quello di creazione e tutti gli operatori pubblicati andranno in tabella.</p></div>';
		}
	}
	bnc_report_table( $report );
	if ( ! $run ) {
		echo '<form method="post" style="margin-top:16px">';
		wp_nonce_field( 'bnc_migrate' );
		echo '<button class="button button-primary" name="bnc_run" value="1">Importa</button></form>';
	}
}

/* ---------- Recensioni: bozza a blocchi ---------- */

function bnc_tab_reviews( $base ) {
	if ( isset( $_POST['bnc_draft'] ) && check_admin_referer( 'bnc_draft' ) ) {
		$new = bnc_create_review_draft( absint( $_POST['bnc_draft'] ) );
		if ( $new ) {
			echo '<div class="notice notice-success"><p>Pagina a blocchi pronta: <a href="' . esc_url( get_edit_post_link( $new ) ) . '">modifica</a> &middot; <a href="' . esc_url( get_preview_post_link( $new ) ) . '" target="_blank">anteprima</a></p></div>';
		}
	}
	echo '<p>Per ogni operatore con una pagina recensione si può creare una <strong>bozza a blocchi</strong> della recensione: testata con logo, bonus, voto e "Vai al sito" presi dall\'operatore, poi i testi della recensione copiati nella pagina come testo modificabile, poi la tabella. La pagina originale non viene toccata.</p>';
	$ops = get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => array( 'menu_order' => 'ASC' ) ) );
	echo '<table class="widefat striped"><thead><tr><th>Operatore</th><th>Pagina recensione</th><th>Bozza a blocchi</th></tr></thead><tbody>';
	foreach ( $ops as $op ) {
		$page  = (int) bnc_get( $op->ID, 'review_page' );
		$draft = bnc_review_draft_id( $op->ID );
		echo '<tr><td>' . esc_html( $op->post_title ) . '</td><td>' . ( $page ? '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank">' . esc_html( get_the_title( $page ) ) . '</a>' : '-' ) . '</td><td>';
		if ( $draft ) {
			echo '<form method="post" onsubmit="return confirm(\'Il contenuto della pagina viene rifatto dai campi dell\\\'operatore: le modifiche fatte a mano nella pagina si perdono. Continuare?\');">';
			wp_nonce_field( 'bnc_draft' );
			echo '<a href="' . esc_url( get_edit_post_link( $draft ) ) . '">Apri</a> &middot; <a href="' . esc_url( get_permalink( $draft ) ) . '" target="_blank">Vedi</a> &middot; <button class="button-link" name="bnc_draft" value="' . (int) $op->ID . '">Rifai</button></form>';
		} elseif ( $page ) {
			echo '<form method="post">';
			wp_nonce_field( 'bnc_draft' );
			echo '<button class="button" name="bnc_draft" value="' . (int) $op->ID . '">Crea bozza</button></form>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

/**
 * Contenuto a blocchi della recensione di un operatore.
 */
function bnc_review_draft_content( $op_id ) {
	$type = bnc_tipo( $op_id );
	$c    = '<!-- wp:betnero/scheda-operatore {"operatore":' . (int) $op_id . ',"parte":"testata"} /-->' . "\n\n";
	foreach ( bnc_review_sections() as $key => $title ) {
		if ( 'procontro' === $key ) {
			$c .= bnc_block_procontro( bnc_get( $op_id, 'pro' ), bnc_get( $op_id, 'contro' ) );
			continue;
		}
		$body = bnc_html_to_blocks( (string) bnc_get( $op_id, $key ) );
		if ( '' === trim( $body ) ) {
			continue;
		}
		if ( $title ) {
			$c .= bnc_block_heading( esc_html( $title ), 2 );
		}
		$c .= $body;
	}
	$c .= '<!-- wp:betnero/tabella-' . ( 'scommesse' === $type ? 'scommesse' : 'casino' ) . ' {"showFilters":false} /-->' . "\n";
	return $c;
}

/**
 * Bozza gia' creata per la pagina recensione di un operatore (0 se non c'e').
 */
function bnc_review_draft_id( $op_id ) {
	$page = (int) bnc_get( $op_id, 'review_page' );
	if ( ! $page ) {
		return 0;
	}
	$ids = get_posts( array( 'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_bn_bozza_di', 'meta_value' => $page ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Crea la pagina a blocchi della recensione, oppure ne rifa' il contenuto se
 * esiste gia' (titolo, indirizzo e stato restano quelli attuali).
 */
function bnc_create_review_draft( $op_id ) {
	if ( BNC_POST_TYPE !== get_post_type( $op_id ) ) {
		return 0;
	}
	$page = (int) bnc_get( $op_id, 'review_page' );
	$orig = $page ? get_post( $page ) : null;
	$c    = bnc_review_draft_content( $op_id );

	$existing = bnc_review_draft_id( $op_id );
	if ( $existing ) {
		$res = wp_update_post( wp_slash( array( 'ID' => $existing, 'post_content' => $c ) ), true );
		return is_wp_error( $res ) ? 0 : $existing;
	}

	$new = wp_insert_post( wp_slash( array(
		'post_type'    => 'page',
		'post_status'  => 'draft',
		'post_title'   => ( $orig ? $orig->post_title : get_the_title( $op_id ) ) . ' (bozza a blocchi)',
		'post_parent'  => $orig ? $orig->post_parent : 0,
		'post_content' => $c,
		'post_author'  => get_current_user_id(),
	) ) );
	if ( ! $new || is_wp_error( $new ) ) {
		return 0;
	}
	update_post_meta( $new, '_bn_operatore', $op_id );
	update_post_meta( $new, '_bn_barra', 'page' );
	if ( $page ) {
		update_post_meta( $new, '_bn_bozza_di', $page );
		foreach ( array( '_yoast_wpseo_title', '_yoast_wpseo_metadesc', '_yoast_wpseo_focuskw' ) as $k ) {
			$v = get_post_meta( $page, $k, true );
			if ( $v ) {
				update_post_meta( $new, $k, $v );
			}
		}
	}
	return (int) $new;
}

/* ---------- Inventario ---------- */

function bnc_tab_inventory() {
	$scan = bnc_scan_inventory();
	$rep  = bnc_replacements();
	$lrep = bnc_listing_replacements();

	echo '<h2>Widget e shortcode Crocoblock (numero di contenuti che li usano)</h2>';
	echo '<table class="widefat striped" style="max-width:1000px"><thead><tr><th>Elemento</th><th>Contenuti</th><th>Si sostituisce con</th></tr></thead><tbody>';
	foreach ( $scan['totals'] as $type => $n ) {
		echo '<tr><td><code>' . esc_html( $type ) . '</code></td><td>' . (int) $n . '</td><td>' . esc_html( isset( $rep[ $type ] ) ? $rep[ $type ] : 'Da valutare' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Listing JetEngine usati</h2><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Listing</th><th>Contenuti</th><th>Si sostituisce con</th></tr></thead><tbody>';
	foreach ( $scan['listings'] as $lid => $n ) {
		echo '<tr><td>' . esc_html( get_the_title( $lid ) ) . ' (#' . (int) $lid . ')</td><td>' . (int) $n . '</td><td>' . esc_html( isset( $lrep[ $lid ] ) ? $lrep[ $lid ] : 'Da valutare' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Popup JetPopup agganciati alle pagine</h2><table class="widefat striped" style="max-width:1000px"><thead><tr><th>Pagina</th><th>Popup</th><th>Barra Betnero</th></tr></thead><tbody>';
	foreach ( bnc_popup_attachments() as $pid => $pop ) {
		$bar = bnc_bar_operator_raw( $pid );
		echo '<tr><td><a href="' . esc_url( get_edit_post_link( $pid ) ) . '">' . esc_html( get_the_title( $pid ) ) . '</a></td><td>' . esc_html( get_the_title( $pop ) ) . '</td><td>' . ( $bar ? esc_html( get_the_title( $bar ) ) : '<span class="bnc-warn">nessuna</span>' ) . '</td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Contenuti (' . count( $scan['items'] ) . ')</h2><table class="widefat striped"><thead><tr><th>Titolo</th><th>Tipo</th><th>Stato</th><th>Elementi Crocoblock</th></tr></thead><tbody>';
	foreach ( $scan['items'] as $it ) {
		$parts = array();
		foreach ( $it['widgets'] as $w => $n ) {
			$parts[] = $w . ' ×' . $n;
		}
		foreach ( $it['listings'] as $lid ) {
			$parts[] = 'listing #' . $lid;
		}
		$link = get_edit_post_link( $it['id'] );
		$view = get_permalink( $it['id'] );
		echo '<tr><td>' . ( $link ? '<a href="' . esc_url( $link ) . '">' . esc_html( $it['title'] ? $it['title'] : '#' . $it['id'] ) . '</a>' : esc_html( $it['title'] ) ) . ( $view && 'publish' === $it['status'] ? ' &middot; <a href="' . esc_url( $view ) . '" target="_blank">vedi</a>' : '' ) . '</td><td>' . esc_html( $it['type'] ) . '</td><td>' . esc_html( $it['status'] ) . '</td><td>' . esc_html( implode( ', ', $parts ) ) . '</td></tr>';
	}
	echo '</tbody></table>';
}

/** Barra impostata su una pagina, anche se al momento e' spenta per JetPopup. */
function bnc_bar_operator_raw( $post_id ) {
	$v = get_post_meta( $post_id, '_bn_barra', true );
	return 'page' === $v ? bnc_operator_for_page( $post_id ) : (int) $v;
}

/* ---------- Impostazioni ---------- */

function bnc_page_settings() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( isset( $_POST['bnc_settings'] ) && check_admin_referer( 'bnc_settings' ) ) {
		$in  = wp_unslash( $_POST['bnc_settings'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$map = array();
		foreach ( (array) ( isset( $in['map'] ) ? $in['map'] : array() ) as $tid => $v ) {
			if ( in_array( $v, array( 'casino', 'scommesse', 'none' ), true ) ) {
				$map[ absint( $tid ) ] = $v;
			}
		}
		update_option( 'bnc_toplist', array(
			'enabled'   => empty( $in['enabled'] ) ? '0' : '1',
			'sidebar'   => empty( $in['sidebar'] ) ? '0' : '1',
			'bar_force' => empty( $in['bar_force'] ) ? '0' : '1',
			'default'   => ( isset( $in['default'] ) && 'scommesse' === $in['default'] ) ? 'scommesse' : 'casino',
			'limit'     => max( 1, min( 10, absint( isset( $in['limit'] ) ? $in['limit'] : 5 ) ) ),
			'map'       => $map,
		) );
		echo '<div class="notice notice-success"><p>Impostazioni salvate.</p></div>';
	}
	$s = bnc_toplist_settings();
	echo '<div class="wrap"><h1>Impostazioni Betnero</h1>';
	if ( bnc_snippet_active() ) {
		echo '<div class="notice notice-warning inline"><p>Lo snippet 8 "Betnero: toplist per categoria" è ancora attivo: finché resta acceso la toplist la gestisce lui e queste impostazioni non hanno effetto. Disattivalo in Snippets quando vuoi passare a questa versione.</p></div>';
	}
	echo '<form method="post">';
	wp_nonce_field( 'bnc_settings' );
	echo '<h2>Toplist negli articoli</h2><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th>Nel testo</th><td><label><input type="checkbox" name="bnc_settings[enabled]" value="1"' . checked( '1', (string) $s['enabled'], false ) . '> Inserisci la toplist prima del primo titolo H2 (o dopo il primo paragrafo)</label></td></tr>';
	echo '<tr><th>In sidebar</th><td><label><input type="checkbox" name="bnc_settings[sidebar]" value="1"' . checked( '1', (string) $s['sidebar'], false ) . '> Shortcode <code>[bn_toplist_sidebar]</code> attivo</label></td></tr>';
	echo '<tr><th>Operatori mostrati</th><td><input type="number" min="1" max="10" name="bnc_settings[limit]" value="' . (int) $s['limit'] . '" class="small-text"></td></tr>';
	echo '<tr><th>Tipo predefinito</th><td><select name="bnc_settings[default]"><option value="casino"' . selected( $s['default'], 'casino', false ) . '>Casino</option><option value="scommesse"' . selected( $s['default'], 'scommesse', false ) . '>Scommesse</option></select></td></tr>';
	echo '</tbody></table>';

	echo '<h2>Categorie</h2><p>Vuoto = suggerimento automatico dal nome della categoria (scritto tra parentesi). Le sottocategorie seguono la categoria madre.</p>';
	echo '<table class="widefat striped" style="max-width:700px"><thead><tr><th>Categoria</th><th>Articoli</th><th>Toplist</th></tr></thead><tbody>';
	$cats   = get_categories( array( 'hide_empty' => false ) );
	$labels = array( 'casino' => 'casino', 'scommesse' => 'scommesse', 'none' => 'nessuna' );
	foreach ( $cats as $cat ) {
		$cur   = isset( $s['map'][ $cat->term_id ] ) ? $s['map'][ $cat->term_id ] : '';
		$guess = bnc_guess_type( $cat );
		$guess = $guess ? $guess : $s['default'];
		$depth = count( get_ancestors( $cat->term_id, 'category' ) );
		echo '<tr><td>' . str_repeat( '&nbsp;&nbsp;&nbsp;&nbsp;', $depth ) . esc_html( $cat->name ) . '</td><td>' . (int) $cat->count . '</td><td><select name="bnc_settings[map][' . (int) $cat->term_id . ']">';
		echo '<option value="">Automatico (' . esc_html( $labels[ $guess ] ) . ')</option>';
		foreach ( array( 'casino' => 'Casino', 'scommesse' => 'Scommesse', 'none' => 'Nessuna' ) as $k => $l ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $cur, $k, false ) . '>' . esc_html( $l ) . '</option>';
		}
		echo '</select></td></tr>';
	}
	echo '</tbody></table>';

	echo '<h2>Barra in basso</h2><table class="form-table" role="presentation"><tbody>';
	echo '<tr><th>Prova con JetPopup acceso</th><td><label><input type="checkbox" name="bnc_settings[bar_force]" value="1"' . checked( '1', (string) ( isset( $s['bar_force'] ) ? $s['bar_force'] : '0' ), false ) . '> Mostra la barra Betnero anche se JetPopup è attivo (solo per prove: si vedranno due barre)</label></td></tr>';
	echo '</tbody></table>';

	submit_button( 'Salva' );
	echo '</form></div>';
}
