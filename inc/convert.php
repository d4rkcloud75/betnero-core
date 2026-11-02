<?php
/**
 * Conversione dei testi delle recensioni (HTML classico + shortcode di
 * Shortcodes Ultimate) in blocchi veri di WordPress.
 *
 * - [su_accordion]/[su_spoiler] con risposta  -> blocchi Dettagli "FAQ Betnero"
 * - [su_accordion]/[su_spoiler] senza testo   -> elenco puntato
 * - p, h1-h6, ul/ol                           -> Paragrafo, Titolo, Elenco
 * - div/section/span di contorno              -> tolti, resta il contenuto
 * - tutto il resto (tabelle, immagini, iframe) -> blocco HTML
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Voci di un accordion di Shortcodes Ultimate.
 *
 * @return array Lista di array( 'title' => testo, 'body' => array di paragrafi HTML ).
 */
function bnc_su_items( $html ) {
	$items = array();
	if ( ! preg_match_all( '#\[su_spoiler\b(.*?)\](.*?)\[/su_spoiler\]#s', (string) $html, $m, PREG_SET_ORDER ) ) {
		return $items;
	}
	foreach ( $m as $one ) {
		$title = '';
		if ( preg_match( '#title\s*=\s*"(.*?)"#s', $one[1], $t ) || preg_match( "#title\s*=\s*'(.*?)'#s", $one[1], $t ) ) {
			$title = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( wp_strip_all_tags( $t[1] ), ENT_QUOTES, 'UTF-8' ) ) );
		}
		if ( '' === $title ) {
			continue;
		}
		$items[] = array(
			'title' => $title,
			'body'  => bnc_su_body( $one[2] ),
		);
	}
	return $items;
}

/**
 * Testo di una voce: paragrafi con il solo HTML in linea (grassetti, link).
 */
function bnc_su_body( $html ) {
	$html = preg_replace( '#</p>\s*<p[^>]*>#i', "\n\n", (string) $html );
	$html = preg_replace( '#<br\s*/?>#i', "\n", $html );
	$html = preg_replace( '#</?(p|div|h[1-6])[^>]*>#i', "\n\n", $html );
	$html = wp_kses( $html, array(
		'a'      => array( 'href' => true, 'target' => true, 'rel' => true ),
		'strong' => array(),
		'b'      => array(),
		'em'     => array(),
		'i'      => array(),
	) );
	$out = array();
	foreach ( preg_split( '/\n\s*\n/', $html ) as $p ) {
		$p    = trim( preg_replace( '/\s+/u', ' ', $p ) );
		$text = trim( str_replace( "\xc2\xa0", ' ', wp_strip_all_tags( $p ) ) );
		if ( '' === $text || preg_match( '/^testo finto$/i', $text ) ) {
			continue;
		}
		$out[] = bnc_clean_inline( force_balance_tags( $p ) );
	}
	return $out;
}

/**
 * Toglie grassetti, corsivi e span vuoti (es. "<strong> </strong>").
 */
function bnc_clean_inline( $html ) {
	do {
		$before = $html;
		$html   = preg_replace( '#<(strong|b|em|i|span|u)\b[^>]*>(?:\s|&nbsp;|\xc2\xa0)*</\1>#iu', ' ', $html );
	} while ( $before !== $html );
	return trim( preg_replace( '/ {2,}/', ' ', $html ) );
}

/* ---------- Blocchi ---------- */

function bnc_block_paragraph( $inner ) {
	$inner = bnc_clean_inline( $inner );
	return "<!-- wp:paragraph -->\n<p>" . $inner . "</p>\n<!-- /wp:paragraph -->\n\n";
}

function bnc_block_heading( $inner, $level = 2 ) {
	$level = max( 2, min( 6, (int) $level ) );
	$attrs = 2 === $level ? '' : ' {"level":' . $level . '}';
	return '<!-- wp:heading' . $attrs . " -->\n<h" . $level . ' class="wp-block-heading">' . $inner . '</h' . $level . ">\n<!-- /wp:heading -->\n\n";
}

/**
 * Elenco puntato da una lista di voci HTML in linea.
 */
function bnc_block_list( $items, $ordered = false ) {
	$tag = $ordered ? 'ol' : 'ul';
	$h   = '<!-- wp:list' . ( $ordered ? ' {"ordered":true}' : '' ) . " -->\n<" . $tag . ' class="wp-block-list">';
	foreach ( $items as $li ) {
		$h .= "<!-- wp:list-item -->\n<li>" . $li . "</li>\n<!-- /wp:list-item -->";
	}
	return $h . '</' . $tag . ">\n<!-- /wp:list -->\n\n";
}

function bnc_block_html( $html ) {
	return "<!-- wp:html -->\n" . trim( $html ) . "\n<!-- /wp:html -->\n\n";
}

/**
 * Una domanda FAQ: blocco Dettagli con stile "FAQ Betnero" (genera anche il JSON-LD).
 */
function bnc_block_faq( $question, $paragraphs ) {
	$h = '<!-- wp:details {"className":"is-style-bn-faq"} -->' . "\n" . '<details class="wp-block-details is-style-bn-faq"><summary>' . esc_html( $question ) . '</summary>';
	foreach ( $paragraphs as $p ) {
		$h .= rtrim( bnc_block_paragraph( $p ) );
	}
	return $h . "</details>\n<!-- /wp:details -->\n\n";
}

/**
 * Accordion di Shortcodes Ultimate: FAQ se le voci hanno un testo, elenco se no.
 */
function bnc_blocks_from_su( $items ) {
	if ( ! $items ) {
		return '';
	}
	$with_body = array_filter( $items, function ( $it ) {
		return ! empty( $it['body'] );
	} );
	if ( ! $with_body ) {
		return bnc_block_list( array_map( function ( $it ) {
			return esc_html( $it['title'] );
		}, $items ) );
	}
	$h = '';
	foreach ( $items as $it ) {
		$h .= bnc_block_faq( $it['title'], $it['body'] ? $it['body'] : array( '' ) );
	}
	return $h;
}

/* ---------- HTML classico -> blocchi ---------- */

/**
 * Converte un pezzo di HTML classico in blocchi.
 */
function bnc_html_to_blocks( $html ) {
	$html = trim( (string) $html );
	if ( '' === $html ) {
		return '';
	}
	if ( false === stripos( $html, '<p' ) && false === stripos( $html, '<h' ) && false === stripos( $html, '<ul' ) && false === stripos( $html, '<div' ) ) {
		$html = wpautop( $html );
	}

	// Gli accordion diventano segnaposto, poi blocchi.
	$acc  = array();
	$html = preg_replace_callback( '#\[su_accordion\b[^\]]*\](.*?)\[/su_accordion\]#s', function ( $m ) use ( &$acc ) {
		$acc[] = bnc_su_items( $m[1] );
		return "\n<!--BNCACC:" . ( count( $acc ) - 1 ) . "-->\n";
	}, $html );
	// Spoiler sciolti, fuori da un accordion.
	$html = preg_replace_callback( '#\[su_spoiler\b.*?\[/su_spoiler\]#s', function ( $m ) use ( &$acc ) {
		$acc[] = bnc_su_items( $m[0] );
		return "\n<!--BNCACC:" . ( count( $acc ) - 1 ) . "-->\n";
	}, $html );
	// Shortcode di Shortcodes Ultimate rimasti spaiati (es. un [su_accordion] aperto due volte).
	$html = preg_replace( '#\[/?su_(?:accordion|spoiler)\b[^\]]*\]#', '', $html );
	// Toglie i <p> rimasti attorno ai segnaposto.
	$html = preg_replace( '#<p[^>]*>(?:\s|<br\s*/?>|&nbsp;)*(<!--BNCACC:\d+-->)(?:\s|<br\s*/?>|&nbsp;)*</p>#i', '$1', $html );
	$html = preg_replace( '#<p[^>]*>(?:\s|<br\s*/?>|&nbsp;)*(<!--BNCACC:\d+-->)#i', '$1<p>', $html );
	$html = preg_replace( '#(<!--BNCACC:\d+-->)(?:\s|<br\s*/?>|&nbsp;)*</p>#i', '</p>$1', $html );

	if ( ! class_exists( 'DOMDocument' ) ) {
		return bnc_block_html( $html );
	}
	$doc  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="utf-8"?><div id="bncroot">' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$root = $doc->getElementById( 'bncroot' );
	if ( ! $root ) {
		return bnc_block_html( $html );
	}
	$out    = '';
	$inline = '';
	bnc_walk_nodes( $root, $doc, $acc, $out, $inline );
	if ( '' !== trim( wp_strip_all_tags( $inline ) ) ) {
		$out .= bnc_block_paragraph( trim( $inline ) );
	}
	return $out;
}

function bnc_inner_html( $node, $doc ) {
	$h = '';
	foreach ( $node->childNodes as $c ) {
		$h .= $doc->saveHTML( $c );
	}
	return trim( $h );
}

function bnc_is_empty_html( $html ) {
	return '' === trim( str_replace( "\xc2\xa0", ' ', html_entity_decode( wp_strip_all_tags( $html ), ENT_QUOTES, 'UTF-8' ) ) ) && false === stripos( $html, '<img' ) && false === stripos( $html, '<iframe' );
}

/**
 * Visita i figli di un nodo e accumula i blocchi in $out.
 * Il testo in linea fuori da un paragrafo si accumula in $inline.
 */
function bnc_walk_nodes( $parent, $doc, $acc, &$out, &$inline ) {
	$flush = function () use ( &$out, &$inline ) {
		if ( ! bnc_is_empty_html( $inline ) ) {
			$out .= bnc_block_paragraph( trim( $inline ) );
		}
		$inline = '';
	};
	$inline_tags = array( 'a', 'strong', 'b', 'em', 'i', 'u', 'span', 'small', 'sup', 'sub', 'mark', 'code', 'abbr', 'br' );

	foreach ( iterator_to_array( $parent->childNodes ) as $n ) {
		if ( XML_COMMENT_NODE === $n->nodeType ) {
			if ( preg_match( '/^BNCACC:(\d+)$/', trim( $n->nodeValue ), $mm ) ) {
				$flush();
				$out .= bnc_blocks_from_su( isset( $acc[ (int) $mm[1] ] ) ? $acc[ (int) $mm[1] ] : array() );
			}
			continue;
		}
		if ( XML_TEXT_NODE === $n->nodeType ) {
			$inline .= esc_html( $n->nodeValue );
			continue;
		}
		if ( XML_ELEMENT_NODE !== $n->nodeType ) {
			continue;
		}
		$tag = strtolower( $n->nodeName );

		if ( in_array( $tag, $inline_tags, true ) && ! bnc_has_block_child( $n ) ) {
			$inline .= 'br' === $tag ? '<br>' : $doc->saveHTML( $n );
			continue;
		}
		$flush();

		if ( 'p' === $tag ) {
			$inner = bnc_inner_html( $n, $doc );
			if ( ! bnc_is_empty_html( $inner ) ) {
				$out .= bnc_block_paragraph( $inner );
			}
		} elseif ( preg_match( '/^h([1-6])$/', $tag, $hm ) ) {
			$inner = bnc_inner_html( $n, $doc );
			if ( ! bnc_is_empty_html( $inner ) ) {
				$out .= bnc_block_heading( $inner, (int) $hm[1] );
			}
		} elseif ( 'ul' === $tag || 'ol' === $tag ) {
			$out .= bnc_list_from_node( $n, $doc );
		} elseif ( 'table' === $tag ) {
			$table = bnc_table_from_node( $n, $doc );
			$out  .= $table ? $table : bnc_block_html( $doc->saveHTML( $n ) );
		} elseif ( in_array( $tag, array( 'div', 'section', 'article', 'span', 'main', 'header', 'footer', 'center', 'font' ), true ) ) {
			bnc_walk_nodes( $n, $doc, $acc, $out, $inline );
			$flush();
		} else {
			$html = $doc->saveHTML( $n );
			if ( ! bnc_is_empty_html( $html ) ) {
				$out .= bnc_block_html( $html );
			}
		}
	}
}

function bnc_has_block_child( $node ) {
	foreach ( $node->childNodes as $c ) {
		if ( XML_ELEMENT_NODE === $c->nodeType && in_array( strtolower( $c->nodeName ), array( 'p', 'div', 'ul', 'ol', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'table', 'section' ), true ) ) {
			return true;
		}
	}
	return false;
}

/**
 * Elenco da un nodo ul/ol; le sottoliste diventano elenchi annidati.
 */
function bnc_list_from_node( $node, $doc ) {
	$ordered = 'ol' === strtolower( $node->nodeName );
	$tag     = $ordered ? 'ol' : 'ul';
	$h       = '<!-- wp:list' . ( $ordered ? ' {"ordered":true}' : '' ) . " -->\n<" . $tag . ' class="wp-block-list">';
	$count   = 0;
	foreach ( $node->childNodes as $li ) {
		if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
			continue;
		}
		$text   = '';
		$nested = '';
		foreach ( $li->childNodes as $c ) {
			$ct = XML_ELEMENT_NODE === $c->nodeType ? strtolower( $c->nodeName ) : '';
			if ( 'ul' === $ct || 'ol' === $ct ) {
				$nested .= rtrim( bnc_list_from_node( $c, $doc ) );
			} elseif ( 'p' === $ct ) {
				$text .= ( '' === trim( $text ) ? '' : '<br>' ) . bnc_inner_html( $c, $doc );
			} else {
				$text .= $doc->saveHTML( $c );
			}
		}
		if ( bnc_is_empty_html( $text ) && '' === $nested ) {
			continue;
		}
		$h .= "<!-- wp:list-item -->\n<li>" . trim( $text ) . $nested . "</li>\n<!-- /wp:list-item -->";
		++$count;
	}
	if ( ! $count ) {
		return '';
	}
	return $h . '</' . $tag . ">\n<!-- /wp:list -->\n\n";
}

/**
 * Blocco Tabella da un nodo table. Le righe di thead (o la prima riga se
 * e' tutta in grassetto) diventano l'intestazione. Restituisce '' se la
 * tabella ha celle unite o tabelle annidate: in quel caso resta un blocco HTML.
 */
function bnc_table_from_node( $table, $doc ) {
	if ( $table->getElementsByTagName( 'table' )->length ) {
		return '';
	}
	$head = array();
	$body = array();
	foreach ( $table->getElementsByTagName( 'tr' ) as $tr ) {
		$cells = array();
		foreach ( $tr->childNodes as $cell ) {
			if ( XML_ELEMENT_NODE !== $cell->nodeType || ! in_array( strtolower( $cell->nodeName ), array( 'td', 'th' ), true ) ) {
				continue;
			}
			if ( $cell->hasAttribute( 'colspan' ) && (int) $cell->getAttribute( 'colspan' ) > 1 ) {
				return '';
			}
			if ( $cell->hasAttribute( 'rowspan' ) && (int) $cell->getAttribute( 'rowspan' ) > 1 ) {
				return '';
			}
			$inner = bnc_inner_html( $cell, $doc );
			$inner = preg_replace( '#</p>\s*<p[^>]*>#i', '<br>', $inner );
			$inner = preg_replace( '#</?p[^>]*>#i', '', $inner );
			$cells[] = bnc_clean_inline( trim( $inner ) );
		}
		if ( ! $cells ) {
			continue;
		}
		$in_head = 'thead' === strtolower( $tr->parentNode->nodeName );
		if ( $in_head ) {
			$head[] = $cells;
		} else {
			$body[] = $cells;
		}
	}
	if ( ! $body && ! $head ) {
		return '';
	}
	// Prima riga tutta in grassetto e nessun thead: diventa l'intestazione.
	if ( ! $head && count( $body ) > 1 ) {
		$bold = true;
		foreach ( $body[0] as $c ) {
			if ( '' !== $c && ! preg_match( '#^<(strong|b)>.*</\1>$#is', $c ) ) {
				$bold = false;
			}
		}
		if ( $bold ) {
			$head[] = array_shift( $body );
		}
	}
	$strip_bold = function ( $c ) {
		return preg_replace( '#^<(strong|b)>(.*)</\1>$#is', '$2', $c );
	};
	$h = '<!-- wp:table -->' . "\n" . '<figure class="wp-block-table"><table class="has-fixed-layout">';
	if ( $head ) {
		$h .= '<thead>';
		foreach ( $head as $row ) {
			$h .= '<tr><th>' . implode( '</th><th>', array_map( $strip_bold, $row ) ) . '</th></tr>';
		}
		$h .= '</thead>';
	}
	if ( $body ) {
		$h .= '<tbody>';
		foreach ( $body as $row ) {
			$h .= '<tr><td>' . implode( '</td><td>', $row ) . '</td></tr>';
		}
		$h .= '</tbody>';
	}
	return $h . "</table></figure>\n<!-- /wp:table -->\n\n";
}

/* ---------- Punti di forza e difetti ---------- */

/**
 * Voci (HTML in linea) di un campo pro/contro: dagli spoiler, dagli elenchi
 * o dai paragrafi.
 */
function bnc_procontro_items( $html ) {
	$html = (string) $html;
	if ( '' === trim( $html ) ) {
		return array();
	}
	$su = bnc_su_items( $html );
	if ( $su ) {
		return array_map( function ( $it ) {
			$t = esc_html( $it['title'] );
			return $it['body'] ? '<strong>' . $t . '</strong>: ' . implode( ' ', $it['body'] ) : $t;
		}, $su );
	}
	if ( preg_match_all( '#<li[^>]*>(.*?)</li>#is', $html, $m ) ) {
		return array_values( array_filter( array_map( 'trim', $m[1] ), function ( $x ) {
			return ! bnc_is_empty_html( $x );
		} ) );
	}
	$out = array();
	foreach ( preg_split( '#</?(?:p|div|h[1-6])[^>]*>|<br\s*/?>|\n#i', $html ) as $line ) {
		$line = trim( wp_kses( $line, array( 'a' => array( 'href' => true ), 'strong' => array(), 'b' => array(), 'em' => array() ) ) );
		if ( ! bnc_is_empty_html( $line ) ) {
			$out[] = $line;
		}
	}
	return $out;
}

/**
 * Due colonne affiancate "Punti di forza" (verde) e "Difetti" (rosso).
 */
function bnc_block_procontro( $pro_html, $contro_html ) {
	$cols = '';
	foreach ( array(
		'bns-pro'    => array( 'Punti di forza', $pro_html ),
		'bns-contro' => array( 'Difetti', $contro_html ),
	) as $class => $data ) {
		$items = bnc_procontro_items( $data[1] );
		if ( ! $items ) {
			continue;
		}
		$cols .= '<!-- wp:column {"className":"' . $class . '"} -->' . "\n" . '<div class="wp-block-column ' . $class . '">';
		$cols .= bnc_block_heading( esc_html( $data[0] ), 3 ) . rtrim( bnc_block_list( $items ) );
		$cols .= "</div>\n<!-- /wp:column -->\n\n";
	}
	if ( '' === $cols ) {
		return '';
	}
	return '<!-- wp:columns {"className":"bns-procontro-cols"} -->' . "\n" . '<div class="wp-block-columns bns-procontro-cols">' . rtrim( $cols ) . "</div>\n<!-- /wp:columns -->\n\n";
}
