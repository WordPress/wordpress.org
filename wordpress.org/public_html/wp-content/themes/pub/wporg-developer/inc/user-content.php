<?php
/**
 * Code Reference user submitted content (comments, notes, etc).
 *
 * @package wporg-developer
 */

/**
 * Class to handle user submitted content.
 */
class DevHub_User_Submitted_Content {

	/**
	 * Initializer
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Handles adding/removing hooks to enable comments as user contributed notes.
	 */
	public static function do_init() {

		// Disable pings.
		add_filter( 'pings_open',                       '__return_false' );

		// Sets whether submitting notes is open for the user
		add_filter( 'comments_open',                    '\DevHub\\can_user_post_note', 10, 2 );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts',               array( __CLASS__, 'scripts_and_styles' ), 11 );

		// Force comment registration to be true
		add_filter( 'pre_option_comment_registration', '__return_true' );

		// Force comment moderation to be true
		add_filter( 'pre_option_comment_moderation',   '__return_true' );

		// Remove reply to link
		add_filter( 'comment_reply_link',              '__return_empty_string' );

		// Remove cancel reply link
		add_filter( 'cancel_comment_reply_link',       '__return_empty_string' );

		// Disable smilie conversion
		remove_filter( 'comment_text',                 'convert_smilies',    20 );

		// Disable capital_P_dangit
		remove_filter( 'comment_text',                 'capital_P_dangit',   31 );

		// Enable shortcodes for comments
		add_filter( 'comment_text',                    'do_shortcode');

		// Customize allowed tags
		add_filter( 'wp_kses_allowed_html',            array( __CLASS__, 'wp_kses_allowed_html' ), 10, 2 );

		// Make 'php' the default language
		add_filter( 'syntaxhighlighter_shortcodeatts', array( __CLASS__, 'syntaxhighlighter_shortcodeatts' ) );

		// Tweak code contained in shortcode
		add_filter( 'syntaxhighlighter_precode',       array( __CLASS__, 'syntaxhighlighter_precode' ) );

		// Allowed HTML for a new child comment
		add_filter( 'preprocess_comment',              array( __CLASS__, 'comment_new_allowed_html' ) );

		// Allowed HTML for an edited child comment (There is no decent hook to filter child comments only)
		add_action( 'edit_comment',                    array( __CLASS__, 'comment_edit_allowed_html' ) );

	}

	/**
	 * Customizes the allowed HTML tags for comments.
	 *
	 * @param array  $allowed List of allowed tags and their allowed attributes.
	 * @param string $context The context for which to retrieve tags.
	 * @return array
	 */
	public static function wp_kses_allowed_html( $allowed, $context ) {
		// Unfortunately comments don't have a specific context, so apply to any context not explicitly known.
		if ( ! in_array( $context, array( 'post', 'user_description', 'pre_user_description', 'strip', 'entities', 'explicit' ) ) ) {
			foreach ( array( 'ol', 'ul', 'li' ) as $tag ) {
				$allowed[ $tag ] = array();
			}
		}

		return $allowed;
	}

	/**
	 * Updates edited child comments with allowed HTML.
	 *
	 * Allowed html is <strong>, <em>, <code> and <a>.
	 *
	 * @param int $comment_ID Comment ID.
	 */
	public static function comment_edit_allowed_html( $comment_ID ) {

		// Get the edited comment.
		$comment = get_comment( $comment_ID, ARRAY_A );
		if ( ! $comment ) {
			return;
		}

		if ( ( 0 === (int) $comment['comment_parent'] ) || empty( $comment['comment_content'] ) ) {
			return;
		}

		$content = $comment['comment_content'];
		$data    = self::comment_new_allowed_html( $comment );

		if ( $data['comment_content'] !== $content ) {
			$commentarr = array(
				'comment_ID' => $data['comment_ID'],
				'comment_content' => $data['comment_content']
			);

			// Update comment content.
			wp_update_comment( $commentarr );
		}
	}

	/**
	 * Filter new child comments content with allowed HTML.
	 *
	 * Allowed html is <strong>, <em>, <code> and <a>.
	 *
	 * @param array $commentdata Array with comment data.
	 * @return array Array with filtered comment data.
	 */
	public static function comment_new_allowed_html( $commentdata ) {
		$comment_parent  = isset( $commentdata['comment_parent'] ) ? absint( $commentdata['comment_parent'] ) : 0;
		$comment_content = isset( $commentdata['comment_content'] ) ? trim( $commentdata['comment_content'] ) : '';

		if ( ( $comment_parent === 0 ) || ! $comment_content ) {
			return $commentdata;
		}

		$allowed_html = array(
			'a'      => array(
				'href'   => true,
				'rel'    => true,
				'target' => true,
			),
			'em'     => array(),
			'strong' => array(),
			'code'   => array(),
		);

		$allowed_protocols = array( 'http', 'https' );

		$comment_content = wp_kses( $comment_content, $allowed_html, $allowed_protocols );
		$commentdata['comment_content'] = preg_replace( '/\r?\n|\r/', '', $comment_content );

		return $commentdata;
	}

	/**
	 * Enqueues scripts and styles.
	 */
	public static function scripts_and_styles() {
		if ( is_singular() ) {
			wp_enqueue_script( 'wporg-developer-function-reference', get_template_directory_uri() . '/js/function-reference.js', array( 'jquery', 'syntaxhighlighter-core', 'syntaxhighlighter-brush-php' ), '20160824', true );
			wp_enqueue_style( 'syntaxhighlighter-core' );
			wp_enqueue_style( 'syntaxhighlighter-theme-default' );

			wp_enqueue_script( 'wporg-developer-user-notes', get_template_directory_uri() . '/js/user-notes.js', array( 'jquery', 'quicktags' ), '20180323', true );
			wp_enqueue_script( 'wporg-developer-user-notes-feedback', get_template_directory_uri() . '/js/user-notes-feedback.js', array( 'jquery', 'quicktags' ), '20180323', true );
			wp_localize_script( 'wporg-developer-user-notes-feedback', 'wporg_note_feedback', array(
				'show' => __( 'Show Feedback', 'wporg' ),
				'hide' => __( 'Hide Feedback', 'wporg' ),
			) );
		}
	}

	/**
	 * Sets the default language for SyntaxHighlighter shortcode.
	 *
	 * @param  array $atts Shortcode attributes.
	 * @return array
	 */
	public static function syntaxhighlighter_shortcodeatts( $atts ) {
		$atts['language'] = 'php';
		return $atts;
	}

	/**
	 * Subverts capital_P_dangit for SyntaxHighlighter shortcode.
	 *
	 * @param  string $code
	 * @return string
	 */
	public static function syntaxhighlighter_precode( $code ) {
		return str_replace( 'Wordpress', 'Word&#112;ress', $code );
	}

	/**
	 * Capture an {@see wp_editor()} instance as the 'User Contributed Notes' comment form.
	 *
	 * Uses output buffering to capture the editor instance for use with the {@see comments_form()}.
	 *
	 * @return string HTML output for the wp_editor-ized comment form.
	 */
	public static function wp_editor_comments() {
		ob_start();
		echo '<h3><label for="comment">' . _x( 'Add Note or Feedback', 'noun', 'wporg' ) . '</label></h3>';

		if ( class_exists( 'DevHub_Note_Preview' ) ) {
			echo '<ul class="tablist" style="display:none;">';
			echo '<li><a href="#comment-form-comment">' . __( 'Write', 'wporg' ) . '</a></li>';
			echo '<li><a href="#comment-preview">' . __( 'Preview', 'wporg' ) . '</a></li></ul>';
		}

		$style = '<style type="text/css">';
		ob_start();
		include get_stylesheet_directory() . '/stylesheets/editor-style.css';
		$style .= ob_get_clean();
		$style .=' </style>';

		echo '<div class="comment-form-comment tab-section" id="comment-form-comment">';
		wp_editor( '', 'comment', array(
			'media_buttons' => false,
			'editor_css'    => $style,
			'textarea_name' => 'comment',
			'textarea_rows' => 8,
			'quicktags'     => array(
				'buttons' => 'strong,em,ul,ol,li'
			),
			'teeny'         => true,
			'tinymce'       => false,
		) );
		echo '</div>';
		return ob_get_clean();
	}

	/**
	 * Capture an {@see wp_editor()} instance as the 'User Contributed Notes' feedback form.
	 *
	 * Uses output buffering to capture the editor instance.
	 *
	 * @return string HTML output for the wp_editor-ized feedback form.
	 */
	public static function wp_editor_feedback( $comment, $display = 'show', $content = '' ) {

		if ( ! ( isset( $comment->comment_ID ) && absint( $comment->comment_ID ) ) ) {
			return '';
		}

		$comment_id = absint( $comment->comment_ID );

		static $instance = 0;
		$instance++;

		$display     = ( 'hide' === $display ) ? ' style="display: none;"' : '';
		$title       = __( 'Add feedback to this note', 'wporg' );
		$form_type   = '';
		$button_text = __( 'Add Feedback', 'wporg' );

		if ( $content ) {
			$title       = __( 'Edit feedback', 'wporg' );
			$form_type   = '-edit';
			$button_text = __( 'Edit Feedback', 'wporg' );
		}

		$allowed_tags = '';
		foreach ( array( '<strong>', '<em>', '<code>', '<a>' ) as $tag ) {
			$allowed_tags .= '<code>' . htmlentities( $tag ) . '</code>, ';
		}

		ob_start();
		echo "<div id='feedback-editor-{$comment_id}' class='feedback-editor'{$display}>\n";
		echo "<p class='feedback-editor-title'>{$title}</p>\n";
		echo '<form id="feedback-form-' . $instance . $form_type . '" class="feedback-form" method="post" action="' . site_url( '/wp-comments-post.php' ) . '" name="feedback-form-' . $instance ."\">\n";

		wp_editor( '', 'feedback-' . $instance, array(
			'media_buttons' => false,
			'textarea_name' => 'comment',
			'textarea_rows' => 3,
			'quicktags'     => array(
				'buttons' => 'strong,em'
			),
			'teeny'         => true,
			'tinymce'       => false,
		) );

		echo '<p><strong>' . __( 'Note', 'wporg' ) . '</strong>: ' . __( 'No newlines allowed', 'wporg' ) . '. ';
		printf( __( 'Allowed tags: %s', 'wporg' ), trim( $allowed_tags, ', ' ) ) . "</p>\n";
		echo "<p><input id='submit-{$instance}' class='submit' type='submit' value='Add Feedback' name='submit-{$instance}'>\n";
		echo "<input type='hidden' name='comment_post_ID' value='" . get_the_ID() . "' id='comment_post_ID-{$instance}' />\n";
		echo "<input type='hidden' name='comment_parent' id='comment_parent-{$instance}' value='{$comment_id}' />\n";
		echo "</p>\n</form>\n</div><!-- #feedback-editor-{$comment_id} -->\n";
		return ob_get_clean();
	}

} // DevHub_User_Submitted_Content

DevHub_User_Submitted_Content::init();
