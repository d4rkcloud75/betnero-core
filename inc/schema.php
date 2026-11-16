<?php
/**
 * Schema scritti a mano contro schema di Yoast (0.4.9).
 *
 * Regola chiesta da Francesco: se una pagina ha uno schema JSON-LD scritto a
 * mano (riquadro del plugin "Betnero Schema Markup") e quello schema descrive
 * la pagina (WebPage, Article, CollectionPage e simili), vale quello e Yoast
 * su quella pagina non stampa il suo. Cosi' non ci sono doppioni.
 *
 * Se lo schema a mano contiene solo tipi aggiuntivi (per esempio solo FAQPage
 * o HowTo), Yoast resta: i due si completano.
 *
 * In piu', nello schema scritto a mano la data "dateModified" viene sostituita
 * con la data vera dell'ultima modifica della pagina, cosi' non resta indietro.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function bnc_schema_plugin_active() {
	return in_array( 'betnero-schema-markup/betnero-schema-markup.php', (array) get_option( 'active_plugins', array() ), true );
}

/**
 * Testi JSON-LD scritti a mano salvati nella pagina (qualsiasi campo che non sia di Yoast).
 */
function bnc_manual_schema_texts( $post_id ) {
	static $cache = array();
	if ( isset( $cache[ $post_id ] ) ) {
		return $cache[ $post_id ];
	}
	$out = array();
	if ( bnc_schema_plugin_active() ) {
		foreach ( (array) get_post_meta( $post_id ) as $key => $vals ) {
			if ( 0 === strpos( $key, '_yoast' ) ) {
				continue;
			}
			foreach ( (array) $vals as $v ) {
				if ( is_string( $v ) && false !== strpos( $v, 'schema.org' ) && false !== strpos( $v, '@type' ) ) {
					$out[] = $v;
				}
			}
		}
	}
	$cache[ $post_id ] = $out;
	return $out;
}

/**
 * True se lo schema scritto a mano descrive la pagina stessa (e quindi sostituisce Yoast).
 */
function bnc_manual_schema_replaces_yoast( $post_id ) {
	foreach ( bnc_manual_schema_texts( $post_id ) as $txt ) {
		if ( preg_match( '/"@type"\s*:\s*(?:\[[^\]]*)?"(WebPage|Article|NewsArticle|BlogPosting|TechArticle|CollectionPage|AboutPage|ContactPage|ItemPage|Review)"/', $txt ) ) {
			return true;
		}
	}
	return false;
}

// Yoast tace sulle pagine con schema scritto a mano completo.
add_filter( 'wpseo_json_ld_output', function ( $data ) {
	if ( is_singular() && bnc_manual_schema_replaces_yoast( get_queried_object_id() ) ) {
		return false;
	}
	return $data;
} );

// Data di modifica sempre aggiornata negli schema scritti a mano.
add_action( 'wp_head', function () {
	if ( ! is_singular() || ! bnc_manual_schema_texts( get_queried_object_id() ) ) {
		return;
	}
	ob_start();
}, 0 );

add_action( 'wp_head', function () {
	if ( ! is_singular() || ! bnc_manual_schema_texts( get_queried_object_id() ) ) {
		return;
	}
	$head = ob_get_clean();
	if ( false === $head ) {
		return;
	}
	$mod = get_post_modified_time( 'c', true, get_queried_object_id() );
	echo preg_replace_callback( // phpcs:ignore WordPress.Security.EscapeOutput
		'#(<script[^>]*application/ld\+json[^>]*>)(.*?)(</script>)#is',
		function ( $m ) use ( $mod ) {
			if ( false !== strpos( $m[1], 'yoast-schema-graph' ) ) {
				return $m[0];
			}
			$body = preg_replace( '/("dateModified"\s*:\s*")[^"]*(")/', '${1}' . $mod . '${2}', $m[2] );
			return $m[1] . $body . $m[3];
		},
		$head
	);
}, PHP_INT_MAX );
