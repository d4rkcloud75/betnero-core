<?php
/**
 * Blocchi della home:
 *
 *   betnero/classifica  classifica a schede con selettore Casino/Scommesse e filtri
 *   betnero/podio       i primi operatori in schede compatte (per la hero)
 *   betnero/numeri      i numeri del sito (operatori, recensioni, pronostici, guide)
 *
 * I dati vengono dagli Operatori, come tabelle e toplist.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'init', function () {
	$v = BETNERO_CORE_VERSION;
	wp_register_style( 'betnero-home', BETNERO_CORE_URL . 'assets/css/home.css', array(), $v );
	wp_register_script( 'betnero-classifica', BETNERO_CORE_URL . 'assets/js/classifica.js', array(), $v, array( 'in_footer' => true, 'strategy' => 'defer' ) );

	$common = array(
		'editor_script' => 'betnero-blocks-editor',
		'style'         => 'betnero-home',
		'category'      => 'betnero',
		'supports'      => array( 'html' => false, 'align' => array( 'wide', 'full' ) ),
	);

	register_block_type( 'betnero/classifica', $common + array(
		'title'           => 'Classifica a schede',
		'attributes'      => array(
			'eyebrow'      => array( 'type' => 'string', 'default' => 'Classifica Betnero' ),
			'titleCasino'  => array( 'type' => 'string', 'default' => 'I migliori casinò online ADM' ),
			'titleSport'   => array( 'type' => 'string', 'default' => 'I migliori bookmaker ADM' ),
			'description'  => array( 'type' => 'string', 'default' => "Ordinati per voto complessivo: affidabilità, bonus reali dopo i requisiti, velocità dei prelievi, qualità dell'assistenza." ),
			'limitCasino'  => array( 'type' => 'number', 'default' => 0 ),
			'limitSport'   => array( 'type' => 'number', 'default' => 0 ),
			'showFilters'  => array( 'type' => 'boolean', 'default' => true ),
			'align'        => array( 'type' => 'string' ),
			'className'    => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_render_classifica',
	) );

	register_block_type( 'betnero/podio', $common + array(
		'title'           => 'Podio operatori',
		'attributes'      => array(
			'type'      => array( 'type' => 'string', 'default' => 'casino' ),
			'limit'     => array( 'type' => 'number', 'default' => 3 ),
			'title'     => array( 'type' => 'string', 'default' => '' ),
			'align'     => array( 'type' => 'string' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_render_podio',
	) );

	register_block_type( 'betnero/numeri', $common + array(
		'title'           => 'Numeri del sito',
		'attributes'      => array(
			'align'     => array( 'type' => 'string' ),
			'className' => array( 'type' => 'string' ),
		),
		'render_callback' => 'bnc_render_numeri',
	) );
} );

/** Giudizio a parole dal voto. */
function bnc_rating_label( $r ) {
	$r = (float) $r;
	if ( $r >= 5 ) {
		return 'Eccellente';
	}
	if ( $r >= 4.5 ) {
		return 'Ottimo';
	}
	if ( $r >= 4 ) {
		return 'Molto buono';
	}
	return 'Buono';
}

function bnc_is_yes( $v ) {
	return in_array( strtolower( trim( (string) $v ) ), array( 'sì', 'si', 'yes', '1', 'true' ), true );
}

/** Una scheda della classifica. */
function bnc_classifica_card( $op, $n ) {
	$ok     = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>';
	$casino = 'casino' === $op['tipo'];
	$nd     = $casino && bnc_is_yes( $op['nd'] );
	$spid   = $casino && bnc_is_yes( $op['spid'] );
	$badges = 1 === $n ? '<span class="bnk-badge bnk-feat">Scelta della redazione</span>' : '';
	if ( $casino ) {
		$badges .= $nd ? '<span class="bnk-badge bnk-ok">' . $ok . 'Senza deposito</span>' : '';
		$badges .= $spid ? '<span class="bnk-badge bnk-ok">' . $ok . 'SPID</span>' : '';
	} elseif ( bnc_is_yes( $op['multipla'] ) ) {
		$badges .= '<span class="bnk-badge bnk-ok">' . $ok . 'Bonus multipla</span>';
	}
	$r   = max( 0, min( 5, (float) str_replace( ',', '.', (string) $op['rating'] ) ) );
	$rev = $op['review_url'] ? '<a class="bnk-rev" href="' . esc_url( $op['review_url'] ) . '"><span class="bnk-d">Leggi la recensione</span><span class="bnk-m">Recensione</span></a>' : '';

	$h  = '<article class="bnk-op' . ( 1 === $n ? ' bnk-top' : '' ) . '" data-nd="' . ( $nd ? 1 : 0 ) . '" data-spid="' . ( $spid ? 1 : 0 ) . '">';
	$h .= '<div class="bnk-main">';
	$h .= '<span class="bnk-rank">' . (int) $n . '</span>';
	$h .= '<span class="bnk-logo">' . bnc_logo( $op ) . '</span>';
	$h .= '<div class="bnk-bonus">' . bnc_bonus_html( $op['bonus'] ) . ( $badges ? '<div class="bnk-badges">' . $badges . '</div>' : '' ) . '</div>';
	if ( $r > 0 ) {
		$h .= '<div class="bnk-score"><div class="bnk-score-top"><b>' . esc_html( number_format_i18n( $r, 1 ) ) . '</b><span>/ 5 · ' . esc_html( bnc_rating_label( $r ) ) . '</span></div>';
		$h .= '<div class="bnk-bar" role="img" aria-label="' . esc_attr( sprintf( 'Voto %s su 5', number_format_i18n( $r, 1 ) ) ) . '"><i style="width:' . ( $r * 20 ) . '%"></i></div><small>Voto Betnero</small></div>';
	} else {
		$h .= '<div class="bnk-score"></div>';
	}
	$h .= '<div class="bnk-cta">' . bnc_go_button( $op, 'Vai al sito', 'bnk-go' ) . $rev . '</div>';
	$h .= '</div>';
	if ( $op['parere'] ) {
		$h .= '<div class="bnk-foot"><div class="bnk-parere">' . wp_kses_post( $op['parere'] ) . '</div><span class="bnk-tc">18+ · T&amp;C</span></div>';
	}
	return $h . '</article>';
}

function bnc_render_classifica( $a ) {
	wp_enqueue_style( 'betnero-tables' );
	wp_enqueue_script( 'betnero-classifica' );
	$lists = array(
		'casino'    => bnc_operators( 'casino' ),
		'scommesse' => bnc_operators( 'scommesse' ),
	);
	$limits = array(
		'casino'    => (int) ( $a['limitCasino'] ?? 0 ),
		'scommesse' => (int) ( $a['limitSport'] ?? 0 ),
	);
	$titles = array(
		'casino'    => (string) ( $a['titleCasino'] ?? '' ),
		'scommesse' => (string) ( $a['titleSport'] ?? '' ),
	);
	$align = ! empty( $a['align'] ) ? ' align' . $a['align'] : '';
	$cls   = ! empty( $a['className'] ) ? ' ' . $a['className'] : '';
	$uid   = 'bnk-' . wp_unique_id();

	$h  = '<div class="bnk' . esc_attr( $align . $cls ) . '" data-bnk>';
	$h .= '<div class="bnk-head"><div>';
	$h .= ! empty( $a['eyebrow'] ) ? '<span class="bnk-eyebrow">' . esc_html( $a['eyebrow'] ) . '</span>' : '';
	$h .= '<h2 class="bnk-title" data-casino="' . esc_attr( $titles['casino'] ) . '" data-scommesse="' . esc_attr( $titles['scommesse'] ) . '">' . esc_html( $titles['casino'] ) . '</h2>';
	$h .= ! empty( $a['description'] ) ? '<p class="bnk-desc">' . esc_html( $a['description'] ) . '</p>' : '';
	$h .= '</div>';
	$h .= '<div class="bnk-tabs" role="tablist" aria-label="Tipo di classifica">';
	$h .= '<button type="button" class="bnk-tab" role="tab" id="' . $uid . '-t-casino" aria-controls="' . $uid . '-casino" aria-selected="true" data-tab="casino">Casinò</button>';
	$h .= '<button type="button" class="bnk-tab" role="tab" id="' . $uid . '-t-scommesse" aria-controls="' . $uid . '-scommesse" aria-selected="false" tabindex="-1" data-tab="scommesse">Scommesse</button>';
	$h .= '</div></div>';

	if ( ! empty( $a['showFilters'] ) ) {
		$h .= '<div class="bnk-filters" data-bnk-filters><span class="bnk-flbl">Filtra:</span>';
		$h .= '<button type="button" class="bnk-chip" aria-pressed="false" data-f="nd">Bonus senza deposito</button>';
		$h .= '<button type="button" class="bnk-chip" aria-pressed="false" data-f="spid">Registrazione SPID</button></div>';
	}

	foreach ( $lists as $type => $ops ) {
		if ( $limits[ $type ] > 0 ) {
			$ops = array_slice( $ops, 0, $limits[ $type ] );
		}
		$hidden = 'casino' === $type ? '' : ' hidden';
		$h     .= '<div class="bnk-list" role="tabpanel" id="' . $uid . '-' . $type . '" aria-labelledby="' . $uid . '-t-' . $type . '" data-bnk-list="' . $type . '"' . $hidden . '>';
		foreach ( array_values( $ops ) as $i => $op ) {
			$h .= bnc_classifica_card( $op, $i + 1 );
		}
		$h .= '<p class="bnk-empty" hidden>Nessun operatore corrisponde ai filtri.</p></div>';
	}
	$h .= '<p class="bnk-legal">18+ · Gioca responsabilmente · I bonus sono soggetti ai termini e condizioni dell\'operatore · Betnero riceve una commissione dagli operatori, senza influenza sul voto.</p>';
	return $h . '</div>';
}

function bnc_render_podio( $a ) {
	$type  = ( $a['type'] ?? 'casino' ) === 'scommesse' ? 'scommesse' : 'casino';
	$ops   = array_slice( bnc_operators( $type ), 0, max( 1, (int) ( $a['limit'] ?? 3 ) ) );
	$title = (string) ( $a['title'] ?? '' );
	if ( '' === $title ) {
		$mesi  = array( '', 'gennaio', 'febbraio', 'marzo', 'aprile', 'maggio', 'giugno', 'luglio', 'agosto', 'settembre', 'ottobre', 'novembre', 'dicembre' );
		$title = ( 'casino' === $type ? 'Podio casinò di ' : 'Podio bookmaker di ' ) . $mesi[ (int) wp_date( 'n' ) ];
	}
	$cls = ! empty( $a['className'] ) ? ' ' . $a['className'] : '';
	$h   = '<aside class="bnp' . esc_attr( $cls ) . '" aria-label="' . esc_attr( $title ) . '">';
	$h  .= '<div class="bnp-head"><span class="bnp-title">' . esc_html( $title ) . '</span><small>Voto Betnero</small></div>';
	foreach ( array_values( $ops ) as $i => $op ) {
		$big   = '';
		$small = '';
		if ( preg_match_all( '#<(p|h[1-6])[^>]*>(.*?)</\1>#is', $op['bonus'], $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $b ) {
				$t = trim( wp_strip_all_tags( $b[2] ) );
				if ( '' === $t ) {
					continue;
				}
				if ( 'p' !== strtolower( $b[1] ) && '' === $big ) {
					$big = $t;
				} elseif ( '' === $small ) {
					$small = $t;
				}
			}
		}
		if ( '' === $big ) {
			$big = bnc_bonus_text( $op['bonus'] );
		}
		$h .= '<div class="bnp-item"><span class="bnp-rank">' . ( $i + 1 ) . '</span><span class="bnp-logo">' . bnc_logo( $op, 0 === $i ) . '</span>';
		$h .= '<span class="bnp-bonus"><b>' . esc_html( $big ) . '</b>' . ( $small ? '<span>' . esc_html( $small ) . '</span>' : '' ) . '</span>';
		$h .= bnc_go_button( $op, 'Vai al sito', 'bnp-go' ) . '</div>';
	}
	$h .= '<div class="bnp-foot">18+ · T&amp;C applicati · Link di affiliazione</div>';
	return $h . '</aside>';
}

/** Numeri del sito, ricalcolati al massimo una volta all'ora. */
function bnc_site_numbers() {
	$n = get_transient( 'bnc_site_numbers' );
	if ( is_array( $n ) ) {
		return $n;
	}
	$ops     = get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ) );
	$reviews = array();
	foreach ( $ops as $id ) {
		$p = (int) bnc_get( $id, 'review_page' );
		if ( $p && 'publish' === get_post_status( $p ) ) {
			$reviews[ $p ] = true;
		}
	}
	$pron    = get_term_by( 'slug', 'pronostici', 'category' );
	$academy = get_page_by_path( 'academy' );
	$guides  = $academy ? count( get_pages( array( 'child_of' => $academy->ID, 'post_status' => 'publish' ) ) ) : 0;
	$n       = array(
		'operatori'  => count( $ops ),
		'recensioni' => count( $reviews ),
		'pronostici' => $pron ? (int) $pron->count : 0,
		'guide'      => $guides,
	);
	set_transient( 'bnc_site_numbers', $n, HOUR_IN_SECONDS );
	return $n;
}

function bnc_render_numeri( $a ) {
	$n     = bnc_site_numbers();
	$items = array(
		'operatori'  => 'operatori ADM verificati',
		'recensioni' => 'recensioni approfondite',
		'pronostici' => 'pronostici pubblicati',
		'guide'      => 'guide in Academy',
	);
	$cls = ! empty( $a['className'] ) ? ' ' . $a['className'] : '';
	$h   = '<div class="bnn' . esc_attr( $cls ) . '">';
	foreach ( $items as $k => $label ) {
		if ( empty( $n[ $k ] ) ) {
			continue;
		}
		$h .= '<div class="bnn-item"><b>' . esc_html( number_format_i18n( $n[ $k ] ) ) . '</b><span>' . esc_html( $label ) . '</span></div>';
	}
	return $h . '</div>';
}

// I numeri cambiano quando cambiano operatori e articoli.
add_action( 'save_post', function () {
	delete_transient( 'bnc_site_numbers' );
} );

/*
 * Velox Optimizer ritarda gli script fino alla prima interazione: il primo
 * clic sul selettore Casino/Scommesse andrebbe perso. Lo script e' piccolo
 * (meno di 2 KB, caricato in defer), quindi lo escludiamo dal ritardo.
 */
add_filter( 'velox_delay_js_extra_exclusions', function ( $list ) {
	$list   = (array) $list;
	$list[] = 'classifica.js';
	$list[] = 'classifica.min.js';
	return $list;
} );
