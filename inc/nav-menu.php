<?php
/**
 * Menu del header modificabile da Aspetto > Menu (0.8.5).
 *
 * Il tema a blocchi scrive le voci del menu nel file parts/header.html.
 * Qui registriamo la posizione "Menu principale (header)" e, al momento di mostrare
 * il blocco Navigazione del header (classe bn-nav), sostituiamo le sue voci con quelle
 * del menu assegnato a quella posizione (o, se non e' assegnato, del menu "Main menu").
 * Senza menu restano le voci scritte nel tema.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'after_setup_theme', function () {
	register_nav_menus( array( 'bn-header' => 'Menu principale (header)' ) );
}, 20 );

/** Menu da usare nel header: quello della posizione, altrimenti quello chiamato "Main menu". */
function bnc_header_menu() {
	$loc = get_nav_menu_locations();
	if ( ! empty( $loc['bn-header'] ) ) {
		$m = wp_get_nav_menu_object( (int) $loc['bn-header'] );
		if ( $m ) {
			return $m;
		}
	}
	$m = wp_get_nav_menu_object( 'Main menu' );
	return $m ? $m : null;
}

/** Voci del menu classico come blocchi navigation-link / navigation-submenu. */
function bnc_menu_items_to_blocks( $items, $parent = 0 ) {
	$out = array();
	foreach ( $items as $it ) {
		if ( (int) $it->menu_item_parent !== (int) $parent ) {
			continue;
		}
		$attrs = array(
			'label' => $it->title, // puo' contenere HTML (es. il colore di "Calcolatore di Rischio")
			'url'   => $it->url,
			'kind'  => 'custom',
		);
		if ( '_blank' === $it->target ) {
			$attrs['opensInNewTab'] = true;
		}
		if ( '' !== (string) $it->xfn ) {
			$attrs['rel'] = $it->xfn;
		}
		if ( '' !== (string) $it->attr_title ) {
			$attrs['title'] = $it->attr_title;
		}
		$cls = trim( implode( ' ', array_filter( (array) $it->classes ) ) );
		if ( '' !== $cls ) {
			$attrs['className'] = $cls;
		}
		$children = bnc_menu_items_to_blocks( $items, $it->ID );
		if ( 0 === (int) $parent ) {
			$attrs['isTopLevelLink'] = true;
		}
		$name  = $children ? 'core/navigation-submenu' : 'core/navigation-link';
		$out[] = array(
			'blockName'    => $name,
			'attrs'        => $attrs,
			'innerBlocks'  => $children,
			'innerHTML'    => '',
			'innerContent' => array_fill( 0, count( $children ), null ),
		);
	}
	return $out;
}

add_filter( 'render_block_data', function ( $block ) {
	if ( is_admin() || 'core/navigation' !== $block['blockName'] || empty( $block['attrs']['className'] ) || false === strpos( (string) $block['attrs']['className'], 'bn-nav' ) ) {
		return $block;
	}
	$menu = bnc_header_menu();
	if ( ! $menu ) {
		return $block;
	}
	$items = wp_get_nav_menu_items( $menu->term_id, array( 'update_post_term_cache' => false ) );
	if ( ! $items ) {
		return $block; // menu vuoto: restano le voci del tema
	}
	$inner = bnc_menu_items_to_blocks( $items );
	$block['innerBlocks']  = $inner;
	$block['innerContent'] = array_fill( 0, count( $inner ), null );
	unset( $block['attrs']['ref'] );
	return $block;
} );

/* Il menu e' in tutte le pagine: dopo il salvataggio si svuota la cache delle pagine (un minuto dopo). */
add_action( 'wp_update_nav_menu', 'bnc_schedule_page_cache_purge' );
