<?php
/**
 * Campi degli operatori.
 *
 * Ogni campo e' salvato come meta del post "Operatore" con prefisso "_bn_".
 * Tipi: text, number, yesno, checks, html (editor piccolo), rich (editor
 * completo), image, gallery, url, bool, page, operator.
 *
 * "tipo" indica a quali operatori si applica: casino, scommesse o entrambi.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

const BNC_POST_TYPE = 'bn_operatore';

/**
 * Gruppi di campi (riquadri nella schermata di modifica).
 */
function bnc_field_groups() {
	return array(
		'tabella'    => 'Dati per tabelle e toplist',
		'link'       => 'Link',
		'filtri'     => 'Filtri delle tabelle',
		'recensione' => 'Scheda recensione',
	);
}

/**
 * Definizione dei campi.
 *
 * @return array<string,array>
 */
function bnc_fields() {
	static $fields = null;
	if ( null !== $fields ) {
		return $fields;
	}
	$both   = array( 'casino', 'scommesse' );
	$casino = array( 'casino' );
	$sport  = array( 'scommesse' );

	$fields = array(
		/* Tabella */
		'logo'              => array( 'label' => 'Logo', 'type' => 'image', 'tipo' => $both, 'group' => 'tabella' ),
		'bonus'             => array( 'label' => 'Bonus in tabella', 'type' => 'html', 'tipo' => $both, 'group' => 'tabella', 'help' => 'Righe normali in piccolo, titoli (H3, H4...) in grande. Esempio: "Bonus fino a" + titolo "1000€".' ),
		'rating'            => array( 'label' => 'Valutazione (0-5)', 'type' => 'number', 'tipo' => $both, 'group' => 'tabella', 'step' => '0.1', 'min' => 0, 'max' => 5 ),
		'senza_deposito'    => array( 'label' => 'Bonus senza deposito', 'type' => 'yesno', 'tipo' => $casino, 'group' => 'tabella' ),
		'spid'              => array( 'label' => 'Registrazione SPID', 'type' => 'yesno', 'tipo' => $casino, 'group' => 'tabella' ),
		'multipla'          => array( 'label' => 'Bonus multipla', 'type' => 'yesno', 'tipo' => $sport, 'group' => 'tabella' ),
		'parere'            => array( 'label' => "Il parere dell'esperto", 'type' => 'html', 'tipo' => $both, 'group' => 'tabella', 'help' => 'Mostrato sotto la riga nella tabella completa.' ),
		'in_tabella'        => array( 'label' => 'Mostra in tabelle e toplist', 'type' => 'bool', 'tipo' => $both, 'group' => 'tabella', 'default' => '1' ),

		/* Link */
		'link'              => array( 'label' => 'Link "Vai al sito"', 'type' => 'url', 'tipo' => $both, 'group' => 'link', 'help' => 'Usa i link /visita/... (redirect), così gli adblock non li bloccano.' ),
		'link_blank'        => array( 'label' => 'Apri "Vai al sito" in una nuova scheda', 'type' => 'bool', 'tipo' => $both, 'group' => 'link' ),
		'review_page'       => array( 'label' => 'Pagina recensione', 'type' => 'page', 'tipo' => $both, 'group' => 'link' ),
		'review_url'        => array( 'label' => 'Link recensione (se non è una pagina del sito)', 'type' => 'url', 'tipo' => $both, 'group' => 'link' ),
		'collegato'         => array( 'label' => 'Operatore collegato (stesso marchio, altro tipo)', 'type' => 'operator', 'tipo' => $both, 'group' => 'link', 'help' => 'Serve alla barra in basso per mostrare sia "Bonus casino" sia "Bonus sport".' ),

		/* Filtri */
		'welcome'           => array( 'label' => 'Welcome bonus', 'type' => 'checks', 'tipo' => $casino, 'group' => 'filtri', 'options' => array( '0' => '0€', '100' => '100€', '200' => '200€' ) ),
		'tipologia'         => array( 'label' => 'Tipologia bonus', 'type' => 'checks', 'tipo' => $casino, 'group' => 'filtri', 'options' => array( '50' => '50% sul deposito', '100' => '100% sul deposito', '125' => '125% sul deposito' ) ),
		'pagamenti'         => array( 'label' => 'Pagamenti', 'type' => 'checks', 'tipo' => $casino, 'group' => 'filtri', 'options' => array( 'assegno_vidimato' => 'Assegno vidimato', 'bancoposta' => 'Bancoposta' ) ),

		/* Scheda recensione */
		'titolo'            => array( 'label' => 'Titolo scheda', 'type' => 'text', 'tipo' => $both, 'group' => 'recensione', 'help' => 'Vuoto = "Recensione" + nome.' ),
		'riepilogo'         => array( 'label' => 'Riepilogo', 'type' => 'html', 'tipo' => $both, 'group' => 'recensione' ),
		'bonus_singolo'     => array( 'label' => 'Bonus (testo esteso)', 'type' => 'html', 'tipo' => $both, 'group' => 'recensione' ),
		'bonus_casino'      => array( 'label' => 'Bonus casino', 'type' => 'html', 'tipo' => $both, 'group' => 'recensione' ),
		'bonus_sport'       => array( 'label' => 'Bonus sport', 'type' => 'html', 'tipo' => $both, 'group' => 'recensione' ),
		'bonus_sport_2'     => array( 'label' => 'Bonus sport (seconda versione)', 'type' => 'html', 'tipo' => $both, 'group' => 'recensione' ),
		'rtp'               => array( 'label' => 'RTP', 'type' => 'text', 'tipo' => $both, 'group' => 'recensione' ),
		'valutazione_cifra' => array( 'label' => 'Valutazione in cifre', 'type' => 'number', 'tipo' => $both, 'group' => 'recensione', 'step' => '0.1' ),
		'gallery_sito'      => array( 'label' => 'Immagini del sito', 'type' => 'gallery', 'tipo' => $both, 'group' => 'recensione' ),
		'gallery_app'       => array( 'label' => "Immagini dell'app", 'type' => 'gallery', 'tipo' => $both, 'group' => 'recensione' ),
		'recensione'        => array( 'label' => 'La nostra recensione', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'pro'               => array( 'label' => 'Punti di forza', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'contro'            => array( 'label' => 'Difetti', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'panoramica'        => array( 'label' => 'Panoramica', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'i_bonus'           => array( 'label' => 'I bonus', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'palinsesto'        => array( 'label' => 'Palinsesto', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'metodi'            => array( 'label' => 'Metodi di deposito e pagamento', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'esperienza'        => array( 'label' => 'Esperienza clienti', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'faq'               => array( 'label' => 'FAQ', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
		'conclusione'       => array( 'label' => 'Conclusione', 'type' => 'rich', 'tipo' => $both, 'group' => 'recensione' ),
	);
	return $fields;
}

/**
 * Tipo dell'operatore: "casino" o "scommesse".
 */
function bnc_tipo( $post_id ) {
	$t = get_post_meta( $post_id, '_bn_tipo', true );
	return 'scommesse' === $t ? 'scommesse' : 'casino';
}

/**
 * Valore grezzo di un campo.
 */
function bnc_get( $post_id, $key ) {
	$f = bnc_fields();
	$v = get_post_meta( $post_id, '_bn_' . $key, true );
	if ( '' === $v && isset( $f[ $key ]['default'] ) && ! metadata_exists( 'post', $post_id, '_bn_' . $key ) ) {
		$v = $f[ $key ]['default'];
	}
	if ( isset( $f[ $key ] ) && in_array( $f[ $key ]['type'], array( 'checks', 'gallery' ), true ) ) {
		return is_array( $v ) ? array_values( array_filter( array_map( 'strval', $v ), 'strlen' ) ) : array();
	}
	return $v;
}

/**
 * Pulisce un valore in base al tipo di campo.
 */
function bnc_sanitize( $key, $value ) {
	$f = bnc_fields();
	if ( ! isset( $f[ $key ] ) ) {
		return '';
	}
	switch ( $f[ $key ]['type'] ) {
		case 'number':
			$value = str_replace( ',', '.', trim( (string) $value ) );
			return is_numeric( $value ) ? (string) ( 0 + $value ) : '';
		case 'yesno':
			return in_array( $value, array( 'Sì', 'No' ), true ) ? $value : '';
		case 'checks':
			$opts = array_keys( $f[ $key ]['options'] );
			return array_values( array_intersect( array_map( 'strval', (array) $value ), array_map( 'strval', $opts ) ) );
		case 'gallery':
			return array_values( array_filter( array_map( 'absint', is_array( $value ) ? $value : explode( ',', (string) $value ) ) ) );
		case 'image':
		case 'page':
		case 'operator':
			return (string) absint( $value );
		case 'bool':
			return $value ? '1' : '0';
		case 'url':
			$value = trim( (string) $value );
			return ( '' !== $value && '/' === $value[0] ) ? esc_url_raw( home_url( $value ) ) : esc_url_raw( $value );
		case 'html':
		case 'rich':
			return wp_kses_post( (string) $value );
		default:
			return sanitize_text_field( (string) $value );
	}
}

/**
 * Salva un campo (valori vuoti: il meta viene rimosso, tranne i booleani).
 */
function bnc_set( $post_id, $key, $value ) {
	$value = bnc_sanitize( $key, $value );
	$f     = bnc_fields();
	if ( ( is_array( $value ) && empty( $value ) ) || ( '' === $value ) || ( '0' === $value && 'bool' !== $f[ $key ]['type'] ) ) {
		delete_post_meta( $post_id, '_bn_' . $key );
		return;
	}
	update_post_meta( $post_id, '_bn_' . $key, $value );
}
