<?php
/**
 * HTML di tabelle, toplist, scheda operatore e barra in basso.
 *
 * Classi bnt-* (tabelle e toplist), bns-* (scheda), bnb-* (barra):
 * da tenere nella safelist di WP Rocket (Remove Unused CSS).
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ---------- Pezzi comuni ---------- */

/**
 * Logo dell'operatore. $eager = true per i loghi in cima alla pagina
 * (testata della recensione): caricati subito invece che in differita.
 */
function bnc_logo( $op, $eager = false ) {
	// $eager: true = subito e con priorita' alta; 'subito' = subito ma senza scavalcare l'immagine principale (LCP).
	if ( 'subito' === $eager ) {
		$load = array( 'loading' => 'eager', 'decoding' => 'async' );
	} else {
		$load = $eager ? array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) : array( 'loading' => 'lazy', 'decoding' => 'async' );
	}
	if ( $op['logo_id'] ) {
		return wp_get_attachment_image( $op['logo_id'], 'medium', false, array_merge( array( 'alt' => $op['name'] ), $load ) );
	}
	if ( $op['logo_url'] ) {
		$attrs = '';
		foreach ( $load as $k => $v ) {
			$attrs .= ' ' . $k . '="' . esc_attr( $v ) . '"';
		}
		return '<img src="' . esc_url( $op['logo_url'] ) . '" alt="' . esc_attr( $op['name'] ) . '"' . $attrs . '>';
	}
	return '<span class="bnt-logo-text">' . esc_html( $op['name'] ) . '</span>';
}

/**
 * Testo bonus: righe <p> piccole, titoli <h1-h6> grandi.
 */
function bnc_bonus_html( $html ) {
	$out = '';
	if ( preg_match_all( '#<(p|h[1-6])[^>]*>(.*?)</\1>#is', (string) $html, $m, PREG_SET_ORDER ) ) {
		foreach ( $m as $b ) {
			$txt = trim( wp_strip_all_tags( $b[2] ) );
			if ( '' === $txt ) {
				continue;
			}
			$cls  = ( 'p' === strtolower( $b[1] ) ) ? 'bnt-small' : 'bnt-big';
			$out .= '<div class="' . $cls . '">' . esc_html( $txt ) . '</div>';
		}
	}
	if ( '' === $out ) {
		$txt = trim( wp_strip_all_tags( (string) $html ) );
		$out = $txt ? '<div class="bnt-big">' . esc_html( $txt ) . '</div>' : '';
	}
	return $out;
}

/** Bonus su una riga di testo (barra in basso). */
function bnc_bonus_text( $html ) {
	return trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( str_replace( array( '</p>', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '<br>', '<br />' ), ' ', (string) $html ) ) ) );
}

function bnc_stars( $val ) {
	$v   = max( 0, min( 5, (float) str_replace( ',', '.', (string) $val ) ) );
	$pct = round( $v / 5 * 100 );
	return '<span class="bnt-stars" role="img" aria-label="' . esc_attr( sprintf( 'Valutazione %s su 5', number_format_i18n( $v, 1 ) ) ) . '"><span class="bnt-stars-bg">★★★★★</span><span class="bnt-stars-fg" style="width:' . $pct . '%">★★★★★</span></span>';
}

/**
 * Operatore collegato (la versione sport di un casino, o viceversa), se pubblicato e con link.
 */
function bnc_linked_operator( $op ) {
	if ( empty( $op['collegato'] ) || 'publish' !== get_post_status( $op['collegato'] ) ) {
		return null;
	}
	$l = bnc_operator( $op['collegato'] );
	return ( $l && ! empty( $l['go_url'] ) ) ? $l : null;
}

function bnc_tipo_label( $tipo ) {
	return 'casino' === $tipo ? 'Casinò' : 'Sport';
}

function bnc_go_button( $op, $label = 'Vai al sito', $class = 'bnt-btn bnt-go' ) {
	if ( ! $op['go_url'] ) {
		return '';
	}
	$target = $op['go_blank'] ? ' target="_blank"' : '';
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $op['go_url'] ) . '"' . $target . ' rel="nofollow sponsored noopener">' . esc_html( $label ) . '</a>';
}

function bnc_review_button( $op ) {
	return $op['review_url'] ? '<a class="bnt-btn bnt-rev" href="' . esc_url( $op['review_url'] ) . '">Recensione</a>' : '';
}

function bnc_yn( $v ) {
	$v = trim( (string) $v );
	return '' === $v ? '-' : esc_html( $v );
}

function bnc_adm_line() {
	return '<div class="bnt-adm">18+ | Gioca responsabilmente | Solo operatori con concessione ADM</div>';
}

/* ---------- Tabelle ---------- */

/**
 * Filtri (nel browser, senza ricaricare): opzioni prese dalla definizione
 * dei campi, quindi se aggiungi un'opzione al campo compare anche nel filtro.
 */
function bnc_filters_html( $type ) {
	$f = bnc_fields();
	if ( 'casino' === $type ) {
		$filters = array(
			'welcome' => array( 'Bonus di benvenuto', $f['welcome']['options'] ),
			'nd'      => array( 'Bonus senza deposito', array( 'Sì' => 'Sì', 'No' => 'No' ) ),
			'tipo'    => array( 'Tipologia bonus', $f['tipologia']['options'] ),
			'pay'     => array( 'Pagamenti', $f['pagamenti']['options'] ),
		);
	} else {
		$filters = array(
			'multipla' => array( 'Bonus multipla', array( 'Sì' => 'Sì', 'No' => 'No' ) ),
		);
	}
	$h = '<div class="bnt-filters">';
	foreach ( $filters as $key => $fl ) {
		$h .= '<label class="bnt-filter"><span>' . esc_html( $fl[0] ) . '</span><select data-bnt-filter="' . esc_attr( $key ) . '"><option value="">Tutti</option>';
		foreach ( $fl[1] as $val => $label ) {
			$h .= '<option value="' . esc_attr( $val ) . '">' . esc_html( $label ) . '</option>';
		}
		$h .= '</select></label>';
	}
	$h .= '<button type="button" class="bnt-reset" data-bnt-reset hidden>Rimuovi filtri</button></div>';
	return $h;
}

function bnc_cols_html( $type, $short = false ) {
	if ( 'casino' === $type ) {
		return '<div class="bnt-cols" aria-hidden="true"><span>' . ( $short ? 'Casinò' : 'Casinò online' ) . '</span><span>Welcome bonus</span><span>Senza deposito</span><span>' . ( $short ? 'SPID' : 'Registrazione SPID' ) . '</span><span>Valutazione</span><span></span></div>';
	}
	return '<div class="bnt-cols" aria-hidden="true"><span>Bookmaker</span><span>Bonus sport</span><span>Bonus multipla</span><span>Valutazione</span><span></span></div>';
}

function bnc_row_cells( $op, $n ) {
	$h  = '<div class="bnt-logo"><b class="bnt-rank">' . (int) $n . '</b>' . bnc_logo( $op ) . '</div>';
	$h .= '<div class="bnt-bonus">' . bnc_bonus_html( $op['bonus'] ) . '</div>';
	if ( 'casino' === $op['tipo'] ) {
		$h .= '<div class="bnt-yn"><span class="bnt-lbl">Senza deposito: </span>' . bnc_yn( $op['nd'] ) . '</div>';
		$h .= '<div class="bnt-yn"><span class="bnt-lbl">SPID: </span>' . bnc_yn( $op['spid'] ) . '</div>';
	} else {
		$h .= '<div class="bnt-yn"><span class="bnt-lbl">Bonus multipla: </span>' . bnc_yn( $op['multipla'] ) . '</div>';
	}
	$h .= '<div class="bnt-rate">' . bnc_stars( $op['rating'] ) . '</div>';
	$h .= '<div class="bnt-btns">' . bnc_go_button( $op ) . bnc_review_button( $op ) . '</div>';
	return $h;
}

function bnc_render_table( $type, $attributes ) {
	$ops = bnc_operators( $type );
	if ( empty( $ops ) ) {
		return '';
	}
	$limit = isset( $attributes['limit'] ) ? (int) $attributes['limit'] : 0;
	if ( $limit > 0 ) {
		$ops = array_slice( $ops, 0, $limit );
	}
	$show_filters = ! empty( $attributes['showFilters'] );
	$show_parere  = ! isset( $attributes['showParere'] ) || $attributes['showParere'];
	$is_casino    = ( 'casino' === $type );

	wp_enqueue_style( 'betnero-tables' );
	if ( $show_filters ) {
		wp_enqueue_script( 'betnero-tables' );
	}

	$h  = '<div class="bnt-table bnt-' . esc_attr( $type ) . '" data-bnt-table>';
	$h .= $show_filters ? bnc_filters_html( $type ) : '';
	$h .= bnc_cols_html( $type );
	$h .= '<div class="bnt-rows">';
	$n  = 0;
	foreach ( $ops as $op ) {
		$n++;
		if ( $is_casino ) {
			$data  = ' data-welcome="' . esc_attr( implode( ' ', $op['welcome'] ) ) . '"';
			$data .= ' data-nd="' . esc_attr( trim( $op['nd'] ) ) . '"';
			$data .= ' data-tipo="' . esc_attr( implode( ' ', $op['tipologia'] ) ) . '"';
			$data .= ' data-pay="' . esc_attr( implode( ' ', $op['pagamenti'] ) ) . '"';
		} else {
			$data = ' data-multipla="' . esc_attr( trim( $op['multipla'] ) ) . '"';
		}
		$h .= '<div class="bnt-row"' . $data . '>' . bnc_row_cells( $op, $n );
		if ( $show_parere && $op['parere'] ) {
			$h .= '<div class="bnt-parere">' . wp_kses_post( $op['parere'] ) . '</div>';
		}
		$h .= '</div>';
	}
	$h .= '</div>';
	$h .= '<p class="bnt-empty" hidden>Nessun operatore corrisponde ai filtri. Rimuovi uno o più filtri.</p>';
	$h .= bnc_adm_line() . '</div>';
	return $h;
}

/**
 * Toplist compatta. $attributes['layout']: "inline" (nel testo) o "side" (sidebar).
 */
function bnc_render_toplist( $attributes ) {
	$type      = ( isset( $attributes['type'] ) && 'scommesse' === $attributes['type'] ) ? 'scommesse' : 'casino';
	$limit     = isset( $attributes['limit'] ) ? max( 1, (int) $attributes['limit'] ) : 5;
	$layout    = ( isset( $attributes['layout'] ) && 'side' === $attributes['layout'] ) ? 'side' : 'inline';
	$is_casino = ( 'casino' === $type );
	$ops       = array_slice( bnc_operators( $type ), 0, $limit );
	if ( empty( $ops ) ) {
		return '';
	}
	wp_enqueue_style( 'betnero-tables' );

	$title   = ! empty( $attributes['title'] ) ? $attributes['title'] : ( $is_casino ? 'I migliori casino online' : 'I migliori bookmaker' );
	$all_url = $is_casino ? home_url( '/casino.html' ) : home_url( '/scommesse-sportive.html' );
	$all_txt = $is_casino ? 'Vedi tutti i casino' : 'Vedi tutti i bookmaker';

	$h  = '<div class="bnt-table bnt-toplist bnt-' . esc_attr( $type ) . ( 'side' === $layout ? ' bnt-side' : '' ) . '">';
	$h .= '<div class="bnt-head"><span class="bnt-title">' . esc_html( $title ) . '</span><a class="bnt-all" href="' . esc_url( $all_url ) . '">' . esc_html( $all_txt ) . ' &rsaquo;</a></div>';
	$h .= 'side' === $layout ? '' : bnc_cols_html( $type, true );
	$h .= '<div class="bnt-rows' . ( 'side' === $layout ? '' : ' bnt-scroll' ) . '">';
	$n  = 0;
	foreach ( $ops as $op ) {
		$n++;
		$h .= '<div class="bnt-row">' . bnc_row_cells( $op, $n ) . '</div>';
	}
	$h .= '</div>';
	$h .= 'side' === $layout ? '' : '<div class="bnt-hint">Scorri per vedere gli altri &rarr;</div>';
	$h .= bnc_adm_line() . '</div>';
	return $h;
}

/* ---------- Scheda operatore (pagine recensione) ---------- */

/**
 * Sezioni della scheda: campo => titolo (vuoto = il testo ha gia' i suoi titoli).
 */
function bnc_review_sections() {
	return array(
		'recensione'  => 'La nostra recensione',
		'procontro'   => '',
		'panoramica'  => 'Panoramica',
		'i_bonus'     => '',
		'palinsesto'  => '',
		'metodi'      => '',
		'esperienza'  => '',
		'faq'         => '',
		'conclusione' => '',
	);
}

function bnc_rich( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return '';
	}
	if ( false === stripos( $html, '<p' ) && false === stripos( $html, '<h' ) && false === stripos( $html, '<ul' ) ) {
		$html = wpautop( $html );
	}
	return do_shortcode( wp_kses_post( $html ) );
}

/**
 * Galleria di immagini. $eager_first = true per la prima galleria della
 * scheda: su mobile e' in cima alla pagina, quindi la prima immagine si
 * carica subito (e' spesso l'elemento piu' grande visibile, cioe' l'LCP).
 */
function bnc_gallery( $ids, $title, $eager_first = false, $name = '' ) {
	if ( empty( $ids ) ) {
		return '';
	}
	wp_enqueue_script( 'betnero-lightbox' );
	$h = '<figure class="bns-gallery"><figcaption>' . esc_html( $title ) . '</figcaption><div class="bns-gallery-grid">';
	foreach ( array_values( $ids ) as $i => $id ) {
		$full  = wp_get_attachment_image_url( (int) $id, 'full' );
		$load  = ( $eager_first && 0 === $i ) ? array( 'loading' => 'eager', 'fetchpriority' => 'high', 'decoding' => 'async' ) : array( 'loading' => 'lazy', 'decoding' => 'async' );
		// Testo alternativo e etichetta del link: servono a lettori di schermo e agenti AI.
		$alt   = trim( (string) get_post_meta( (int) $id, '_wp_attachment_image_alt', true ) );
		$label = ( '' !== $name ? $name . ', ' : '' ) . strtolower( $title ) . ', immagine ' . ( $i + 1 );
		if ( '' === $alt ) {
			$load['alt'] = $label;
		}
		$h .= '<a href="' . esc_url( (string) $full ) . '" data-bn-lb data-elementor-open-lightbox="no" aria-label="' . esc_attr( 'Ingrandisci ' . $label ) . '">' . wp_get_attachment_image( (int) $id, 'medium', false, $load ) . '</a>';
	}
	return $h . '</div></figure>';
}

/**
 * @param array $attributes operatore (0 = quello della pagina), parte (tutto|testata|testi).
 */
function bnc_render_scheda( $attributes ) {
	$id = isset( $attributes['operatore'] ) ? (int) $attributes['operatore'] : 0;
	if ( ! $id ) {
		$id = bnc_operator_for_page( get_the_ID() );
	}
	if ( ! $id || BNC_POST_TYPE !== get_post_type( $id ) ) {
		return current_user_can( 'edit_posts' ) ? '<p class="bns-empty">Scheda operatore: scegli un operatore nel blocco (oppure collega questa pagina come "Pagina recensione" dell\'operatore).</p>' : '';
	}
	$parte = isset( $attributes['parte'] ) ? $attributes['parte'] : 'tutto';
	$op    = bnc_operator( $id );
	wp_enqueue_style( 'betnero-tables' );
	wp_enqueue_style( 'betnero-scheda' );

	$h = '<div class="bns-scheda bns-' . esc_attr( $op['tipo'] ) . '">';

	if ( 'testi' !== $parte ) {
		$titolo = (string) bnc_get( $id, 'titolo' );
		$titolo = $titolo ? wp_strip_all_tags( $titolo ) : 'Recensione ' . preg_replace( '/\s+(Casino|Casinò|Scommesse|Sport)$/i', '', $op['name'] );
		$h     .= '<section class="bns-hero">';
		$h     .= '<div class="bns-logo">' . bnc_logo( $op, 'subito' ) . '</div>';
		// La prima scheda della pagina porta l'H1, se il testo della pagina non ne ha gia' uno.
		static $h1_done = false;
		$own_h1 = false !== stripos( (string) get_post_field( 'post_content', get_the_ID() ), '<h1' );
		$tag    = ( ! $h1_done && ! $own_h1 && is_singular() ) ? 'h1' : 'div';
		$h1_done = $h1_done || 'h1' === $tag;
		$h     .= '<div class="bns-main"><' . $tag . ' class="bns-title">' . esc_html( $titolo ) . '</' . $tag . '>';
		$linked = bnc_linked_operator( $op );
		if ( $linked ) {
			// Operatore con bonus casino e bonus sport: si vedono entrambi (come in produzione).
			$h .= '<div class="bns-bonus bns-bonus-duo">';
			foreach ( array( $op, $linked ) as $o ) {
				$h .= '<div class="bns-bonus-item"><span class="bns-bonus-tipo bns-bonus-tipo--' . esc_attr( $o['tipo'] ) . '">Bonus ' . esc_html( bnc_tipo_label( $o['tipo'] ) ) . '</span>' . bnc_bonus_html( $o['bonus'] ) . '</div>';
			}
			$h .= '</div>';
		} else {
			$h .= '<div class="bns-bonus">' . bnc_bonus_html( $op['bonus'] ) . '</div>';
		}
		$h     .= '<ul class="bns-facts">';
		if ( 'casino' === $op['tipo'] ) {
			$h .= '<li><span>Bonus senza deposito</span><b>' . bnc_yn( $op['nd'] ) . '</b></li>';
			$h .= '<li><span>Registrazione SPID</span><b>' . bnc_yn( $op['spid'] ) . '</b></li>';
		} else {
			$h .= '<li><span>Bonus multipla</span><b>' . bnc_yn( $op['multipla'] ) . '</b></li>';
		}
		$h .= '<li><span>Valutazione</span><b>' . bnc_stars( $op['rating'] ) . ' ' . esc_html( number_format_i18n( (float) $op['rating'], 1 ) ) . '</b></li>';
		$h .= '</ul>';
		$riepilogo = (string) bnc_get( $id, 'riepilogo' );
		$h        .= $riepilogo ? '<div class="bns-riepilogo">' . bnc_rich( $riepilogo ) . '</div>' : '';
		$h        .= '</div>';
		$cta = $linked
			? bnc_go_button( $op, 'Bonus ' . bnc_tipo_label( $op['tipo'] ), 'bnt-btn bnt-go bns-go' . ( 'casino' === $op['tipo'] ? '' : ' bns-go-alt' ) ) . bnc_go_button( $linked, 'Bonus ' . bnc_tipo_label( $linked['tipo'] ), 'bnt-btn bnt-go bns-go' . ( 'casino' === $linked['tipo'] ? '' : ' bns-go-alt' ) )
			: bnc_go_button( $op, 'Vai al sito', 'bnt-btn bnt-go bns-go' );
		$h  .= '<div class="bns-cta">' . $cta . '<div class="bns-adm">18+ | Gioca responsabilmente</div></div>';
		$h        .= '</section>';

		$gname = preg_replace( '/\s+(Casino|Casinò|Scommesse|Sport)$/iu', '', $op['name'] );
		$gal   = bnc_gallery( bnc_get( $id, 'gallery_sito' ), 'Il sito web', true, $gname ) . bnc_gallery( bnc_get( $id, 'gallery_app' ), 'App', false, $gname );
		$h  .= $gal ? '<div class="bns-galleries">' . $gal . '</div>' : '';
	}

	if ( 'testata' !== $parte ) {
		foreach ( bnc_review_sections() as $key => $title ) {
			if ( 'procontro' === $key ) {
				$pro    = bnc_rich( bnc_get( $id, 'pro' ) );
				$contro = bnc_rich( bnc_get( $id, 'contro' ) );
				if ( $pro || $contro ) {
					$h .= '<div class="bns-procontro">';
					$h .= $pro ? '<div class="bns-pro"><h3>Punti di forza</h3>' . $pro . '</div>' : '';
					$h .= $contro ? '<div class="bns-contro"><h3>Difetti</h3>' . $contro . '</div>' : '';
					$h .= '</div>';
				}
				continue;
			}
			$body = bnc_rich( bnc_get( $id, $key ) );
			if ( ! $body ) {
				continue;
			}
			$h .= '<section class="bns-section bns-' . esc_attr( $key ) . '">' . ( $title ? '<h2>' . esc_html( $title ) . '</h2>' : '' ) . $body . '</section>';
		}
	}

	return $h . '</div>';
}

/* ---------- Barra in basso (sostituisce i "Bottom Bar" di JetPopup) ---------- */

function bnc_render_bar( $op_id ) {
	$op = bnc_operator( $op_id );
	if ( ! $op['go_url'] ) {
		return '';
	}
	$buttons = '';
	$linked  = bnc_linked_operator( $op );
	if ( $linked ) {
		$first   = 'Bonus ' . mb_strtolower( bnc_tipo_label( $op['tipo'] ) );
		$second  = 'Bonus ' . mb_strtolower( bnc_tipo_label( $linked['tipo'] ) );
		// Colore per tipo: casino blu, sport arancione (non per posizione).
		$buttons = bnc_go_button( $op, $first, 'bnb-btn' . ( 'casino' === $op['tipo'] ? '' : ' bnb-btn-alt' ) ) . bnc_go_button( $linked, $second, 'bnb-btn' . ( 'casino' === $linked['tipo'] ? '' : ' bnb-btn-alt' ) );
	} else {
		$buttons = bnc_go_button( $op, 'Vai al sito', 'bnb-btn' );
	}
	$h  = '<div class="bnb-bar" id="bnb-bar" data-bnb="' . (int) $op_id . '" role="complementary" aria-label="Offerta ' . esc_attr( $op['name'] ) . '" hidden>';
	$h .= '<div class="bnb-inner">';
	$h .= '<div class="bnb-logo">' . bnc_logo( $op ) . '</div>';
	if ( $linked ) {
		$txt = '';
		foreach ( array( $op, $linked ) as $o ) {
			$txt .= '<span class="bnb-bonus bnb-bonus-duo bnb-bonus--' . esc_attr( $o['tipo'] ) . '"><b>' . esc_html( bnc_tipo_label( $o['tipo'] ) ) . ':</b> ' . esc_html( bnc_bonus_text( $o['bonus'] ) ) . '</span>';
		}
	} else {
		$txt = '<span class="bnb-bonus">' . esc_html( bnc_bonus_text( $op['bonus'] ) ) . '</span>';
	}
	$h .= '<div class="bnb-text">' . $txt . '<span class="bnb-adm">18+ | Gioca responsabilmente | T&amp;C applicati</span></div>';
	$h .= '<div class="bnb-btns">' . $buttons . '</div>';
	$h .= '</div></div>';
	return $h;
}
