<?php
/**
 * Misura del tempo di generazione della pagina, indipendente dalla rete.
 * Con ?bnc_timing=1 la pagina scrive in fondo un commento HTML:
 * <!-- bnc_timing php=0.842s query=123 plugin=48 memoria=42MB -->
 * Senza il parametro non fa nulla.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'shutdown', function () {
	if ( ! isset( $_GET['bnc_timing'] ) || is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (float) $_SERVER['REQUEST_TIME_FLOAT'] : 0;
	$php   = $start ? microtime( true ) - $start : 0;
	$q     = function_exists( 'get_num_queries' ) ? get_num_queries() : 0;
	$plug  = count( (array) get_option( 'active_plugins', array() ) );
	printf( "\n<!-- bnc_timing php=%.3fs query=%d plugin=%d memoria=%dMB -->\n", $php, $q, $plug, round( memory_get_peak_usage() / 1048576 ) );
}, 9999 );
