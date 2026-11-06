<?php
/**
 * Pulizia di CSS e JS sulle pagine a blocchi.
 *
 * Sulle pagine che NON sono costruite con Elementor non vengono caricati gli
 * stili e gli script di Elementor, Pro Elements, Crocoblock (Jet*), Happy Addons e
 * degli altri plugin che li servono solo alle pagine Elementor; non vengono
 * nemmeno stampati i vecchi popup di Elementor. Le pagine Elementor (es. le
 * 4 recensioni degli ex partner) restano come sono.
 *
 * Si spegne con il filtro: add_filter( 'bnc_clean_assets', '__return_false' );
 * Per vedere cosa toglie: ?bnc_assets=debug (solo amministratori).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * La pagina richiesta e' da ripulire?
 */
function bnc_clean_assets_on() {
	static $on = null;
	if ( null !== $on ) {
		return $on;
	}
	$on = false;
	if ( is_admin() || wp_doing_ajax() || is_customize_preview() || ! apply_filters( 'bnc_clean_assets', true ) ) {
		return $on;
	}
	if ( isset( $_GET['elementor-preview'] ) || isset( $_GET['preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return $on;
	}
	if ( is_singular() ) {
		$id = get_queried_object_id();
		if ( 'builder' === get_post_meta( $id, '_elementor_edit_mode', true ) ) {
			return $on; // Pagina fatta con Elementor: non si tocca.
		}
	}
	$on = true;
	return $on;
}

/**
 * Testo della pagina (per capire se usa uno shortcode di un plugin).
 */
function bnc_clean_assets_content() {
	if ( ! is_singular() ) {
		return '';
	}
	return (string) get_post_field( 'post_content', get_queried_object_id() );
}

/**
 * Cartelle dei plugin i cui file non servono sulle pagine a blocchi.
 * Se il testo della pagina usa uno shortcode del plugin, il plugin resta.
 */
function bnc_clean_assets_rules() {
	$content = bnc_clean_assets_content();
	$rules   = array(
		'/plugins/elementor/'                         => '',
		'/plugins/pro-elements/'                      => '',
		'/plugins/proelements-4.1.0/'                 => '',
		'/plugins/happy-elementor-addons/'            => '',
		'/plugins/sticky-header-effects-for-elementor/' => '',
		'/plugins/jet-blocks/'                        => '[jet',
		'/plugins/jet-blog/'                          => '[jet',
		'/plugins/jet-elements/'                      => '[jet',
		'/plugins/jet-engine/'                        => '[jet',
		'/plugins/jet-reviews/'                       => '[jet',
		'/plugins/jet-search/'                        => '[jet',
		'/plugins/jet-smart-filters/'                 => '[jet',
		'/plugins/jet-tabs/'                          => '[jet',
		'/plugins/jet-theme-core/'                    => '[jet',
		'/plugins/jet-tricks/'                        => '[jet',
		'/plugins/jet-popup/'                         => '[jet',
		'/plugins/metform/'                           => 'metform',
		'/plugins/wp-social/'                         => '[xs_',
		'/plugins/contact-form-7/'                    => '[contact-form-7',
		'/plugins/wp-ultimate-review/'                => '[wur',
		'/plugins/gutenkit-blocks-addon/'             => 'gutenkit',
		'/plugins/shortcodes-ultimate/'               => '[su_',
		'/uploads/elementor/'                         => '',
		'/uploads/jet-'                               => '[jet',
	);
	$out = array();
	foreach ( $rules as $path => $needle ) {
		if ( '' !== $needle && false !== stripos( $content, $needle ) ) {
			continue;
		}
		$out[] = $path;
	}
	return apply_filters( 'bnc_clean_assets_paths', $out );
}

function bnc_clean_assets_match( $src ) {
	if ( ! $src ) {
		return false;
	}
	foreach ( bnc_clean_assets_rules() as $path ) {
		if ( false !== strpos( $src, $path ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Toglie dalla coda stili e script dei plugin elencati.
 */
function bnc_clean_assets_dequeue() {
	if ( ! bnc_clean_assets_on() ) {
		return;
	}
	global $wp_styles, $wp_scripts;
	$removed = array( 'css' => array(), 'js' => array() );

	if ( $wp_styles instanceof WP_Styles ) {
		foreach ( (array) $wp_styles->queue as $h ) {
			$src = isset( $wp_styles->registered[ $h ] ) ? (string) $wp_styles->registered[ $h ]->src : '';
			if ( bnc_clean_assets_match( $src ) || 0 === strpos( $h, 'elementor-post-' ) || 0 === strpos( $h, 'elementor-' ) || 0 === strpos( $h, 'jet-' ) ) {
				wp_dequeue_style( $h );
				$removed['css'][] = $h;
			}
		}
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' ); // Serve solo alla barra di amministrazione.
		}
	}

	if ( $wp_scripts instanceof WP_Scripts ) {
		foreach ( (array) $wp_scripts->queue as $h ) {
			$src = isset( $wp_scripts->registered[ $h ] ) ? (string) $wp_scripts->registered[ $h ]->src : '';
			if ( bnc_clean_assets_match( $src ) || 0 === strpos( $h, 'elementor-' ) || 0 === strpos( $h, 'jet-' ) ) {
				wp_dequeue_script( $h );
				$removed['js'][] = $h;
			}
		}
		// jQuery solo se qualcosa rimasto in coda ne ha ancora bisogno.
		$needs = false;
		foreach ( (array) $wp_scripts->queue as $h ) {
			if ( in_array( $h, array( 'jquery', 'jquery-core', 'jquery-migrate' ), true ) ) {
				continue;
			}
			if ( bnc_clean_assets_needs( $wp_scripts, $h, 'jquery-core' ) ) {
				$needs = true;
				break;
			}
		}
		if ( ! $needs && ! is_user_logged_in() ) {
			foreach ( array( 'jquery', 'jquery-core', 'jquery-migrate' ) as $h ) {
				wp_dequeue_script( $h );
			}
			$removed['js'][] = 'jquery';
		}
	}
	$GLOBALS['bnc_clean_assets_removed'] = $removed;
}

/**
 * Lo script $h dipende (anche indirettamente) da $dep?
 */
function bnc_clean_assets_needs( $wp_scripts, $h, $dep, $depth = 0 ) {
	if ( $depth > 10 || ! isset( $wp_scripts->registered[ $h ] ) ) {
		return false;
	}
	foreach ( (array) $wp_scripts->registered[ $h ]->deps as $d ) {
		if ( $d === $dep || 'jquery' === $d || bnc_clean_assets_needs( $wp_scripts, $d, $dep, $depth + 1 ) ) {
			return true;
		}
	}
	return false;
}

// Dopo che tutti hanno messo in coda i loro file (anche quelli caricati nel footer).
add_action( 'wp_enqueue_scripts', 'bnc_clean_assets_dequeue', 9999 );
add_action( 'wp_print_styles', 'bnc_clean_assets_dequeue', 9999 );
add_action( 'wp_print_footer_scripts', 'bnc_clean_assets_dequeue', 1 );
add_action( 'wp_footer', 'bnc_clean_assets_dequeue', 1 );

/**
 * Niente popup di Elementor (vecchia barra in basso e ricerca) sulle pagine a blocchi:
 * la barra e la ricerca ora sono di Betnero Core e del tema.
 */
add_filter( 'elementor/theme/get_location_templates/template_id', function ( $template_id ) {
	if ( ! $template_id || ! bnc_clean_assets_on() ) {
		return $template_id;
	}
	return 'popup' === get_post_meta( $template_id, '_elementor_template_type', true ) ? 0 : $template_id;
} );

/**
 * Template Vue di JetReviews e altri stampati nel footer anche dove non servono.
 */
add_action( 'wp', function () {
	if ( ! bnc_clean_assets_on() || false !== stripos( bnc_clean_assets_content(), '[jet' ) ) {
		return;
	}
	foreach ( array( 'wp_footer', 'wp_print_footer_scripts' ) as $hook ) {
		global $wp_filter;
		if ( empty( $wp_filter[ $hook ] ) ) {
			continue;
		}
		foreach ( $wp_filter[ $hook ]->callbacks as $prio => $cbs ) {
			foreach ( $cbs as $key => $cb ) {
				$f     = $cb['function'];
				$class = is_array( $f ) && is_object( $f[0] ) ? get_class( $f[0] ) : ( is_array( $f ) && is_string( $f[0] ) ? $f[0] : '' );
				if ( $class && preg_match( '/^(Jet_|Jet\\\\|Elementor|ElementorPro|Happy|MetForm|XS_|Xs_)/', $class ) ) {
					remove_action( $hook, $f, $prio );
				}
			}
		}
	}
}, 99 );

/**
 * ?bnc_assets=debug: elenco di quello che e' stato tolto (solo amministratori).
 */
add_action( 'wp_footer', function () {
	if ( ! isset( $_GET['bnc_assets'] ) || ! current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification
		return;
	}
	$r = isset( $GLOBALS['bnc_clean_assets_removed'] ) ? $GLOBALS['bnc_clean_assets_removed'] : array();
	echo "\n<!-- bnc_assets " . esc_html( wp_json_encode( $r ) ) . " -->\n";
}, 9999 );
