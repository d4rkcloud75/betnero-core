<?php
/**
 * Google Analytics 4 (0.4.6): lo stesso codice che oggi in produzione arriva
 * dal tema figlio di Hello. Spostato qui perche' con il nuovo tema resti attivo.
 *
 * Per non sporcare le statistiche, il codice esce solo sul dominio di
 * produzione (www.betnero.it): sullo staging non viene stampato.
 * Filtri: "bnc_ga4_id" per cambiare l'ID, "bnc_ga4_enabled" per forzarlo.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bnc_ga4_id() {
	return (string) apply_filters( 'bnc_ga4_id', 'G-XF6XTYQVV7' );
}

function bnc_ga4_enabled() {
	$host    = (string) wp_parse_url( home_url(), PHP_URL_HOST );
	$enabled = in_array( $host, array( 'www.betnero.it', 'betnero.it' ), true );
	if ( is_admin() || is_preview() || is_customize_preview() ) {
		$enabled = false;
	}
	return (bool) apply_filters( 'bnc_ga4_enabled', $enabled );
}

add_action( 'wp_head', function () {
	$id = bnc_ga4_id();
	if ( '' === $id || ! bnc_ga4_enabled() ) {
		return;
	}
	// Se un altro plugin o tema lo stampa gia', non lo ripetiamo.
	if ( did_action( 'bnc_ga4_printed' ) ) {
		return;
	}
	do_action( 'bnc_ga4_printed' );
	echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . esc_attr( $id ) . "\"></script>\n";
	echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','" . esc_js( $id ) . "');</script>\n";
}, 5 );
