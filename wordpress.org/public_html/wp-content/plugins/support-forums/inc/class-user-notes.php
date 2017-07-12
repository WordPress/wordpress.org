<?php

namespace WordPressdotorg\Forums;

class User_Notes {

	/**
	 * An array of all notes for each user.
	 *
	 * As users may have written multiple replies in a thread, this will help us
	 * to avoid looking up all the notes multiple times.
	 *
	 * @access private
	 *
	 * @var array $user_notes
	 */
	private $user_notes = array();

	public function __construct() {
		add_action( 'wp_enqueue_scripts',                   array( $this, 'enqueue_scripts' ) );

		add_action( 'bbp_post_request',                     array( $this, 'add_user_note' ) );
		add_action( 'bbp_get_request',                      array( $this, 'delete_user_note' ) );

		add_action( 'bbp_theme_after_topic_author_details', array( $this, 'add_user_notes_toggle_link' ) );
		add_action( 'bbp_theme_after_reply_author_details', array( $this, 'add_user_notes_toggle_link' ) );

		add_action( 'bbp_theme_before_topic_content',       array( $this, 'display_user_notes_in_content' ) );
		add_action( 'bbp_theme_before_reply_content',       array( $this, 'display_user_notes_in_content' ) );
		add_action( 'bbp_template_after_user_profile',      array( $this, 'display_user_notes_in_profile' ) );
	}

	/**
	 * Registers scripts and styles.
	 */
	function enqueue_scripts() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		wp_enqueue_script( 'wporg-bbp-user-notes', plugins_url( '/js/user-notes.js', __DIR__ ), array( 'jquery' ), '20170710', true );
	}

	/**
	 * Checks if a user note is added and saves it to user's meta data.
	 *
	 * @param string $action Requested action.
	 */
	function add_user_note( $action = '' ) {
		if ( 'wporg_bbp_add_user_note' !== $action || ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id   = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$post_id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		$note_id   = isset( $_POST['note_id'] ) ? (int) $_POST['note_id'] : 0;
		$note_text = isset( $_POST['note_text'] ) ? wp_kses( $_POST['note_text'], array( 'a' => array( 'href' => true ) ) ) : '';

		if ( ! $user_id || ! $note_text ) {
			return;
		}

		// Make sure our nonces are in order.
		if ( ! bbp_verify_nonce_request( sprintf( 'wporg-bbp-add-user-note_%d_%d', $user_id, $post_id ) ) ) {
			return;
		}

		// Make sure the user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Get an array of existing notes, or create an array if there are none.
		$user_notes = get_user_meta( $user_id, '_wporg_bbp_user_notes', true );
		if ( ! $user_notes ) {
			$user_notes = array();
		}

		$edit_note = isset( $user_notes[ $note_id ] );

		if ( ! $edit_note ) {
			$note_id = count( $user_notes ) + 1;

			// Add the new note to the array of notes.
			$user_notes[ $note_id ] = (object) array(
				'text'      => $note_text,
				'date'      => current_time( 'mysql' ),
				'post_id'   => $post_id,
				'site_id'   => get_current_blog_id(),
				'moderator' => wp_get_current_user()->user_nicename
			);
		} else {
			// Only keymasters or the note author can edit a note.
			if (
				! current_user_can( 'keep_gate' )
			&&
				$user_notes[ $note_id ]->moderator !== wp_get_current_user()->user_nicename
			) {
				return;
			}

			// Save new text for an existing note.
			$user_notes[ $note_id ]->text = $note_text;

			// Add site ID if missing.
			if ( ! isset( $user_notes[ $note_id ]->site_id ) ) {
				$user_notes[ $note_id ]->site_id = get_current_blog_id();
			}
		}

		update_user_meta( $user_id, '_wporg_bbp_user_notes', $user_notes );

		$redirect_url = set_url_scheme( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

		// Redirect to clear form data.
		bbp_redirect( $redirect_url );
	}

	/**
	 * Deletes a previously added note from user's meta data.
	 *
	 * @param string $action Requested action.
	 */
	function delete_user_note( $action = '' ) {
		if ( 'wporg_bbp_delete_user_note' !== $action || ! current_user_can( 'keep_gate' ) ) {
			return;
		}

		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$note_id = isset( $_GET['note_id'] ) ? (int) $_GET['note_id'] : 0;

		if ( ! $user_id || ! $note_id ) {
			return;
		}

		// Make sure our nonces are in order.
		if ( ! bbp_verify_nonce_request( sprintf( 'wporg-bbp-delete-user-note_%d_%d', $user_id, $note_id ) ) ) {
			return;
		}

		// Make sure the user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		// Get an array of existing notes.
		$user_notes = get_user_meta( $user_id, '_wporg_bbp_user_notes', true );
		if ( ! $user_notes ) {
			return;
		}

		unset( $user_notes[ $note_id ] );

		// Reindex the array from 1.
		if ( $user_notes ) {
			$user_notes = array_combine( range( 1, count( $user_notes ) ), array_values( $user_notes ) );
		}

		update_user_meta( $user_id, '_wporg_bbp_user_notes', $user_notes );

		$redirect_url = remove_query_arg( array( 'action', 'user_id', 'note_id', '_wpnonce' ) );

		// Redirect to clear URL.
		bbp_redirect( $redirect_url );
	}

	/**
	 * Retrieves all notes for a particular user.
	 *
	 * @param int $user_id User ID. Defaults to the current post author.
	 * @return array Array of user notes.
	 */
	function get_user_notes( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_the_author_meta( 'ID' );
		}

		// Bail early if the notes are already grabbed for this session.
		if ( isset( $this->user_notes[ $user_id ] ) ) {
			return $this->user_notes[ $user_id ];
		}

		$user_notes = get_user_meta( $user_id, '_wporg_bbp_user_notes', true );
		if ( ! $user_notes ) {
			$user_notes = array();
		}

		$this->user_notes[ $user_id ] = $user_notes;

		return $user_notes;
	}

	/**
	 * Adds toggle link for notes to the author area of a post.
	 */
	function add_user_notes_toggle_link() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id = get_the_author_meta( 'ID' );
		$post_id = get_the_ID();

		// Only keymasters can see notes on moderators.
		if ( user_can( $user_id, 'moderate' ) && ! current_user_can( 'keep_gate' ) ) {
			return;
		}

		printf( '<div class="wporg-bbp-user-notes-toggle"><a href="#" data-post-id="%d">%s</a></div>',
			esc_attr( $post_id ),
			esc_html(
				/* translators: %d: user notes count */
				sprintf( __( 'User Notes (%d)', 'wporg-forums' ),
					count( $this->get_user_notes( $user_id ) )
				)
			)
		);
	}

	/**
	 * Retrieves permalink to the post associated with a note.
	 *
	 * If the note is not associated with a particular post, returns a link
	 * to user profile.
	 *
	 * @param int $post_id Post ID. Default 0.
	 * @param int $user_id User ID. Default 0.
	 * @param int $site_id Site ID. Default 0.
	 * @return string Post permalink or user profile URL.
	 */
	function get_user_note_post_permalink( $post_id = 0, $user_id = 0, $site_id = 0 ) {
		switch_to_blog( $site_id );

		$post_type = $post_id ? get_post_type( $post_id ) : '';

		if ( 'topic' === $post_type ) {
			$permalink = bbp_get_topic_permalink( $post_id ) . '#post-' . (int) $post_id;
			$permalink = add_query_arg( 'show_user_notes', $post_id, $permalink );
		} elseif ( 'reply' === $post_type ) {
			$permalink = bbp_get_reply_url( $post_id );
			$permalink = add_query_arg( 'show_user_notes', $post_id, $permalink );
		} else {
			$permalink = bbp_get_user_profile_url( $user_id ) . '#user-notes';
		}

		restore_current_blog();

		return $permalink;
	}

	/**
	 * Displays notes for a particular user and a form for adding a new note.
	 *
	 * @param int $user_id User ID. Defaults to the current post author.
	 */
	function display_user_notes( $user_id = 0 ) {
		$note_id    = isset( $_GET['note_id'] ) ? (int) $_GET['note_id'] : 0;
		$user_notes = $this->get_user_notes( $user_id );
		$edit_note  = isset( $user_notes[ $note_id ] );

		$current_post_type = get_post_type();

		foreach ( $user_notes as $key => $note ) {
			$post_site_id       = isset( $note->site_id ) ? (int) $note->site_id : get_current_blog_id();
			$post_permalink     = $this->get_user_note_post_permalink( $note->post_id, $user_id, $post_site_id );
			$redirect_on_delete = $this->get_user_note_post_permalink( get_the_ID(), $user_id, get_current_blog_id() );

			$note_meta = array(
				/* translators: 1: user note author's display name, 2: link to post, 3: date, 4: time */
				'author' => sprintf( __( 'By %1$s on <a href="%2$s">%3$s at %4$s</a>', 'wporg-forums' ),
					sprintf( '<a href="%s">%s</a>',
						esc_url( get_home_url( $post_site_id, "/users/{$note->moderator}/" ) ),
						$note->moderator
					),
					esc_url( $post_permalink ),
					/* translators: localized date format, see https://secure.php.net/date */
					mysql2date( __( 'F j, Y', 'wporg-forums' ), $note->date ),
					/* translators: localized time format, see https://secure.php.net/date */
					mysql2date( __( 'g:i a', 'wporg-forums' ), $note->date )
				)
			);

			// Only keymasters or the note author can edit a note.
			if (
				current_user_can( 'keep_gate' ) && $post_site_id == get_current_blog_id()
			||
				$note->moderator === wp_get_current_user()->user_nicename
			) {
				$note_meta['edit'] = sprintf( '<a href="%s">%s</a>',
					esc_url(
						add_query_arg( array(
							'action'  => 'wporg_bbp_edit_user_note',
							'user_id' => $user_id,
							'note_id' => $key,
						), $post_permalink )
					),
					__( 'Edit', 'wporg-forums' )
				);
			}

			// Only keymasters can delete a note.
			if ( current_user_can( 'keep_gate' ) && $post_site_id == get_current_blog_id() ) {
				$note_meta['delete'] = sprintf( '<a href="%s">%s</a>',
					esc_url( wp_nonce_url(
						add_query_arg( array(
							'action'  => 'wporg_bbp_delete_user_note',
							'user_id' => $user_id,
							'note_id' => $key,
						), $redirect_on_delete ),
						sprintf( 'wporg-bbp-delete-user-note_%d_%d', $user_id, $key )
					) ),
					__( 'Delete', 'wporg-forums' )
				);
			}

			printf( '<div class="bbp-template-notice warning"><p>%s</p> %s</div>' . "\n",
				wp_kses( $note->text, array( 'a' => array( 'href' => true ) ) ),
				sprintf( '<p class="wporg-bbp-user-note-meta">%s</p>' . "\n",
					implode( ' | ', $note_meta )
				)
			);

			if ( $edit_note && $key == $note_id ) {
				$this->display_note_form( $user_id );
			}
		}

		if ( ! $user_notes ) {
			printf( '<div class="bbp-template-notice info"><p>%s</p></div>',
				esc_html__( 'No notes have been added for this user.', 'wporg-forums' )
			);
		}

		if ( ! $edit_note ) {
			$this->display_note_form( $user_id );
		}
	}

	/**
	 * Displays the form for adding a new note or editing an existing note.
	 *
	 * @param int $user_id User ID. Defaults to the current post author.
	 */
	function display_note_form( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_the_author_meta( 'ID' );
		}

		if ( ! bbp_is_single_user_profile() ) {
			$post_id = get_the_ID();
		} else {
			$post_id = 0;
		}

		$note_id    = isset( $_GET['note_id'] ) ? (int) $_GET['note_id'] : 0;
		$user_notes = $this->get_user_notes( $user_id );

		if ( isset( $user_notes[ $note_id ] ) ) {
			$edit_note    = true;
			$note_text    = $user_notes[ $note_id ]->text;
			$button_label = esc_html__( 'Save note', 'wporg-forums' );
		} else {
			$edit_note    = false;
			$note_text    = '';
			$button_label = esc_html__( 'Add your note', 'wporg-forums' );
		}

		$post_permalink = $this->get_user_note_post_permalink( $post_id );
		?>
		<form action="<?php echo esc_url( $post_permalink ); ?>" method="post" class="wporg-bbp-add-user-note">
			<?php wp_nonce_field( sprintf( 'wporg-bbp-add-user-note_%d_%d', $user_id, $post_id ) ); ?>
			<input type="hidden" name="action" value="wporg_bbp_add_user_note">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
			<input type="hidden" name="post_id" value="<?php echo esc_attr( $post_id ); ?>">
			<?php if ( $edit_note ) : ?>
				<input type="hidden" name="note_id" value="<?php echo esc_attr( $note_id ); ?>">
			<?php endif; ?>

			<label for="wporg-bbp-user-note-text" class="screen-reader-text"><?php esc_html_e( 'Note text:', 'wporg-forums' ); ?></label>
			<textarea name="note_text" id="wporg-bbp-user-note-text" cols="40" rows="5"><?php echo esc_textarea( $note_text ); ?></textarea>

			<button type="submit" class="button"><?php echo $button_label; ?></button>
		</form>
		<?php
	}

	/**
	 * Displays existing notes and the form for adding a new note before post content
	 * in topics or replies.
	 */
	function display_user_notes_in_content() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id = get_the_author_meta( 'ID' );
		$post_id = get_the_ID();

		// Only keymasters can see notes on moderators.
		if ( user_can( $user_id, 'moderate' ) && ! current_user_can( 'keep_gate' ) ) {
			return;
		}

		$show_user_notes = isset( $_GET['show_user_notes'] ) && (int) $_GET['show_user_notes'] == $post_id;

		$class = 'wporg-bbp-user-notes';
		if ( ! $show_user_notes ) {
			$class .= ' hidden-notes';
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>" id="wporg-bbp-user-notes-<?php echo esc_attr( $post_id ); ?>">
			<?php $this->display_user_notes( $user_id ); ?>
		</div>
		<?php
	}

	/**
	 * Displays existing notes and the form for adding a new note in user profile.
	 */
	function display_user_notes_in_profile() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id = bbp_get_displayed_user_id();

		// Only keymasters can see notes on moderators.
		if ( user_can( $user_id, 'moderate' ) && ! current_user_can( 'keep_gate' ) ) {
			return;
		}
		?>
		<div class="wporg-bbp-user-notes">
			<h2 id="user-notes" class="entry-title"><?php esc_html_e( 'User Notes', 'wporg-forums' ); ?></h2>
			<div class="bbp-user-section">
				<?php $this->display_user_notes( $user_id ); ?>
			</div>
		</div>
		<?php
	}

}
