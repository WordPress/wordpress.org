<?php

/**
 * WordPress.tv Event Meta
 *
 */
class WordPressTV_Event_Meta {
	function __construct() {
		add_action( 'event_edit_form_fields', array( $this, 'edit_form_fields' ) );
		add_action( 'event_add_form_fields',  array( $this, 'add_form_fields' ) );

		add_action( 'edited_event', array( $this, 'save' ) );
		add_action( 'created_event', array( $this, 'save' ) );
	}

	function fields() {
		return array(
			array(
				'key'  => 'youtube_playlist_id',
				'name' => 'Youtube Playlist ID',
				'desc' => 'The Youtube Playlist ID',
			),
			array(
				'key'  => 'hashtag',
				'name' => 'Hashtag',
				'desc' => 'The Event Hashtag',
			),
			array(
				'key'  => 'owners',
				'name' => 'Owners',
				'desc' => 'WordPress.org username(s) of those in charge of the videos',
			),
			array(
				'key'  => 'owners_slack',
				'name' => 'Owners (Slack)',
				'desc' => 'Slack username(s) of those in charge of the videos',
			),
		);
	}

	function add_form_fields() {
		foreach ( $this->fields() as $field ) {
			echo '<div class="form-field term-group">
        			<label for="' . esc_attr( $field['key'] ) . '">' . esc_html( $field['name'] ) . '</label>
        			<input type="text" width="40" id="' . esc_attr( $field['key'] ) . '" name="term_meta[' . esc_attr( $field['key'] ) . ']"><br>
        			<span class="description">' . esc_html( $field['desc'] ) . '</span>
    			</div>';
		}
	}

	function edit_form_fields( $term ) {
		foreach ( $this->fields() as $field ) {
			$value = get_option( "term_meta_{$term->term_id}_{$field['key']}", '' );
			echo '<tr class="form-field">
					<th scope="row" valign="top"><label for="' . esc_attr( $field['key'] ) . '">' . esc_html( $field['name'] ) . '</label></th>
					<td><input type="text" name="term_meta[' . esc_attr( $field['key'] ) . ']" id="' . esc_attr( $field['key'] ) . '" value="' . esc_attr( $value ) . '"><br />
					<span class="description">' . esc_html( $field['desc'] ) . '</span></td>
				</tr>';
		}

	}

	function save( $term_id ) {
		if ( ! isset( $_POST['term_meta'] ) ) {
			return;
		}

		foreach ( $this->fields() as $field ) {
			$key = $field['key'];
			$value = $_POST['term_meta'][ $key ] ?? '';
			$value = sanitize_text_field( $value );

			if ( $value ) {
				update_option( "term_meta_{$term_id}_{$key}", $value );
			} else {
				delete_option( "term_meta_{$term_id}_{$key}" );
			}
		}
	}
}

// Initialize the object.
add_action( 'admin_init', 'wptv_event_meta_init', 5 );
function wptv_event_meta_init() {
	$wptv_rest_api = new WordPressTV_Event_Meta();
}
