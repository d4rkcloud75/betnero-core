<?php
/**
 * Aggiornamento di tema e plugin direttamente da GitHub, senza tag e senza zip.
 *
 * POST /wp-json/betnero/v1/aggiorna   { "cosa": "tema" | "core", "ref": "main" }
 *
 * Scarica l'archivio del ramo (o del tag) dai repository pubblici di Betnero
 * su GitHub e lo installa al posto della versione attuale, con lo stesso
 * meccanismo di WordPress per i caricamenti manuali. Solo amministratori.
 * I repository sono fissi nel codice: non si puo' installare altro.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bnc_deploy_targets() {
	return array(
		'tema' => array( 'repo' => 'd4rkcloud75/betnero-theme', 'slug' => 'betnero-theme', 'type' => 'theme' ),
		'core' => array( 'repo' => 'd4rkcloud75/betnero-core', 'slug' => 'betnero-core', 'type' => 'plugin', 'file' => 'betnero-core/betnero-core.php' ),
	);
}

function bnc_deploy_version( $t ) {
	if ( 'theme' === $t['type'] ) {
		$th = wp_get_theme( $t['slug'] );
		return $th->exists() ? $th->get( 'Version' ) : '';
	}
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	$f = WP_PLUGIN_DIR . '/' . $t['file'];
	return file_exists( $f ) ? (string) get_plugin_data( $f, false, false )['Version'] : '';
}

function bnc_deploy_run( $cosa, $ref ) {
	$targets = bnc_deploy_targets();
	if ( ! isset( $targets[ $cosa ] ) ) {
		return new WP_Error( 'bnc_deploy_target', 'Scegli "tema" oppure "core".', array( 'status' => 400 ) );
	}
	if ( ! preg_match( '/^[A-Za-z0-9._\/-]{1,80}$/', $ref ) ) {
		return new WP_Error( 'bnc_deploy_ref', 'Ramo o tag non valido.', array( 'status' => 400 ) );
	}
	$t      = $targets[ $cosa ];
	$before = bnc_deploy_version( $t );
	$kind   = preg_match( '/^v?\d+\.\d+\.\d+$/', $ref ) ? 'tags' : 'heads';
	$url    = 'https://github.com/' . $t['repo'] . '/archive/refs/' . $kind . '/' . $ref . '.zip';

	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/misc.php';
	require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
	if ( ! function_exists( 'get_plugin_data' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}

	$tmp = download_url( $url, 120 );
	if ( is_wp_error( $tmp ) ) {
		return new WP_Error( 'bnc_deploy_download', 'Download non riuscito: ' . $tmp->get_error_message(), array( 'status' => 502 ) );
	}

	// L'archivio di GitHub ha la cartella "repo-ramo": va rinominata come la cartella installata.
	$rename = function ( $source, $remote_source ) use ( $t ) {
		global $wp_filesystem;
		$wanted = trailingslashit( $remote_source ) . $t['slug'] . '/';
		if ( untrailingslashit( $source ) === untrailingslashit( $wanted ) ) {
			return $source;
		}
		if ( $wp_filesystem && $wp_filesystem->move( $source, $wanted, true ) ) {
			return $wanted;
		}
		return new WP_Error( 'bnc_deploy_rename', 'Impossibile preparare la cartella ' . $t['slug'] );
	};
	add_filter( 'upgrader_source_selection', $rename, 10, 2 );

	$skin     = new Automatic_Upgrader_Skin();
	$upgrader = 'theme' === $t['type'] ? new Theme_Upgrader( $skin ) : new Plugin_Upgrader( $skin );
	$result   = $upgrader->run( array(
		'package'           => $tmp,
		'destination'       => 'theme' === $t['type'] ? get_theme_root() : WP_PLUGIN_DIR,
		'clear_destination' => true,
		'clear_working'     => true,
		'abort_if_destination_exists' => false,
		'hook_extra'        => 'theme' === $t['type'] ? array( 'theme' => $t['slug'], 'type' => 'theme', 'action' => 'update' ) : array( 'plugin' => $t['file'], 'type' => 'plugin', 'action' => 'update' ),
	) );

	remove_filter( 'upgrader_source_selection', $rename, 10 );
	if ( file_exists( $tmp ) ) {
		wp_delete_file( $tmp );
	}
	if ( is_wp_error( $result ) || ! $result ) {
		$msg = is_wp_error( $result ) ? $result->get_error_message() : implode( ' ', (array) $skin->get_upgrade_messages() );
		return new WP_Error( 'bnc_deploy_install', 'Installazione non riuscita: ' . $msg, array( 'status' => 500 ) );
	}

	// Il plugin aggiornato resta attivo; la cache delle versioni va ricaricata.
	if ( 'plugin' === $t['type'] && ! is_plugin_active( $t['file'] ) ) {
		activate_plugin( $t['file'] );
	}
	wp_clean_themes_cache();
	wp_clean_plugins_cache( false );
	clearstatcache();
	if ( function_exists( 'opcache_reset' ) ) {
		@opcache_reset(); // phpcs:ignore WordPress.PHP.NoSilencedErrors
	}
	do_action( 'bnc_deployed', $cosa, $ref );

	return array(
		'cosa'  => $cosa,
		'ref'   => $ref,
		'prima' => $before,
		'dopo'  => bnc_deploy_version( $t ),
	);
}

add_action( 'rest_api_init', function () {
	register_rest_route( 'betnero/v1', '/aggiorna', array(
		'methods'             => 'POST',
		'permission_callback' => function () {
			return current_user_can( 'update_themes' ) && current_user_can( 'update_plugins' );
		},
		'args'                => array(
			'cosa' => array( 'type' => 'string', 'required' => true ),
			'ref'  => array( 'type' => 'string', 'default' => 'main' ),
		),
		'callback'            => function ( $req ) {
			return bnc_deploy_run( (string) $req['cosa'], (string) $req['ref'] );
		},
	) );
} );
