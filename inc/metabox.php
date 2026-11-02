<?php
/**
 * Schermata di modifica dell'operatore: campi nativi, senza JetEngine.
 *
 * @package betnero-core
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'add_meta_boxes_' . BNC_POST_TYPE, function () {
	foreach ( bnc_field_groups() as $group => $title ) {
		add_meta_box( 'bnc-' . $group, $title, 'bnc_render_metabox', BNC_POST_TYPE, 'normal', 'recensione' === $group ? 'default' : 'high', array( 'group' => $group ) );
	}
	add_meta_box( 'bnc-tipo', 'Tipo di operatore', 'bnc_render_tipo_box', BNC_POST_TYPE, 'side', 'high' );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	$screen = get_current_screen();
	if ( ! $screen || BNC_POST_TYPE !== $screen->post_type || ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}
	wp_enqueue_media();
	wp_enqueue_script( 'bnc-admin-fields', BETNERO_CORE_URL . 'assets/js/admin-fields.js', array( 'jquery' ), BETNERO_CORE_VERSION, true );
} );

function bnc_render_tipo_box( $post ) {
	$tipo = get_post_meta( $post->ID, '_bn_tipo', true );
	if ( ! $tipo ) {
		$tipo = ( isset( $_GET['bn_tipo'] ) && 'scommesse' === $_GET['bn_tipo'] ) ? 'scommesse' : 'casino'; // phpcs:ignore WordPress.Security.NonceVerification
	}
	wp_nonce_field( 'bnc_save', 'bnc_nonce' );
	echo '<p><label><input type="radio" name="bnc_tipo" value="casino"' . checked( $tipo, 'casino', false ) . '> Casino</label><br>';
	echo '<label><input type="radio" name="bnc_tipo" value="scommesse"' . checked( $tipo, 'scommesse', false ) . '> Bookmaker (scommesse)</label></p>';
	echo '<p class="description">La posizione nelle tabelle si cambia trascinando le righe nella lista degli operatori.</p>';
	$cct = get_post_meta( $post->ID, '_bn_cct', true );
	if ( $cct ) {
		echo '<p class="description">Importato da JetEngine: ' . esc_html( $cct ) . '</p>';
	}
}

function bnc_render_metabox( $post, $box ) {
	$group = $box['args']['group'];
	echo '<table class="form-table bnc-fields" role="presentation"><tbody>';
	foreach ( bnc_fields() as $key => $f ) {
		if ( $f['group'] !== $group ) {
			continue;
		}
		$value = bnc_get( $post->ID, $key );
		$name  = 'bnc[' . $key . ']';
		$id    = 'bnc_' . $key;
		echo '<tr class="bnc-field bnc-type-' . esc_attr( $f['type'] ) . '" data-tipo="' . esc_attr( implode( ' ', $f['tipo'] ) ) . '">';
		echo '<th scope="row"><label for="' . esc_attr( $id ) . '">' . esc_html( $f['label'] ) . '</label></th><td>';
		bnc_render_input( $post, $key, $f, $value, $name, $id );
		if ( ! empty( $f['help'] ) ) {
			echo '<p class="description">' . esc_html( $f['help'] ) . '</p>';
		}
		echo '</td></tr>';
	}
	echo '</tbody></table>';
}

function bnc_render_input( $post, $key, $f, $value, $name, $id ) {
	switch ( $f['type'] ) {
		case 'number':
			printf(
				'<input type="number" id="%s" name="%s" value="%s" step="%s"%s%s class="small-text">',
				esc_attr( $id ),
				esc_attr( $name ),
				esc_attr( (string) $value ),
				esc_attr( isset( $f['step'] ) ? $f['step'] : 'any' ),
				isset( $f['min'] ) ? ' min="' . esc_attr( $f['min'] ) . '"' : '',
				isset( $f['max'] ) ? ' max="' . esc_attr( $f['max'] ) . '"' : ''
			);
			break;
		case 'yesno':
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="">-</option>';
			foreach ( array( 'Sì', 'No' ) as $o ) {
				echo '<option value="' . esc_attr( $o ) . '"' . selected( $value, $o, false ) . '>' . esc_html( $o ) . '</option>';
			}
			echo '</select>';
			break;
		case 'checks':
			echo '<input type="hidden" name="' . esc_attr( $name ) . '[]" value="">';
			foreach ( $f['options'] as $ok => $ol ) {
				echo '<label style="margin-right:16px"><input type="checkbox" name="' . esc_attr( $name ) . '[]" value="' . esc_attr( $ok ) . '"' . checked( in_array( (string) $ok, (array) $value, true ), true, false ) . '> ' . esc_html( $ol ) . '</label>';
			}
			break;
		case 'bool':
			echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="0">';
			echo '<label><input type="checkbox" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="1"' . checked( '1', (string) $value, false ) . '> Sì</label>';
			break;
		case 'url':
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="large-text code" placeholder="/visita/nome-operatore/">';
			break;
		case 'page':
			wp_dropdown_pages( array(
				'name'              => $name,
				'id'                => $id,
				'selected'          => (int) $value,
				'show_option_none'  => '- Nessuna -',
				'option_none_value' => '0',
				'post_status'       => array( 'publish', 'draft', 'private' ),
			) );
			break;
		case 'operator':
			$ops = get_posts( array( 'post_type' => BNC_POST_TYPE, 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'exclude' => array( $post->ID ) ) );
			echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"><option value="0">- Nessuno -</option>';
			foreach ( $ops as $op ) {
				echo '<option value="' . (int) $op->ID . '"' . selected( (int) $value, $op->ID, false ) . '>' . esc_html( $op->post_title . ' (' . ( 'scommesse' === bnc_tipo( $op->ID ) ? 'bookmaker' : 'casino' ) . ')' ) . '</option>';
			}
			echo '</select>';
			break;
		case 'image':
			$img = $value ? wp_get_attachment_image( (int) $value, 'medium', false, array( 'style' => 'max-width:200px;height:auto' ) ) : '';
			echo '<div class="bnc-media" data-multiple="0"><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"><div class="bnc-media-preview">' . $img . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<button type="button" class="button bnc-media-pick">Scegli immagine</button> <button type="button" class="button-link bnc-media-clear">Rimuovi</button></div>';
			break;
		case 'gallery':
			$ids  = (array) $value;
			$prev = '';
			foreach ( $ids as $gid ) {
				$prev .= wp_get_attachment_image( (int) $gid, 'thumbnail', false, array( 'style' => 'width:70px;height:70px;object-fit:cover;margin:0 6px 6px 0' ) );
			}
			echo '<div class="bnc-media" data-multiple="1"><input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( implode( ',', $ids ) ) . '"><div class="bnc-media-preview">' . $prev . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
			echo '<button type="button" class="button bnc-media-pick">Scegli immagini</button> <button type="button" class="button-link bnc-media-clear">Svuota</button></div>';
			break;
		case 'html':
		case 'rich':
			wp_editor( (string) $value, 'bnc' . str_replace( '_', '', $key ), array(
				'textarea_name' => $name,
				'textarea_rows' => 'rich' === $f['type'] ? 12 : 4,
				'media_buttons' => 'rich' === $f['type'],
				'teeny'         => 'html' === $f['type'],
				'quicktags'     => true,
			) );
			break;
		default:
			echo '<input type="text" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '" class="regular-text">';
	}
}

add_action( 'save_post_' . BNC_POST_TYPE, function ( $post_id ) {
	if ( ! isset( $_POST['bnc_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bnc_nonce'] ) ), 'bnc_save' ) ) {
		return;
	}
	if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	$tipo = ( isset( $_POST['bnc_tipo'] ) && 'scommesse' === $_POST['bnc_tipo'] ) ? 'scommesse' : 'casino';
	update_post_meta( $post_id, '_bn_tipo', $tipo );

	$data = isset( $_POST['bnc'] ) ? wp_unslash( $_POST['bnc'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	foreach ( bnc_fields() as $key => $f ) {
		if ( array_key_exists( $key, $data ) ) {
			bnc_set( $post_id, $key, $data[ $key ] );
		}
	}
}, 5 );
