<?php

namespace WordPressdotorg\Forums;

class User_Notes {

	const META = '_wporg_bbp_user_notes';

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

		add_action( 'bbp_post_request',                     array( $this, 'add_user_note_request' ), 0 ); // Low priority to get below bbp_edit_user_handler().
		add_action( 'bbp_get_request',                      array( $this, 'delete_user_note_request' ) );

		add_action( 'bbp_theme_after_topic_author_details', array( $this, 'display_user_notes_toggle_link' ) );
		add_action( 'bbp_theme_after_reply_author_details', array( $this, 'display_user_notes_toggle_link' ) );

		add_action( 'bbp_theme_before_topic_content',       array( $this, 'display_user_notes_in_content' ) );
		add_action( 'bbp_theme_before_reply_content',       array( $this, 'display_user_notes_in_content' ) );
		add_action( 'bbp_template_after_user_profile',      array( $this, 'display_user_notes_in_profile' ) );
		add_action( 'bbp_user_edit_after',                  array( $this, 'display_user_notes_in_profile_edit' ), 20 );
	}

	/**
	 * Registers scripts and styles.
	 */
	public function enqueue_scripts() {
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
	public function add_user_note_request( $action = '' ) {
		if (
			! current_user_can( 'moderate' ) ||
			! in_array( $action, [ 'bbp-update-user', 'wporg_bbp_add_user_note' ] )
		) {
			return;
		}

		$should_redirect = 'wporg_bbp_add_user_note' === $action;

		$user_id   = isset( $_POST['user_id'] ) ? (int) $_POST['user_id'] : 0;
		$post_id   = isset( $_POST['post_id'] ) ? (int) $_POST['post_id'] : 0;

		$note_id   = isset( $_POST['note_id'] ) ? (int) $_POST['note_id'] : 0;
		$note_text = isset( $_POST['note_text'] ) ? wp_kses( $_POST['note_text'], array( 'a' => array( 'href' => true ) ) ) : '';

		if ( ! $user_id || ! $note_text ) {
			return;
		}

		// Make sure our nonces are in order.
		if ( ! bbp_verify_nonce_request( sprintf( 'wporg-bbp-add-user-note_%d', $user_id ), '_notenonce' ) ) {
			return;
		}

		$this->add_user_note( $user_id, $note_text, $post_id, $note_id );

		if ( $should_redirect ) {
			$redirect_url = set_url_scheme( 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'] );

			// Redirect to clear form data.
			bbp_redirect( $redirect_url );
		}
	}

	/**
	 * Saves a note to a users meta data.
	 * 
	 * @param int    $user_id   The user ID.
	 * @param string $note_text The note text to add.
	 * @param int    $post_id   The support thread this text is related to. Optional.
	 * @param int    $note_id   The note ID to edit. Optional.
	 */
	public function add_user_note( $user_id, $note_text, $post_id = 0, $note_id = 0 ) {
		// Make sure the user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Get an array of existing notes, or create an array if there are none.
		$user_notes = get_user_meta( $user_id, self::META, true );
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
				return false;
			}

			// Save new text for an existing note.
			$user_notes[ $note_id ]->text = $note_text;

			// Add site ID if missing.
			if ( ! isset( $user_notes[ $note_id ]->site_id ) ) {
				$user_notes[ $note_id ]->site_id = get_current_blog_id();
			}
		}

		if ( update_user_meta( $user_id, self::META, $user_notes ) ) {
			// Clear internal cache.
			unset( $this->user_notes[ $user_id ] );
		}

		return true;
	}

	/**
	 * Saves a note to a users meta data. Suffixes to previous user note if by same moderator within a timeframe.
	 * 
	 * @param int    $user_id   The user ID.
	 * @param string $note_text The note text to add.
	 * @param int    $post_id   The support thread this text is related to. Optional.
	 * @param int    $timeframe The timeframe used to determine if the previous note should be updated. Optional. Default 5 minutes.
	 */
	public function add_user_note_or_update_previous( $user_id, $note_text, $post_id = 0, $timeframe = 300 ) {
		// Default to adding a new note..
		$note_id = 0;

		// Add a user note about this action.
		$existing_notes = $this->get_user_notes( $user_id );

		// Check to see if the last note added was from the current user in the last few minutes, and if so, append to it.
		if ( $existing_notes->count ) {
			$last_note_id = array_key_last( $existing_notes->raw );
			$last_note    = $existing_notes->raw[ $last_note_id ];
			if (
				// Note from the current user
				$last_note->moderator === wp_get_current_user()->user_nicename &&
				// ..and created within $timeframe seconds
				absint( time() - strtotime( $last_note->date ) ) <= $timeframe
			) {
				$note_id = $last_note_id;

				// Prefix the existing message.
				$note_text = trim( $last_note->text . "\n\n" . $note_text );
			}
		}

		return $this->add_user_note( $user_id, $note_text, $post_id, $note_id );
	}

	/**
	 * Deletes a previously added note from user's meta data.
	 *
	 * @param string $action Requested action.
	 */
	public function delete_user_note_request( $action = '' ) {
		if ( 'wporg_bbp_delete_user_note' !== $action || ! current_user_can( 'keep_gate' ) ) {
			return;
		}

		$user_id = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : 0;
		$note_id = isset( $_GET['note_id'] ) ? (int) $_GET['note_id'] : 0;

		if ( ! $user_id || ! $note_id ) {
			return;
		}

		// Make sure our nonces are in order.
		if ( ! bbp_verify_nonce_request( sprintf( 'wporg-bbp-delete-user-note_%d_%d', $user_id, $note_id ), '_notenonce' ) ) {
			return;
		}

		$this->delete_user_note( $user_id, $note_id );

		$redirect_url = remove_query_arg( array( 'action', 'user_id', 'note_id', '_notenonce' ) );

		// Redirect to clear URL.
		bbp_redirect( $redirect_url );
	}

	/**
	 * Delete a user note.
	 *
	 * @param int $user_id The user ID.
	 * @param int $note_id The note ID.
	 */
	public function delete_user_note( $user_id, $note_id ) {
		// Make sure the user exists.
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return false;
		}

		// Get an array of existing notes.
		$user_notes = get_user_meta( $user_id, self::META, true );
		if ( ! $user_notes || ! isset( $user_notes[ $note_id ] ) ) {
			return false;
		}

		unset( $user_notes[ $note_id ] );

		// Reindex the array from 1.
		if ( $user_notes ) {
			$user_notes = array_combine( range( 1, count( $user_notes ) ), array_values( $user_notes ) );
		}

		update_user_meta( $user_id, self::META, $user_notes );

		return true;
	}

	/**
	 * Retrieves all notes for a particular user.
	 *
	 * @param int  $user_id               User ID. Defaults to the current post author.
	 * @param bool $display_add_note_form Whether to show the Add New Note form. Default true.
	 * @return array {
	 *     Array of user notes.
	 *
	 *     @type int    $count User notes count.
	 *     @type array  $raw   Array of raw user notes data.
	 *     @type string $html  User notes output.
	 * }
	 */
	public function get_user_notes( $user_id = 0, $display_add_note_form = true ) {
		if ( ! $user_id ) {
			$user_id = get_the_author_meta( 'ID' );
		}

		// Bail early if the notes are already grabbed for this session.
		if ( isset( $this->user_notes[ $user_id ] ) ) {
			return $this->user_notes[ $user_id ];
		}

		$user_notes = get_user_meta( $user_id, self::META, true );
		if ( ! $user_notes ) {
			$user_notes = array();
		}

		$note_id   = isset( $_GET['note_id'] ) ? (int) $_GET['note_id'] : 0;
		$edit_note = isset( $user_notes[ $note_id ] );

		// Don't display the new note form when editing a note.
		if ( $edit_note ) {
			$display_add_note_form = false;
		}

		$this->user_notes[ $user_id ] = (object) array(
			'count' => count( $user_notes ),
			'raw'   => $user_notes,
			'html'  => '',
		);

		foreach ( $user_notes as $key => $note ) {
			$post_site_id       = isset( $note->site_id ) ? (int) $note->site_id : get_current_blog_id();
			$post_permalink     = $this->get_user_note_post_permalink( $note->post_id, $user_id, $post_site_id );
			$redirect_on_delete = $this->get_user_note_post_permalink( get_the_ID(), $user_id, get_current_blog_id() );

			$note_meta = array(
				'author' => sprintf(
					/* translators: 1: User note author's display name, 2: Link to post, 3: Date, 4: Time. */
					__( 'By %1$s on <a href="%2$s">%3$s at %4$s</a>', 'wporg-forums' ),
					sprintf( '<a href="%s">%s</a>',
						esc_url( get_home_url( $post_site_id, "/users/{$note->moderator}/" ) ),
						$note->moderator
					),
					esc_url( $post_permalink ),
					/* translators: Localized date format, see https://www.php.net/date */
					mysql2date( __( 'F j, Y', 'wporg-forums' ), $note->date ),
					/* translators: Localized time format, see https://www.php.net/date */
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
						sprintf( 'wporg-bbp-delete-user-note_%d_%d', $user_id, $key ),
						'_notenonce'
					) ),
					__( 'Delete', 'wporg-forums' )
				);
			}

			$this->user_notes[ $user_id ]->html .= sprintf(
				'<div class="bbp-template-notice warning">%s %s</div>' . "\n",
				apply_filters( 'comment_text', $note->text, null, array() ),
				sprintf( '<p class="wporg-bbp-user-note-meta">%s</p>' . "\n",
					implode( ' | ', $note_meta )
				)
			);

			if ( $edit_note && $key == $note_id ) {
				ob_start();
				$this->display_note_form( $user_id );
				$this->user_notes[ $user_id ]->html .= ob_get_clean();
			}
		}

		if ( ! $user_notes ) {
			$this->user_notes[ $user_id ]->html .= sprintf(
				'<div class="bbp-template-notice info"><p>%s</p></div>',
				esc_html__( 'No notes have been added for this user.', 'wporg-forums' )
			);
		}

		if ( $display_add_note_form ) {
			ob_start();
			$this->display_note_form( $user_id );
			$this->user_notes[ $user_id ]->html .= ob_get_clean();
		}

		return $this->user_notes[ $user_id ];
	}

	/**
	 * Retrieves user notes output for a particular user.
	 *
	 * Replaces '###POST_ID###' and '###POST_PERMALINK###' placeholders
	 * in the note adding/editing form with the current post ID and permalink.
	 *
	 * @param int  $user_id               User ID. Default 0.
	 * @param bool $display_add_note_form Whether to show the Add New Note form. Default true.
	 * @return string User notes output.
	 */
	public function get_user_notes_html( $user_id = 0, $display_add_note_form = true ) {
		$user_notes = $this->get_user_notes( $user_id, $display_add_note_form )->html;

		if ( ! bbp_is_single_user_profile() ) {
			$post_id = get_the_ID();
		} else {
			$post_id = 0;
		}

		$post_permalink = $this->get_user_note_post_permalink( $post_id );

		$user_notes = strtr( $user_notes, array(
			'###POST_ID###'        => esc_attr( $post_id ),
			'###POST_PERMALINK###' => esc_url( $post_permalink ),
		) );

		return $user_notes;
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
	public function get_user_note_post_permalink( $post_id = 0, $user_id = 0, $site_id = 0 ) {
		if ( $site_id ) {
			switch_to_blog( $site_id );
		}

		$post_type = $post_id ? get_post_type( $post_id ) : '';

		if ( 'topic' === $post_type ) {
			$permalink = bbp_get_topic_permalink( $post_id ) . '#post-' . (int) $post_id;
			$permalink = add_query_arg( array(
				'view'            => 'all',
				'show_user_notes' => $post_id,
			), $permalink );
		} elseif ( 'reply' === $post_type ) {
			$permalink = bbp_get_reply_url( $post_id );
			$permalink = add_query_arg( array(
				'view'            => 'all',
				'show_user_notes' => $post_id,
			), $permalink );
		} else {
			$permalink = bbp_get_user_profile_url( $user_id ) . '#user-notes';
		}

		if ( $site_id ) {
			restore_current_blog();
		}

		return $permalink;
	}

	/**
	 * Checks whether the user has any notes.
	 *
	 * @param int $user_id User ID. Defaults to the current post author.
	 * @return bool True if the user has notes, false otherwise.
	 */
	public function has_user_notes( $user_id = 0 ) {
		return (bool) $this->get_user_notes( $user_id )->count;
	}

	/**
	 * Displays the form for adding a new note or editing an existing note.
	 *
	 * @param int $user_id User ID. Defaults to the current post author.
	 */
	public function display_note_form( $user_id = 0 ) {
		if ( ! $user_id ) {
			$user_id = get_the_author_meta( 'ID' );
		}

		$note_id    = isset( $_GET['note_id'] ) ? (int) $_GET['note_id'] : 0;
		$user_notes = $this->get_user_notes( $user_id )->raw;

		if ( isset( $user_notes[ $note_id ] ) ) {
			$edit_note    = true;
			$note_text    = $user_notes[ $note_id ]->text;
			$button_label = esc_html__( 'Save note', 'wporg-forums' );
		} else {
			$edit_note    = false;
			$note_text    = '';
			$button_label = esc_html__( 'Add your note', 'wporg-forums' );
		}
		?>
		<form action="###POST_PERMALINK###" method="post" class="wporg-bbp-add-user-note">
			<?php wp_nonce_field( sprintf( 'wporg-bbp-add-user-note_%d', $user_id ), '_notenonce' ); ?>
			<input type="hidden" name="action" value="wporg_bbp_add_user_note">
			<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
			<input type="hidden" name="post_id" value="###POST_ID###">
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
	 * Displays toggle link for notes to the author area of a post.
	 */
	public function display_user_notes_toggle_link() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id = get_the_author_meta( 'ID' );
		$post_id = get_the_ID();

		// Only super admins can see notes on the current user.
		if ( ! is_super_admin() && $user_id == get_current_user_id() ) {
			return;
		}

		// Only keymasters can see notes on moderators.
		if ( user_can( $user_id, 'moderate' ) && ! current_user_can( 'keep_gate' ) ) {
			return;
		}

		printf( '<div class="wporg-bbp-user-notes-toggle"><a href="#" data-post-id="%d">%s</a></div>',
			esc_attr( $post_id ),
			esc_html(
				/* translators: %d: User notes count. */
				sprintf( __( 'User Notes (%d)', 'wporg-forums' ),
					$this->get_user_notes( $user_id )->count
				)
			)
		);
	}

	/**
	 * Displays existing notes and the form for adding a new note before post content
	 * in topics or replies.
	 */
	public function display_user_notes_in_content() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id = get_the_author_meta( 'ID' );
		$post_id = get_the_ID();

		// Only super admins can see notes on the current user.
		if ( ! is_super_admin() && $user_id == get_current_user_id() ) {
			return;
		}

		// Only keymasters can see notes on moderators.
		if ( user_can( $user_id, 'moderate' ) && ! current_user_can( 'keep_gate' ) ) {
			return;
		}

		$show_user_notes = isset( $_GET['show_user_notes'] ) && (int) $_GET['show_user_notes'] == $post_id;

		$class = 'wporg-bbp-user-notes';

		if ( $this->has_user_notes( $user_id ) ) {
			$class .= ' has-user-notes';
		}

		if ( ! $show_user_notes ) {
			$class .= ' hidden-notes';
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>" id="wporg-bbp-user-notes-<?php echo esc_attr( $post_id ); ?>">
			<?php echo $this->get_user_notes_html( $user_id ); ?>
		</div>
		<?php
	}

	/**
	 * Displays existing notes and the form for adding a new note in user profile.
	 */
	public function display_user_notes_in_profile() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id = bbp_get_displayed_user_id();

		// Only super admins can see notes on the current user.
		if ( ! is_super_admin() && $user_id == get_current_user_id() ) {
			return;
		}

		// Only keymasters can see notes on moderators.
		if ( user_can( $user_id, 'moderate' ) && ! current_user_can( 'keep_gate' ) ) {
			return;
		}
		?>
		<div class="wporg-bbp-user-notes">
			<h2 id="user-notes" class="entry-title"><?php esc_html_e( 'User Notes', 'wporg-forums' ); ?></h2>
			<div class="bbp-user-section">
				<?php echo $this->get_user_notes_html( $user_id ); ?>
			</div>
		</div>
		<?php
	}

	
	/**
	 * Displays existing notes and the form for adding a new note in user edit profile.
	 */
	public function display_user_notes_in_profile_edit() {
		if ( ! current_user_can( 'moderate' ) ) {
			return;
		}

		$user_id = bbp_get_displayed_user_id();

		// Only super admins can see notes on the current user.
		if ( ! is_super_admin() && $user_id == get_current_user_id() ) {
			return;
		}

		// Only keymasters can see notes on moderators.
		if ( user_can( $user_id, 'moderate' ) && ! current_user_can( 'keep_gate' ) ) {
			return;
		}
		?>
		<div class="wporg-bbp-user-notes">
			<h2 id="user-notes" class="entry-title"><?php esc_html_e( 'User Notes', 'wporg-forums' ); ?></h2>
			<div class="bbp-user-section">
				<?php echo $this->get_user_notes_html( $user_id, false ); ?>

				<div class="wporg-bbp-add-user-note">
					<?php wp_nonce_field( sprintf( 'wporg-bbp-add-user-note_%d', $user_id ), '_notenonce' ); ?>
					<input type="hidden" name="user_id" value="<?php echo esc_attr( $user_id ); ?>">
					<label for="wporg-bbp-user-note-text"><?php esc_html_e( 'Add your note', 'wporg-forums' ); ?></label><br>
					<textarea name="note_text" id="wporg-bbp-user-note-text" cols="40" rows="5"></textarea>
				</div>
			</div>
		</div>
		<?php
	}

}
