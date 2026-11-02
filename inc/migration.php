<?php
/**
 * Migrazione da JetEngine (tabelle CCT casino e scommesse) agli Operatori.
 *
 * - legge direttamente le tabelle del database, quindi funziona anche con
 *   JetEngine gia' spento (le tabelle restano)
 * - si puo' ripetere: aggiorna gli operatori gia' importati (_bn_cct)
 * - ordine e "Mostra in tabella" presi dalle query 2 e 12 del Query Builder,
 *   se JetEngine e' attivo; altrimenti dalla data di creazione
 * - collega pagine recensione, barre in basso (popup JetPopup) e marchi
 *   presenti sia come casino sia come bookmaker
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Corrispondenza campo JetEngine => campo Operatore.
 */
function bnc_cct_map( $type ) {
	if ( 'scommesse' === $type ) {
		return array(
			'logo_898'        => 'logo',
			'bonus'           => 'bonus',
			'bonus_multipla'  => 'multipla',
			'valutazione_857' => 'rating',
			'visita_il_sito2' => 'link',
		);
	}
	return array(
		'logo'                           => 'logo',
		'bonusbonus'                     => 'bonus',
		'valutazione'                    => 'rating',
		'bonus_senza_deposito'           => 'senza_deposito',
		'registrazione_spid'             => 'spid',
		'il_parere_dellesperto'          => 'parere',
		'visita_il_sito'                 => 'link',
		'vai_alla_scheda'                => 'review',
		'welcome_bonus'                  => 'welcome',
		'tipologia_bonus'                => 'tipologia',
		'pagamenti'                      => 'pagamenti',
		'titolo'                         => 'titolo',
		'riepilogo'                      => 'riepilogo',
		'bonusbonus_copy'                => 'bonus_singolo',
		'bonus_casino'                   => 'bonus_casino',
		'bonus_sport'                    => 'bonus_sport',
		'bonus_sport_525'                => 'bonus_sport_2',
		'rtp'                            => 'rtp',
		'valutazione_copy'               => 'valutazione_cifra',
		'sito'                           => 'gallery_sito',
		'app'                            => 'gallery_app',
		'field'                          => 'recensione',
		'pros_503'                       => 'pro',
		'cons'                           => 'contro',
		'panoramica'                     => 'panoramica',
		'i_bonus'                        => 'i_bonus',
		'palinsesto'                     => 'palinsesto',
		'metodi_di_deposito_e_pagamento' => 'metodi',
		'esperienza_clienti'             => 'esperienza',
		'faq'                            => 'faq',
		'conclusione'                    => 'conclusione',
	);
}

function bnc_cct_table( $type ) {
	global $wpdb;
	$table = $wpdb->prefix . 'jet_cct_' . $type;
	$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	return $found === $table ? $table : '';
}

function bnc_cct_rows( $type ) {
	global $wpdb;
	$table = bnc_cct_table( $type );
	if ( ! $table ) {
		return array();
	}
	return $wpdb->get_results( "SELECT * FROM `{$table}` ORDER BY cct_created ASC, _ID ASC", ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery,WordPress.DB.PreparedSQL
}

/**
 * Ordine attuale delle tabelle: ID JetEngine => posizione (0, 1, 2...).
 * Vuoto se il Query Builder non e' disponibile.
 */
function bnc_cct_order( $type ) {
	$items = bnc_jet_query_items( 'scommesse' === $type ? BNC_QUERY_SCOMMESSE : BNC_QUERY_CASINO );
	$order = array();
	foreach ( array_values( $items ) as $i => $it ) {
		$id = (int) bnc_item_value( $it, '_ID' );
		if ( $id ) {
			$order[ $id ] = $i;
		}
	}
	return $order;
}

/** Marchio "pulito" per abbinare popup, casino e bookmaker. */
function bnc_brand( $title ) {
	$t = strtolower( remove_accents( wp_strip_all_tags( (string) $title ) ) );
	$t = preg_replace( '/\b(casino|casin|scommesse|sport|sports|bottom|bar|test|popup)\b/', ' ', $t );
	return preg_replace( '/[^a-z]/', '', $t );
}

/** ID allegato da un URL di immagine (anche con suffisso -300x100). */
function bnc_attachment_from_url( $url ) {
	if ( ! $url ) {
		return 0;
	}
	$id = attachment_url_to_postid( $url );
	if ( ! $id ) {
		$id = attachment_url_to_postid( preg_replace( '/-\d+x\d+(\.[a-z0-9]+)$/i', '$1', $url ) );
	}
	return (int) $id;
}

/**
 * Pagina del sito da un indirizzo (anche assoluto con dominio diverso, es.
 * www.betnero.it sullo staging, e con il suffisso .html).
 */
function bnc_page_from_url( $url ) {
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );
	if ( '' === $path ) {
		return 0;
	}
	$id = url_to_postid( home_url( $path ) );
	if ( ! $id ) {
		$slug = trim( preg_replace( '/\.html?$/i', '', $path ), '/' );
		$page = $slug ? get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) ) : null;
		$id = $page ? $page->ID : 0;
		if ( ! $id ) {
			// Stesso slug in un'altra posizione (es. pagina spostata sotto /recensioni/).
			$found = get_posts( array( 'post_type' => array( 'page', 'post' ), 'post_status' => 'publish', 'name' => basename( $slug ), 'numberposts' => 1, 'fields' => 'ids' ) );
			$id    = $found ? (int) $found[0] : 0;
		}
	}
	return (int) $id;
}

function bnc_gallery_ids( $raw ) {
	$v = maybe_unserialize( $raw );
	if ( is_string( $v ) ) {
		$j = json_decode( $v, true );
		$v = is_array( $j ) ? $j : explode( ',', $v );
	}
	$ids = array();
	foreach ( (array) $v as $g ) {
		if ( is_array( $g ) && ! empty( $g['id'] ) ) {
			$ids[] = (int) $g['id'];
		} elseif ( is_numeric( $g ) ) {
			$ids[] = (int) $g;
		} elseif ( is_array( $g ) && ! empty( $g['url'] ) ) {
			$ids[] = bnc_attachment_from_url( $g['url'] );
		}
	}
	return array_values( array_filter( $ids ) );
}

/**
 * Esegue (o simula) la migrazione.
 *
 * @param bool $dry Solo simulazione.
 * @return array Righe del resoconto.
 */
function bnc_run_migration( $dry = true ) {
	$report = array();
	$GLOBALS['bnc_bulk'] = true;

	foreach ( array( 'casino', 'scommesse' ) as $type ) {
		$rows  = bnc_cct_rows( $type );
		$order = bnc_cct_order( $type );
		$map   = bnc_cct_map( $type );
		$name_key = 'scommesse' === $type ? 'nome2' : 'name';
		$system   = array( '_ID', 'cct_status', 'cct_author_id', 'cct_created', 'cct_modified', 'cct_single_post_id', $name_key );

		foreach ( $rows as $row ) {
			$cct_id = (int) $row['_ID'];
			$key    = $type . ':' . $cct_id;
			$notes  = array();
			$exist  = get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_bn_cct', 'meta_value' => $key ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			$post_id = $exist ? (int) $exist[0] : 0;

			$in_query   = isset( $order[ $cct_id ] );
			$menu_order = $in_query ? ( $order[ $cct_id ] + 1 ) * 10 : 1000 + $cct_id;
			$status     = ( 'publish' === $row['cct_status'] ) ? 'publish' : 'draft';

			$values = array();
			$extra  = array();
			foreach ( $row as $col => $raw ) {
				if ( in_array( $col, $system, true ) ) {
					continue;
				}
				if ( ! isset( $map[ $col ] ) ) {
					if ( '' !== (string) $raw ) {
						$extra[ $col ] = $raw;
					}
					continue;
				}
				$to = $map[ $col ];
				switch ( $to ) {
					case 'logo':
						$src = bnc_html_img( $raw );
						$aid = bnc_attachment_from_url( $src );
						$values['logo'] = $aid;
						$values['_logo_src'] = $src;
						if ( $src && ! $aid ) {
							$notes[] = 'logo non trovato nei Media, uso l\'indirizzo dell\'immagine';
						}
						break;
					case 'link':
						$values['link']       = bnc_html_href( $raw );
						$values['link_blank'] = false !== stripos( (string) $raw, 'target="_blank"' ) ? '1' : '0';
						if ( $values['link'] && false === strpos( $values['link'], '/visita/' ) ) {
							$notes[] = 'link diretto (non /visita/): può essere bloccato dagli adblock';
						}
						break;
					case 'review':
						$url = bnc_html_href( $raw );
						$pid = $url ? bnc_page_from_url( $url ) : 0;
						if ( $pid ) {
							$values['review_page'] = $pid;
						} elseif ( $url ) {
							$values['review_url'] = $url;
							$notes[] = 'pagina recensione non trovata: ' . $url;
						}
						break;
					case 'welcome':
					case 'tipologia':
					case 'pagamenti':
						$values[ $to ] = bnc_parse_checks( $raw );
						break;
					case 'gallery_sito':
					case 'gallery_app':
						$values[ $to ] = bnc_gallery_ids( $raw );
						break;
					case 'titolo':
						$values[ $to ] = trim( wp_strip_all_tags( (string) $raw ) );
						break;
					default:
						$values[ $to ] = $raw;
				}
			}
			$values['in_tabella'] = ( $order ? $in_query : 'publish' === $status ) ? '1' : '0';

			$report[] = array(
				'tipo'     => $type,
				'cct'      => $cct_id,
				'nome'     => $row[ $name_key ],
				'azione'   => $post_id ? 'aggiorna' : 'crea',
				'post_id'  => $post_id,
				'ordine'   => $in_query ? ( $order[ $cct_id ] + 1 ) : '-',
				'tabella'  => '1' === $values['in_tabella'] ? 'Sì' : 'No',
				'stato'    => $status,
				'note'     => $notes,
				'extra'    => array_keys( $extra ),
			);

			if ( $dry ) {
				continue;
			}

			$postarr = array(
				'post_type'   => BNC_POST_TYPE,
				'post_title'  => wp_strip_all_tags( (string) $row[ $name_key ] ),
				'post_status' => $status,
				'menu_order'  => $menu_order,
			);
			if ( $post_id ) {
				$postarr['ID'] = $post_id;
				wp_update_post( $postarr );
			} else {
				$post_id = wp_insert_post( $postarr );
			}
			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}
			update_post_meta( $post_id, '_bn_cct', $key );
			update_post_meta( $post_id, '_bn_tipo', $type );
			foreach ( $values as $k => $v ) {
				if ( '_logo_src' === $k ) {
					update_post_meta( $post_id, '_bn_logo_src', esc_url_raw( $v ) );
					continue;
				}
				bnc_set( $post_id, $k, $v );
			}
			if ( $extra ) {
				update_post_meta( $post_id, '_bn_cct_extra', $extra );
			}
			$report[ count( $report ) - 1 ]['post_id'] = $post_id;
		}
	}

	if ( ! $dry ) {
		$report = array_merge( $report, bnc_link_after_migration() );
		update_option( 'bnc_migrated_at', current_time( 'mysql' ) );
	}

	$GLOBALS['bnc_bulk'] = false;
	if ( ! $dry ) {
		bnc_flush_cache();
	}
	return $report;
}

/**
 * Collegamenti dopo l'import: marchi, pagine recensione, barre in basso.
 * Non sovrascrive mai quello che e' gia' stato impostato a mano.
 */
function bnc_link_after_migration() {
	$out = array();
	$ops = get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => -1 ) );

	// Stesso marchio, tipo diverso => operatori collegati (prima i pubblicati in tabella).
	$by_brand = array();
	foreach ( $ops as $op ) {
		$by_brand[ bnc_brand( $op->post_title ) ][ bnc_tipo( $op->ID ) ][] = $op;
	}
	$best = function ( $list ) {
		usort( $list, function ( $a, $b ) {
			$sa = ( 'publish' === $a->post_status ? 2 : 0 ) + ( '1' === (string) bnc_get( $a->ID, 'in_tabella' ) ? 1 : 0 );
			$sb = ( 'publish' === $b->post_status ? 2 : 0 ) + ( '1' === (string) bnc_get( $b->ID, 'in_tabella' ) ? 1 : 0 );
			return $sb - $sa;
		} );
		return $list[0]->ID;
	};
	$is_weak = function ( $id ) {
		return ! $id || 'publish' !== get_post_status( $id );
	};
	foreach ( $by_brand as $brand => $types ) {
		if ( ! $brand || empty( $types['casino'] ) || empty( $types['scommesse'] ) ) {
			continue;
		}
		$c = $best( $types['casino'] );
		$s = $best( $types['scommesse'] );
		$done = false;
		foreach ( array( array( $c, $s ), array( $s, $c ) ) as $pair ) {
			$cur = (int) bnc_get( $pair[0], 'collegato' );
			// Si imposta se vuoto o se punta a un operatore non pubblicato (es. "Test - Eplay").
			if ( $cur !== $pair[1] && $is_weak( $cur ) && ! $is_weak( $pair[1] ) ) {
				bnc_set( $pair[0], 'collegato', $pair[1] );
				$done = true;
			}
		}
		if ( $done ) {
			$out[] = array( 'tipo' => 'link', 'nome' => get_the_title( $c ) . ' + ' . get_the_title( $s ), 'azione' => 'operatori collegati' );
		}
	}

	// Pagina recensione => operatore della pagina.
	foreach ( $ops as $op ) {
		$page = (int) bnc_get( $op->ID, 'review_page' );
		if ( $page && ! get_post_meta( $page, '_bn_operatore', true ) ) {
			update_post_meta( $page, '_bn_operatore', $op->ID );
			$out[] = array( 'tipo' => 'pagina', 'nome' => get_the_title( $page ), 'azione' => 'operatore della pagina: ' . $op->post_title );
		}
	}

	// Popup "Bottom Bar" agganciati alle pagine => barra in basso.
	$recheck = ! get_option( 'bnc_bar_auto_v1' ); // Una volta: ricalcola anche le barre messe dalla 0.1.2.
	foreach ( bnc_popup_attachments() as $page_id => $popup_id ) {
		// Non tocca le barre scelte a mano; quelle messe dall'importazione si ricalcolano.
		if ( get_post_meta( $page_id, '_bn_barra', true ) && ! $recheck && ! get_post_meta( $page_id, '_bn_barra_auto', true ) ) {
			continue;
		}
		$op = bnc_operator_for_popup( $popup_id, $ops );
		if ( $op ) {
			update_post_meta( $page_id, '_bn_barra', (string) $op );
			update_post_meta( $page_id, '_bn_barra_auto', '1' );
			$out[] = array(
				'tipo'   => 'barra',
				'nome'   => get_the_title( $page_id ),
				'azione' => 'barra in basso: ' . get_the_title( $op ) . ' (da popup ' . get_the_title( $popup_id ) . ')',
				'note'   => 'publish' === get_post_status( $op ) ? array() : array( 'operatore in bozza: la barra non si vede finché non lo pubblichi' ),
			);
		} else {
			$out[] = array( 'tipo' => 'barra', 'nome' => get_the_title( $page_id ), 'azione' => 'popup ' . get_the_title( $popup_id ) . ' senza operatore corrispondente: da impostare a mano', 'note' => array( 'da controllare' ) );
		}
	}
	update_option( 'bnc_bar_auto_v1', '1', false );
	return $out;
}

/**
 * Pagine con un popup JetPopup agganciato dalle impostazioni pagina di Elementor.
 *
 * @return array page_id => popup_id
 */
function bnc_popup_attachments() {
	global $wpdb;
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE pm.meta_key IN ('_elementor_page_settings','_elementor_data') AND pm.meta_value LIKE '%jet_attached_popup%'
		AND p.post_status = 'publish' AND p.post_type IN ('page','post')"
	);
	$out = array();
	foreach ( $rows as $r ) {
		// Popup agganciato alle impostazioni pagina (serializzate) o a un elemento (JSON).
		$ids = array();
		$set = maybe_unserialize( $r->meta_value );
		if ( is_array( $set ) && ! empty( $set['jet_attached_popup'] ) ) {
			$ids[] = is_array( $set['jet_attached_popup'] ) ? reset( $set['jet_attached_popup'] ) : $set['jet_attached_popup'];
		} elseif ( preg_match_all( '/"jet_attached_popup":\[?"?(\d+)/', $r->meta_value, $m ) ) {
			$ids = $m[1];
		}
		foreach ( $ids as $pid ) {
			$pid = (int) $pid;
			if ( $pid && 'jet-popup' === get_post_type( $pid ) && 'publish' === get_post_status( $pid ) ) {
				$out[ (int) $r->post_id ] = $pid;
				break;
			}
		}
	}

	// Condizioni di visualizzazione di JetPopup ("mostra sulla pagina X").
	foreach ( bnc_popup_conditions() as $popup_id => $pages ) {
		foreach ( $pages as $page_id ) {
			if ( ! isset( $out[ $page_id ] ) && 'publish' === get_post_status( $page_id ) ) {
				$out[ $page_id ] = $popup_id;
			}
		}
	}
	return $out;
}

/**
 * Pagine indicate nelle condizioni dei popup JetPopup pubblicati.
 *
 * @return array popup_id => int[] pagine
 */
function bnc_popup_conditions() {
	global $wpdb;
	$out  = array();
	$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"SELECT pm.post_id, pm.meta_value FROM {$wpdb->postmeta} pm JOIN {$wpdb->posts} p ON p.ID = pm.post_id
		WHERE p.post_type = 'jet-popup' AND p.post_status = 'publish' AND pm.meta_value LIKE '%subGroupValue%'"
	);
	foreach ( $rows as $r ) {
		$pages = bnc_condition_pages( $r->meta_value );
		if ( $pages ) {
			$out[ (int) $r->post_id ] = $pages;
		}
	}
	if ( ! $out ) {
		// Alcune versioni salvano tutte le condizioni in un'opzione, divise per popup.
		$opts = $wpdb->get_col( "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE '%popup%' AND option_value LIKE '%subGroupValue%'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		foreach ( $opts as $raw ) {
			$data = maybe_unserialize( $raw );
			if ( is_string( $data ) ) {
				$data = json_decode( $data, true );
			}
			foreach ( (array) $data as $popup_id => $conds ) {
				$pages = bnc_condition_pages( $conds );
				if ( $pages && is_numeric( $popup_id ) && 'publish' === get_post_status( (int) $popup_id ) ) {
					$out[ (int) $popup_id ] = $pages;
				}
			}
		}
	}
	return $out;
}

/**
 * Pagine incluse da un insieme di condizioni JetPopup (serializzato, JSON o array).
 *
 * @return int[]
 */
function bnc_condition_pages( $raw ) {
	$data = is_string( $raw ) ? maybe_unserialize( $raw ) : $raw;
	if ( is_string( $data ) ) {
		$data = json_decode( $data, true );
	}
	$pages = array();
	$walk  = function ( $node ) use ( &$walk, &$pages ) {
		if ( ! is_array( $node ) ) {
			return;
		}
		if ( isset( $node['subGroupValue'], $node['subGroup'] ) && 0 === strpos( (string) $node['subGroup'], 'singular-' ) ) {
			$inc = ! isset( $node['include'] ) || true === $node['include'] || 'true' === $node['include'];
			if ( $inc ) {
				foreach ( (array) $node['subGroupValue'] as $v ) {
					if ( is_numeric( $v ) ) {
						$pages[] = (int) $v;
					}
				}
			}
			return;
		}
		foreach ( $node as $child ) {
			$walk( $child );
		}
	};
	$walk( $data );
	return array_values( array_unique( array_filter( $pages ) ) );
}

function bnc_operator_for_popup( $popup_id, $ops ) {
	$title = get_the_title( $popup_id );
	$brand = bnc_brand( $title );
	// Parole intere: "Sportitaliabet Casino" e' un casino, non scommesse.
	$want  = preg_match( '/\b(scommesse|sport)\b/i', $title ) ? 'scommesse' : 'casino';
	$best  = 0;
	$score = -1;
	foreach ( $ops as $op ) {
		$b = bnc_brand( $op->post_title );
		if ( ! $b || ! $brand || ( 0 !== strpos( $b, $brand ) && 0 !== strpos( $brand, $b ) ) ) {
			continue;
		}
		// Prima il tipo giusto, poi i pubblicati, poi quelli in tabella.
		$sc = ( bnc_tipo( $op->ID ) === $want ? 4 : 0 ) + ( 'publish' === $op->post_status ? 2 : 0 ) + ( '1' === (string) bnc_get( $op->ID, 'in_tabella' ) ? 1 : 0 );
		if ( $sc > $score ) {
			$score = $sc;
			$best  = $op->ID;
		}
	}
	return $best;
}
