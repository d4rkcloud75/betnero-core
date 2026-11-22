<?php
/**
 * Crawl budget (0.8.1), su indicazione di Andrea e Francesco (email del 22/11/2026).
 *
 * 1. Pronostici vecchi in 410: articoli della categoria Pronostici pubblicati prima del
 *    17/07/2026 (2.036 in produzione). Rispondono 410, spariscono da archivi, ricerca, feed,
 *    articolo precedente/successivo e sitemap di Yoast. Le paginazioni di Pronostici che non
 *    esistono piu' (fino alla 105) rispondono 410 invece di 404. Gli articoli restano nel
 *    database: si torna indietro togliendo questo file o cambiando la data con il filtro.
 * 2. noindex, follow (canonical invariato) dalla pagina 2 in su di /blog, delle 7 categorie
 *    indicate e di tutte le pagine autore; /autore/redazione anche alla pagina 1.
 * 3. robots.txt: e' un file vero sul server; lo riscrive l'endpoint POST /betnero/v1/seo/robots
 *    (solo amministratori), con copia del vecchio.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BNC_SEO_GONE_CAT', 'pronostici' );
define( 'BNC_SEO_GONE_BEFORE', '2026-07-17 00:00:00' ); // ora del sito

/* ---------- 1. Articoli in 410 ---------- */

/** ID degli articoli da togliere. Calcolati una volta e tenuti 12 ore. */
function bnc_seo_gone_ids() {
	static $ids = null;
	if ( null !== $ids ) {
		return $ids;
	}
	$before = (string) apply_filters( 'bnc_seo_gone_before', BNC_SEO_GONE_BEFORE );
	$key    = 'bnc_seo_gone_' . md5( BNC_SEO_GONE_CAT . $before );
	$ids    = get_transient( $key );
	if ( ! is_array( $ids ) ) {
		$GLOBALS['bnc_seo_computing'] = true;
		$ids = get_posts( array(
			'post_type'        => 'post',
			'post_status'      => 'publish',
			'category_name'    => BNC_SEO_GONE_CAT,
			'date_query'       => array( array( 'before' => $before, 'inclusive' => false ) ),
			'fields'           => 'ids',
			'posts_per_page'   => -1,
			'no_found_rows'    => true,
			'suppress_filters' => true,
		) );
		unset( $GLOBALS['bnc_seo_computing'] );
		$ids = array_map( 'intval', (array) $ids );
		set_transient( $key, $ids, 12 * HOUR_IN_SECONDS );
	}
	return $ids;
}

function bnc_seo_is_gone( $post_id ) {
	return in_array( (int) $post_id, bnc_seo_gone_ids(), true );
}

// Esclusi da tutte le liste del sito (archivi, ricerca, feed, blocchi "Query"), non dall'amministrazione.
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! empty( $GLOBALS['bnc_seo_computing'] ) || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	if ( $q->is_singular() || $q->get( 'p' ) || $q->get( 'name' ) ) {
		return; // la pagina dell'articolo deve caricarsi, per poter rispondere 410
	}
	$pt = $q->get( 'post_type' );
	if ( $pt && 'any' !== $pt && ! in_array( 'post', (array) $pt, true ) ) {
		return;
	}
	$ids = bnc_seo_gone_ids();
	if ( $ids ) {
		$q->set( 'post__not_in', array_unique( array_merge( (array) $q->get( 'post__not_in' ), $ids ) ) );
	}
} );

// Articolo precedente / successivo.
foreach ( array( 'get_previous_post_where', 'get_next_post_where' ) as $bnc_hook ) {
	add_filter( $bnc_hook, function ( $where ) {
		$ids = bnc_seo_gone_ids();
		return $ids ? $where . ' AND p.ID NOT IN (' . implode( ',', array_map( 'intval', $ids ) ) . ')' : $where;
	} );
}

// Sitemap di Yoast.
add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', function ( $ids ) {
	return array_values( array_unique( array_merge( (array) $ids, bnc_seo_gone_ids() ) ) );
} );

add_action( 'template_redirect', function () {
	if ( is_singular( 'post' ) && bnc_seo_is_gone( get_queried_object_id() ) ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 410 );
		nocache_headers();
		header( 'X-Robots-Tag: noindex' );
		return;
	}
	// Vecchie paginazioni di Pronostici che non esistono piu': 410 invece di 404.
	if ( is_404() ) {
		$path = trim( (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH ), '/' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( preg_match( '#^blog/' . preg_quote( BNC_SEO_GONE_CAT, '#' ) . '/page/(\d+)$#', $path, $m ) && (int) $m[1] >= 2 && (int) $m[1] <= 105 ) {
			status_header( 410 );
			nocache_headers();
			header( 'X-Robots-Tag: noindex' );
		}
	}
}, 1 );

/* ---------- 2. noindex, follow sulle paginazioni ---------- */

function bnc_seo_noindex_here() {
	if ( is_admin() ) {
		return false;
	}
	$cats = (array) apply_filters( 'bnc_seo_noindex_categories', array( 'blog', 'pronostici', 'slot-machine', 'guida-al-casino', 'altri-giochi', 'guida-scommesse', 'roulette', 'blackjack' ) );
	if ( is_paged() && is_category( $cats ) ) {
		return true;
	}
	if ( is_author() ) {
		$a = get_queried_object();
		if ( is_paged() || ( $a && isset( $a->user_nicename ) && 'redazione' === $a->user_nicename ) ) {
			return true; // tutti gli autori dalla pagina 2 (anche quelli futuri), redazione anche alla 1
		}
	}
	return false;
}

// Solo il testo del meta robots: il canonical di Yoast resta quello della pagina stessa.
add_filter( 'wpseo_robots', function ( $robots ) {
	return bnc_seo_noindex_here() ? 'noindex, follow' : $robots;
}, 99 );

// Senza Yoast (o se Yoast non stampa il meta): stesso risultato con il meta robots di WordPress.
add_filter( 'wp_robots', function ( $r ) {
	if ( ! defined( 'WPSEO_VERSION' ) && bnc_seo_noindex_here() ) {
		$r['noindex'] = true;
		$r['follow']  = true;
		unset( $r['index'] );
	}
	return $r;
} );

/* ---------- 3. robots.txt ---------- */

function bnc_seo_robots_txt() {
	return "User-agent: *\n"
		. "Disallow: /wp-admin/\n"
		. "Allow: /wp-admin/admin-ajax.php\n"
		. "Disallow: /cdn-cgi/\n"
		. "\n# Blocca parametri nocache\n"
		. "Disallow: /*?nocache=\n"
		. "Disallow: /*&nocache=\n"
		. "\n# Blocca parametri jet_blog_ajax\n"
		. "Disallow: /*?jet_blog_ajax=\n"
		. "Disallow: /*&jet_blog_ajax=\n"
		. "\n# Blocca ricerca interna e feed\n"
		. "Disallow: /?s=\n"
		. "Disallow: /search/\n"
		. "Disallow: */feed/\n"
		. "\n# Blocca parametri di tracciamento esterni\n"
		. "Disallow: /*?utm_\n"
		. "Disallow: /*?fbclid=\n"
		. "Disallow: /*?gclid=\n"
		. "\n# Sitemap XML principale\n"
		. 'Sitemap: ' . home_url( '/sitemap_index.xml' ) . "\n";
}

// Se un giorno il file vero sparisse, WordPress servirebbe comunque questo.
add_filter( 'robots_txt', function () {
	return bnc_seo_robots_txt();
}, 99 );

add_action( 'rest_api_init', function () {
	$admin = function () {
		return current_user_can( 'manage_options' );
	};
	register_rest_route( 'betnero/v1', '/seo/stato', array(
		'methods'             => 'GET',
		'permission_callback' => $admin,
		'callback'            => function () {
			$ids  = bnc_seo_gone_ids();
			$file = ABSPATH . 'robots.txt';
			return array(
				'articoli_410' => count( $ids ),
				'esempi'       => array_map( 'get_permalink', array_slice( $ids, 0, 3 ) ),
				'prima_del'    => (string) apply_filters( 'bnc_seo_gone_before', BNC_SEO_GONE_BEFORE ),
				'robots_file'  => file_exists( $file ) ? (string) file_get_contents( $file ) : null, // phpcs:ignore WordPress.WP.AlternativeFunctions
				'robots_nuovo' => bnc_seo_robots_txt(),
			);
		},
	) );
	register_rest_route( 'betnero/v1', '/seo/robots', array(
		'methods'             => 'POST',
		'permission_callback' => $admin,
		'callback'            => function () {
			$file = ABSPATH . 'robots.txt';
			$bak  = '';
			if ( file_exists( $file ) ) {
				$bak = ABSPATH . 'robots.txt.bak-' . gmdate( 'Ymd-His' );
				if ( ! copy( $file, $bak ) ) {
					return new WP_Error( 'bnc_robots', 'Copia del vecchio robots.txt non riuscita', array( 'status' => 500 ) );
				}
			}
			$ok = file_put_contents( $file, bnc_seo_robots_txt() ); // phpcs:ignore WordPress.WP.AlternativeFunctions
			if ( false === $ok ) {
				return new WP_Error( 'bnc_robots', 'Scrittura di robots.txt non riuscita', array( 'status' => 500 ) );
			}
			return array( 'scritto' => true, 'copia' => $bak ? basename( $bak ) : null, 'contenuto' => bnc_seo_robots_txt() );
		},
	) );
} );

// Alla nuova versione: sitemap di Yoast rigenerate e cache delle pagine svuotata.
add_action( 'init', function () {
	if ( get_option( 'bnc_seo_ver' ) === BETNERO_CORE_VERSION ) {
		return;
	}
	update_option( 'bnc_seo_ver', BETNERO_CORE_VERSION, false );
	if ( class_exists( 'WPSEO_Sitemaps_Cache' ) && method_exists( 'WPSEO_Sitemaps_Cache', 'clear' ) ) {
		WPSEO_Sitemaps_Cache::clear();
	}
	if ( function_exists( 'bnc_schedule_page_cache_purge' ) ) {
		bnc_schedule_page_cache_purge();
	}
}, 99 );
