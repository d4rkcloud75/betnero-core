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
	return $list;
} );
