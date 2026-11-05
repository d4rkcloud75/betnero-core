<?php
/**
 * Plugin Name:       Betnero Core
 * Plugin URI:        https://www.betnero.it/
 * Description:       Operatori (casino e bookmaker), tabelle, toplist, schede recensione e barra in basso di Betnero. Sostituisce JetEngine, JetSmartFilters, JetPopup e lo snippet della toplist.
 * Version:           0.3.1
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Carmelo Brischetto
 * Author URI:        https://carmelobrischetto.it/
 * License:           GPLv2 or later
 * Text Domain:       betnero-core
 * GitHub Plugin URI: d4rkcloud75/betnero-core
 * Primary Branch:    main
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BETNERO_CORE_VERSION', '0.3.1' );
define( 'BETNERO_CORE_FILE', __FILE__ );
define( 'BETNERO_CORE_DIR', plugin_dir_path( __FILE__ ) );
define( 'BETNERO_CORE_URL', plugin_dir_url( __FILE__ ) );

require_once BETNERO_CORE_DIR . 'inc/fields.php';
require_once BETNERO_CORE_DIR . 'inc/operators.php';
require_once BETNERO_CORE_DIR . 'inc/metabox.php';
require_once BETNERO_CORE_DIR . 'inc/data.php';
require_once BETNERO_CORE_DIR . 'inc/render.php';
require_once BETNERO_CORE_DIR . 'inc/convert.php';
require_once BETNERO_CORE_DIR . 'inc/blocks.php';
require_once BETNERO_CORE_DIR . 'inc/home-blocks.php';
require_once BETNERO_CORE_DIR . 'inc/toplist-auto.php';
require_once BETNERO_CORE_DIR . 'inc/bottom-bar.php';
require_once BETNERO_CORE_DIR . 'inc/migration.php';
require_once BETNERO_CORE_DIR . 'inc/inventory.php';
require_once BETNERO_CORE_DIR . 'inc/admin-pages.php';
require_once BETNERO_CORE_DIR . 'inc/rest.php';

register_activation_hook( __FILE__, function () {
	bnc_register_post_type();
	flush_rewrite_rules();
} );
