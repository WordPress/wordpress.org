<?php
/**
 * Code Reference voting for user contributed notes.
 *
 * Any user can vote once on any user contributed note.
 *
 * TODO:
 *   - If a user gets blocked as spam, any vote cast by that user should get removed.
 *
 * @package wporg-developer
 */

/**
 * Class to handle voting for user contributed notes.
 */
class DevHub_User_Contributed_Notes_Voting {

	/**
	 * Meta key name for list of all user IDs that submitted an upvote.
	 *
	 * @var array
	 * @access public
	 */
	public static $meta_upvotes = 'devhub_up_votes';

	/**
	 * Meta key name for list of all user IDs that submitted a downvote.
	 *
	 * @var array
	 * @access public
	 */
	public static $meta_downvotes = 'devhub_down_votes';

	/**
	 * Initializer
	 *
	 * @access public
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Initialization
	 *
	 * @access public
	 */
	public static function do_init() {
		// Save a non-AJAX submitted vote.
		add_action( 'template_redirect',  array( __CLASS__, 'vote_submission' ) );

		// Save AJAX submitted vote.
		add_action( 'wp_ajax_note_vote',  array( __CLASS__, 'ajax_vote_submission' ) );

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'scripts_and_styles' ), 11 );
	}

	/**
	 * Enqueues scripts and styles.
	 *
	 * @access public
	 */
	public static function scripts_and_styles() {
		// Only need to enqueue voting-related resources if there are comments to vote on.
		if ( self::user_can_vote() && is_singular() && '0' != get_comments_number() ) {
			wp_register_script( 'wporg-developer-user-notes-voting', get_template_directory_uri() . '/js/user-notes-voting.js', array(), '20160623', true );
			wp_localize_script( 'wporg-developer-user-notes-voting', 'ajaxurl', admin_url( 'admin-ajax.php' ) );
			wp_enqueue_script( 'wporg-developer-user-notes-voting' );
		}
	}

	/**
	 * Handles vote submission.
	 *
	 * @access public
	 *
	 * @return bool True if vote resulted in success or a change.
	 */
	public static function vote_submission( $redirect = true ) {
		$success = false;

		if ( isset( $_REQUEST['comment'] ) && $_REQUEST['comment']
			&& isset( $_REQUEST['vote'] ) && $_REQUEST['vote'] && in_array( $_REQUEST['vote'], array( 'down', 'up' ) )
			&& isset( $_REQUEST['_wpnonce'] ) && wp_verify_nonce( $_REQUEST['_wpnonce'], 'user-note-vote-' . $_REQUEST['comment'] )
			&& self::user_can_vote( get_current_user_id(), $_REQUEST['comment'] )
		) {
			$success = ( 'down' == $_REQUEST['vote'] ) ?
				self::vote_down( (int) $_REQUEST['comment'], get_current_user_id() ) :
				self::vote_up( (int) $_REQUEST['comment'], get_current_user_id() );

			// Redirect user back to comment unless this was an AJAX request.
			if ( ! isset( $_REQUEST['is_ajax'] ) ) {
				wp_redirect( get_comment_link( $_REQUEST['comment'] ) );
				exit();
			}

		}

		return $success;
	}

	/**
	 * Handles AJAX vote submission.
	 *
	 * @access public
	 *
	 * @return int|string Returns 0 on error or no change; else the markup to be used to replace .user-note-voting
	 */
	public static function ajax_vote_submission() {
		check_ajax_referer( 'user-note-vote-' . $_POST['comment'], $_POST['_wpnonce'] );

		$_REQUEST['is_ajax'] = true;
		// If voting succeeded and resulted in a change, send back full replacement
		// markup.
		if ( self::vote_submission( false ) ) {
			self::show_voting( (int) $_POST['comment'] );
			die();
		}
		die( 0 );
	}

	/**
	 * Returns the list of upvotes for a comment.
	 *
	 * @access public
	 *
	 * @param  int $comment_id The comment ID.
	 * @return array
	 */
	public static function get_comment_upvotes( $comment_id ) {
		return self::get_comment_votes( $comment_id, self::$meta_upvotes );
	}

	/**
	 * Returns the list of downvotes for a comment.
	 *
	 * @access public
	 *
	 * @param  int $comment_id The comment ID.
	 * @return array
	 */
	public static function get_comment_downvotes( $comment_id ) {
		return self::get_comment_votes( $comment_id, self::$meta_downvotes );
	}

	/**
	 * Returns the list of vote for a specific vote type for a comment.
	 *
	 * @access protected
	 *
	 * @param  int    $comment_id The comment ID.
	 * @param  string $field
	 * @return array
	 */
	protected static function get_comment_votes( $comment_id, $field ) {
		$votes = get_comment_meta( $comment_id, $field, true );

		if ( ! $votes ) {
			$votes = array();
		}

		return $votes;
	}

	/**
	 * Determines if the user can vote on user contributed notes.
	 *
	 * By default, the only requirements are:
	 * - the user is logged in.
	 * - the comment must be approvedUse the
	 * filter 'devhub_user_can_vote' to configure custom permissions for the
	 * user and/or the comment.
	 *
	 * @access public
	 *
	 * @param  int  $user_id    Optional. The user ID. If not defined, assumes current user.
	 * @param  int  $comment_id Optional. The comment ID. If not defined, assumes being able to comment generally.
	 * @return bool True if the user can vote.
	 */
	public static function user_can_vote( $user_id = '', $comment_id = '' ) {
		// If no user specified, assume current user.
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Must be a user to vote.
		if ( ! $user_id ) {
			return false;
		}

		$can = true;

		// Comment, if provided, must be approved.
		if ( $comment_id ) {
			$can = ( '1' == get_comment( $comment_id )->comment_approved );
			// Users can't vote on their own comments.
			if ( $can && self::is_current_user_note( $comment_id ) ) {
				$can = false;
			}
		}

		return apply_filters( 'devhub_user_can_vote', $can, $user_id, $comment_id );
	}

	/**
	 * Determines if a note was submitted by the current user.
	 *
	 * @param int   $comment_id The comment ID, or empty to use current comment.
	 * @return bool True if the note was submitted by the current user.
	 */
	public static function is_current_user_note( $comment_id = '' ) {
		if ( ! $comment_id ) {
			global $comment;
			$comment_id = $comment->comment_ID;
		}

		$note    = get_comment( $comment_id );
		$user_id = get_current_user_id();

		if ( ! $note || ! $user_id ) {
			return false;
		}

		if ( (int) $note->user_id === $user_id ) {
			return true;
		}

		return false;
	}

	/**
	 * Has user upvoted the comment?
	 *
	 * @access public
	 *
	 * @param  int    $comment_id The comment ID
	 * @param  int    $user_id    Optional. The user ID. If not defined, assumes current user.
	 * @return bool   True if the user has upvoted the comment.
	 */
	public static function has_user_upvoted_comment( $comment_id, $user_id = '' ) {
		// If no user specified, assume current user.
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Must be logged in to have voted.
		if ( ! $user_id ) {
			return false;
		}

		$upvotes = self::get_comment_upvotes( $comment_id );

		return in_array( $user_id, $upvotes );
	}

	/**
	 * Has user downvoted the comment?
	 *
	 * @access public
	 *
	 * @param  int    $comment_id The comment ID
	 * @param  int    $user_id    Optional. The user ID. If not defined, assumes current user.
	 * @return bool   True if the user has downvoted the comment.
	 */
	public static function has_user_downvoted_comment( $comment_id, $user_id = '' ) {
		// If no user specified, assume current user.
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// Must be logged in to have voted.
		if ( ! $user_id ) {
			return false;
		}

		$downvotes = self::get_comment_downvotes( $comment_id );

		return in_array( $user_id, $downvotes );
	}

	/**
	 * Outputs the voting markup for user contributed note.
	 *
	 * @access public
	 *
	 * @param int $comment_id The comment ID, or empty to use current comment.
	 */
	public static function show_voting( $comment_id = '') {
		if ( ! $comment_id ) {
			global $comment;
			$comment_id = $comment->comment_ID;
		}

		$can_vote     = self::user_can_vote( get_current_user_id(), $comment_id );
		$user_note    = self::is_current_user_note( $comment_id );
		$logged_in    = is_user_logged_in();
		$comment_link = get_comment_link( $comment_id );
		$nonce        = wp_create_nonce( 'user-note-vote-' . $comment_id );
		$disabled_str = __( 'Voting for this note is disabled', 'wporg' );
		$cancel_str   = __( 'Click to cancel your vote', 'wporg' ); 
		$log_in_str   = __( 'You must log in to vote on the helpfulness of this note', 'wporg' );
		$log_in_url   = add_query_arg( 'redirect_to', urlencode( $comment_link ), 'https://login.wordpress.org' );

		if ( ! $can_vote && $user_note ) {
			$disabled_str = __( 'Voting for your own note is disabled', 'wporg' );
		}

		echo '<div class="user-note-voting" data-nonce="' . esc_attr( $nonce ) . '">';

		// Up vote link
		$user_upvoted = self::has_user_upvoted_comment( $comment_id );
		if ( $can_vote ) {
			$cancel = $user_upvoted ? '. ' . $cancel_str . '.' : ''; 
			$title = $user_upvoted ?
				__( 'You have voted to indicate this note was helpful', 'wporg' ) . $cancel :
				__( 'Vote up if this note was helpful', 'wporg' );
			$tag = 'a';
		} else {
			$title = ! $logged_in ? $log_in_str : $disabled_str;
			$tag = $logged_in ? 'span' : 'a';
		}
		echo "<{$tag} "
			. 'class="user-note-voting-up' . ( $user_upvoted ? ' user-voted' : '' )
			. '" title="' . esc_attr( $title )
			. '" data-id="' . esc_attr( $comment_id )
			. '" data-vote="up';
		if ( 'a' === $tag ) {
			$up_url = $logged_in ?
				add_query_arg( array( '_wpnonce' => $nonce , 'comment' => $comment_id, 'vote' => 'up' ), $comment_link ) :
				$log_in_url;
			echo '" href="' . esc_url( $up_url );
		}
		echo '">';
		echo '<span class="dashicons dashicons-arrow-up"></span>';
		echo '<span class="screen-reader-text">' . $title .  '</span>';
		echo "</{$tag}>";

		// Total count
		// Don't indicate a like percentage if no one voted.
		$title = ( 0 == self::count_votes( $comment_id, 'total' ) ) ?
			'' :
			sprintf( __( '%s like this', 'wporg' ), self::count_votes( $comment_id, 'like_percentage' ) . '%' );
		$class = '';
		echo '<span '
			. 'class="user-note-voting-count ' . esc_attr( $class ) . '" '
			. 'title="' . esc_attr( $title ) . '">'
			. '<span class="screen-reader-text">' . __( 'Vote results for this note: ', 'wporg' ) .  '</span>'
			. self::count_votes( $comment_id, 'difference' )
			. '</span>';

		// Down vote link
		$user_downvoted = ( $user_upvoted ? false : self::has_user_downvoted_comment( $comment_id ) );
		if ( $can_vote ) {
			$cancel = $user_downvoted ? '. ' . $cancel_str . '.' : '';
			$title = $user_downvoted ?
				__( 'You have voted to indicate this note was not helpful', 'wporg' ) . $cancel :
				__( 'Vote down if this note was not helpful', 'wporg' );
			$tag = 'a';
		} else {
			$title = ! $logged_in ? $log_in_str : $disabled_str;
			$tag = $logged_in ? 'span' : 'a';
		}
		echo "<{$tag} "
			. 'class="user-note-voting-down' . ( $user_downvoted ? ' user-voted' : '' )
			. '" title="' . esc_attr( $title )
			. '" data-id="' . esc_attr( $comment_id )
			. '" data-vote="down';
		if ( 'a' === $tag ) {
			$down_url = $logged_in ?
				add_query_arg( array( '_wpnonce' => $nonce , 'comment' => $comment_id, 'vote' => 'down' ), $comment_link ) :
				$log_in_url;
			echo '" href="' . esc_url( $down_url );
		}
		echo '">';
		echo '<span class="dashicons dashicons-arrow-down"></span>';
		echo '<span class="screen-reader-text">' . $title .  '</span>';
		echo "</{$tag}>";

		echo '</div>';
	}

	/**
	 * Returns a count relating to the voting.
	 *
	 * Supported $type values:
	 * 'up'              : The total number of upvotes
	 * 'down'            : The total number of downvotes
	 * 'total'           : The total number of votes (upvotes + downvotes)
	 * 'difference'      : The difference between upvotes and downvotes (upvotes - downvotes)
	 * 'like_percentage' : The percentage of total votes that upvoted
	 * 
	 * @access public
	 *
	 * @param  string $type The type of count to return.
	 * @return int    The requested count.
	 */
	public static function count_votes( $comment_id, $type ) {
		// The 'up' count is needed in all cases except for 'down'.
		if ( 'down' != $type ) {
			$up = count( self::get_comment_upvotes( $comment_id ) );
		}
		// The 'down' count is needed in all cases except for 'up'.
		if ( 'up' != $type ) {
			$down = count( self::get_comment_downvotes( $comment_id ) );
		}

		switch ( $type ) {
			case 'up':
				return $up;
			case 'down':
				return $down;
			case 'total':
				return $up + $down;
			case 'difference':
				return $up - $down;
			case 'like_percentage':
				$total = $up + $down;
				// If no votes have been cast, return 0 to avoid dividing by 0.
				if ( 0 == $total ) {
					return 0;
				}
				// More precise, and floatval() will drop ".00" when present
				//return floatval( round( ( $up / $total ) * 100, 2 ) );
				// Less precise; rounds to nearest integer
				return round( ( $up / $total ) * 100 );
		}
	}

	/**
	 * Records an up vote.
	 *
	 * @access public
	 *
	 * @param  int  $comment_id The comment ID
	 * @param  int  $user_id    Optional. The user ID. Default is current user.
	 * @return bool Whether the up vote succeed (a new vote or a change in vote).
	 */
	public static function vote_up( $comment_id, $user_id = '' ) {
		return self::vote_handler( $comment_id, $user_id, 'up' );
	}

	/**
	 * Records a down vote.
	 *
	 * @access public
	 *
	 * @param  int  $comment_id The comment ID
	 * @param  int  $user_id    Optional. The user ID. Default is current user.
	 * @return bool Whether the down vote succeed (a new vote or a change in vote).
	 */
	public static function vote_down( $comment_id, $user_id = '' ) {
		return self::vote_handler( $comment_id, $user_id, 'down' );
	}

	/**
	 * Handles abstraction between an up or down vote.
	 *
	 * @access protected
	 *
	 * @param  int    $comment_id The comment ID
	 * @param  int    $user_id    Optional. The user ID. Default is current user.
	 * @param  string $type       Optional. 'up' for an up vote, 'down' for a down vote. Default is 'up'.
	 * @return bool   Whether the vote succeed (a new vote or a change in vote).
	 */
	protected static function vote_handler( $comment_id, $user_id = '', $type = 'up' ) {
		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// See if the user can vote on this comment.
		$votable = self::user_can_vote( $user_id, $comment_id );

		if ( ! $votable ) {
			return false;
		}

		// The difference between an up vote and a down vote is which meta list their
		// vote was recorded in.
		if ( 'up' == $type ) {
			$add_to      = self::$meta_upvotes;
			$remove_from = self::$meta_downvotes;
		} else {
			$add_to      = self::$meta_downvotes;
			$remove_from = self::$meta_upvotes;
		}

		// Get list of people who cast the same vote.
		$add_to_list = get_comment_meta( $comment_id, $add_to, true );

		// Remove user from list if recasting the same vote as before.
		if ( in_array( $user_id, (array) $add_to_list ) ) {
			unset( $add_to_list[ array_search( $user_id, $add_to_list ) ] );
			update_comment_meta( $comment_id, $add_to, $add_to_list );
			return true;
		}

		// If the user had previously cast the opposite vote, undo that older vote.
		$remove_from_list = (array) get_comment_meta( $comment_id, $remove_from, true );
		if ( in_array( $user_id, $remove_from_list ) ) {
			unset( $remove_from_list[ array_search( $user_id, $remove_from_list ) ] );
			update_comment_meta( $comment_id, $remove_from, $remove_from_list );
		}

		// Add user to the list of people casting the identical vote.
		if ( $add_to_list ) {
			$add_to_list[] = $user_id;
		} else {
			$add_to_list = array( $user_id );
		}
		update_comment_meta( $comment_id, $add_to, $add_to_list );

		// TODO: Store some value (the like_percentage perhaps) in comment_karma so it can be custom sorted?

		return true;
	}

} // DevHub_User_Contributed_Notes_Voting

DevHub_User_Contributed_Notes_Voting::init();
