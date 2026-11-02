<?php
/**
 * Inventario: dove il sito usa ancora Crocoblock (widget Elementor Jet*,
 * shortcode e blocchi JetEngine, popup agganciati).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cosa sostituisce ogni widget Crocoblock.
 */
function bnc_replacements() {
	return array(
		'jet-listing-grid'          => 'Blocchi Tabella casino / Tabella scommesse / Toplist / Scheda operatore',
		'jet-listing-dynamic-field' => 'Parte del listing (sparisce con il listing)',
		'jet-listing-dynamic-image' => 'Parte del listing (sparisce con il listing)',
		'jet-listing-dynamic-link'  => 'Parte del listing (sparisce con il listing)',
		'jet-smart-filters-select'  => 'Filtri integrati nel blocco Tabella casino',
		'jet-smart-filters-remove-filters' => 'Pulsante "Rimuovi filtri" del blocco Tabella',
		'jet-accordion'             => 'Blocco Dettagli con stile "FAQ Betnero"',
		'jet-tabs'                  => 'Blocco Dettagli o Gruppo',
		'jet-ajax-search'           => 'Blocco Cerca di WordPress (gia\' nel tema)',
		'jet-breadcrumbs'           => 'Briciole di pane di Yoast (gia\' nel tema)',
		'jet-reviews'               => 'Stelle del blocco Scheda operatore',
	);
}

/**
 * Listing JetEngine noti => blocco che li sostituisce.
 */
function bnc_listing_replacements() {
	return array(
		163   => 'Tabella casino',
		41739 => 'Tabella scommesse',
		971   => 'Toplist (sidebar) casino',
		53286 => 'Toplist (sidebar) scommesse',
		44362 => 'Scheda operatore',
	);
}

function bnc_crocoblock_plugins() {
	if ( ! function_exists( 'get_plugins' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$out = array();
	foreach ( get_plugins() as $file => $data ) {
		$slug = dirname( $file );
		if ( 0 === strpos( $slug, 'jet-' ) || false !== stripos( $slug, 'crocoblock' ) || false !== stripos( $data['Author'], 'crocoblock' ) ) {
			$out[ $file ] = array( 'name' => $data['Name'], 'version' => $data['Version'], 'active' => is_plugin_active( $file ) );
		}
	}
	return $out;
}

/**
 * Scansione dei contenuti.
 *
 * @return array{items: array, totals: array, listings: array}
 */
function bnc_scan_inventory() {
	global $wpdb;
	$items    = array();
	$totals   = array();
	$listings = array();

	// Contenuti fatti con Elementor.
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT p.ID, p.post_type, p.post_status, p.post_title, pm.meta_value AS data
		FROM {$wpdb->posts} p JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_elementor_data'
		WHERE p.post_status IN ('publish','draft','private','future') AND p.post_type NOT IN ('revision','jet-engine')
		AND pm.meta_value LIKE '%jet-%'"
	);
	foreach ( $rows as $r ) {
		$w = array();
		if ( preg_match_all( '/"widgetType":"(jet-[a-z0-9\-]+)"/', $r->data, $m ) ) {
			foreach ( $m[1] as $type ) {
				$w[ $type ] = isset( $w[ $type ] ) ? $w[ $type ] + 1 : 1;
			}
		}
		$l = array();
		if ( preg_match_all( '/"lisitng_id":"?(\d+)/', $r->data, $m ) ) {
			$l = array_map( 'intval', array_unique( $m[1] ) );
		}
		if ( ! $w ) {
			continue;
		}
		foreach ( $w as $type => $n ) {
			$totals[ $type ] = isset( $totals[ $type ] ) ? $totals[ $type ] + 1 : 1;
		}
		foreach ( $l as $lid ) {
			$listings[ $lid ] = isset( $listings[ $lid ] ) ? $listings[ $lid ] + 1 : 1;
		}
		$items[] = array(
			'id'       => (int) $r->ID,
			'title'    => $r->post_title,
			'type'     => $r->post_type,
			'status'   => $r->post_status,
			'widgets'  => $w,
			'listings' => $l,
		);
	}

	// Shortcode e blocchi Jet nel testo (editor classico o a blocchi).
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT ID, post_type, post_status, post_title, post_content FROM {$wpdb->posts}
		WHERE post_status IN ('publish','draft','private','future') AND post_type NOT IN ('revision')
		AND ( post_content LIKE '%[jet\_%' OR post_content LIKE '%<!-- wp:jet-%' OR post_content LIKE '%[jet-%' )"
	);
	foreach ( $rows as $r ) {
		$w = array();
		if ( preg_match_all( '/\[(jet[_\-][a-z0-9_\-]+)|<!-- wp:(jet-[a-z0-9\-\/]+)/', $r->post_content, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $x ) {
				$type       = ! empty( $x[1] ) ? '[' . $x[1] . ']' : 'blocco ' . $x[2];
				$w[ $type ] = isset( $w[ $type ] ) ? $w[ $type ] + 1 : 1;
			}
		}
		if ( ! $w ) {
			continue;
		}
		foreach ( $w as $type => $n ) {
			$totals[ $type ] = isset( $totals[ $type ] ) ? $totals[ $type ] + 1 : 1;
		}
		$items[] = array(
			'id'       => (int) $r->ID,
			'title'    => $r->post_title,
			'type'     => $r->post_type,
			'status'   => $r->post_status,
			'widgets'  => $w,
			'listings' => array(),
		);
	}

	arsort( $totals );
	return array( 'items' => $items, 'totals' => $totals, 'listings' => $listings );
}

/**
 * Stato generale: pronto per spegnere Crocoblock?
 */
function bnc_readiness() {
	$checks = array();

	$cct = 0;
	foreach ( array( 'casino', 'scommesse' ) as $t ) {
		$cct += count( bnc_cct_rows( $t ) );
	}
	$imported = count( get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids', 'meta_key' => '_bn_cct', 'meta_compare' => 'EXISTS' ) ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
	$checks[] = array( 'Operatori importati da JetEngine', $imported . ' su ' . $cct, $cct > 0 && $imported >= $cct );

	$checks[] = array( 'Snippet 8 (toplist) spento', bnc_snippet_active() ? 'ancora attivo' : 'spento', ! bnc_snippet_active() );

	$scan      = bnc_scan_inventory();
	$published = 0;
	foreach ( $scan['items'] as $it ) {
		if ( 'publish' === $it['status'] && in_array( $it['type'], array( 'page', 'post' ), true ) ) {
			$published++;
		}
	}
	$checks[] = array( 'Pagine e articoli pubblicati con widget Crocoblock', (string) $published, 0 === $published );

	$pop      = count( bnc_popup_attachments() );
	$bars     = 0;
	foreach ( array_keys( bnc_popup_attachments() ) as $pid ) {
		$bars += get_post_meta( $pid, '_bn_barra', true ) ? 1 : 0;
	}
	$checks[] = array( 'Pagine con popup JetPopup che hanno la barra Betnero', $bars . ' su ' . $pop, $bars >= $pop );

	$active   = array_filter( bnc_crocoblock_plugins(), function ( $p ) {
		return $p['active'];
	} );
	$checks[] = array( 'Plugin Crocoblock attivi', (string) count( $active ), 0 === count( $active ) );

	return array( 'checks' => $checks, 'scan' => $scan );
}
