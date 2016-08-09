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
	 * Enqueues scripts and styles.
	 */
	public static function scripts_and_styles() {
		if ( is_singular() ) {
			if ( '0' != get_comments_number() || \DevHub\post_type_has_source_code() || get_query_var( 'is_handbook' ) ) {
				wp_enqueue_script( 'wporg-developer-function-reference', get_template_directory_uri() . '/js/function-reference.js', array( 'jquery', 'syntaxhighlighter-core', 'syntaxhighlighter-brush-php' ), '20160809', true );
				wp_enqueue_style( 'syntaxhighlighter-core' );
				wp_enqueue_style( 'syntaxhighlighter-theme-default' );
			}

			wp_enqueue_script( 'wporg-developer-user-notes', get_template_directory_uri() . '/js/user-notes.js', array( 'quicktags', 'wporg-developer-preview' ), '20160809', true );
			if ( get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}
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
		echo '<p class="comment-form-comment"><label for="comment">' . _x( 'Add Note or Feedback', 'noun', 'wporg' ) . '</label>';
		wp_editor( '', 'comment', array(
			'media_buttons' => false,
			'textarea_name' => 'comment',
			'textarea_rows' => 8,
			'quicktags'     => array(
				'buttons' => 'strong,em,ul,ol,li'
			),
			'teeny'         => true,
			'tinymce'       => false,
		) );
		echo '</p>';
		return ob_get_clean();
	}

} // DevHub_User_Submitted_Content

DevHub_User_Submitted_Content::init();
