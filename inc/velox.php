<?php
/**
 * Velox Optimizer: script che non devono aspettare la prima interazione.
 * Cookie Notice deve mostrare il banner subito, appena la pagina si apre
 * (l'esclusione "Cookie banners" di Velox non riconosce questo plugin).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'velox_delay_js_extra_exclusions', function ( $list ) {
	$list   = (array) $list;
	$list[] = 'cookie-notice';
	$list[] = 'cnArgs';
	// 0.6.7: quote sempre fresche anche sulle pagine in cache, senza aspettare che il lettore si muova.
	$list[] = 'betnero-quote';
	$list[] = 'quote.min.js';
	return $list;
} );
