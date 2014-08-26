<?php
/**
 * Code Reference user submitted content (comments, examples, etc).
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
	 * Handles adding/removing hooks to enable comments as examples.
	 *
	 * Mostly gives users greater permissions in terms of comment content.
	 *
	 * In order to submit code examples, users must be able to post with less restrictions.
	 */
	public static function do_init() {

		// Restricts commenting to logged in users.
		add_filter( 'comments_open',                    array( __CLASS__, 'prevent_invalid_comment_submissions' ), 10, 2 );

		// Sets whether submitting examples is open for the user
		add_filter( 'comments_open',                    '\DevHub\\can_user_post_example', 10, 2 );

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

		// Make 'php' the default language
		add_filter( 'syntaxhighlighter_shortcodeatts', array( __CLASS__, 'syntaxhighlighter_shortcodeatts' ) );

		// Tweak code contained in shortcode
		add_filter( 'syntaxhighlighter_precode',       array( __CLASS__, 'syntaxhighlighter_precode' ) );

	}

	/**
	 * Enqueues scripts and styles.
	 */
	public static function scripts_and_styles() {
		if ( is_singular() && ( '0' != get_comments_number() || \DevHub\post_type_has_source_code() ) ) {
			wp_enqueue_script( 'wporg-developer-function-reference', get_template_directory_uri() . '/js/function-reference.js', array( 'jquery', 'syntaxhighlighter-core', 'syntaxhighlighter-brush-php' ), '20140515', true );
			wp_enqueue_style( 'syntaxhighlighter-core' );
			wp_enqueue_style( 'syntaxhighlighter-theme-default' );

			wp_enqueue_script( 'wporg-developer-code-examples', get_template_directory_uri() . '/js/code-example.js', array(), '20140423', true );
			if ( get_option( 'thread_comments' ) ) {
				wp_enqueue_script( 'comment-reply' );
			}
		}
	}

	/**
	 * Disables commenting to invalid or non-users.
	 *
	 * @param bool  $status Default commenting status for post.
	 * @return bool False if commenter isn't a user, otherwise the passed in status.
	 */
	public static function prevent_invalid_comment_submissions( $status, $post_id ) {
		if ( $_POST && ( ! is_user_logged_in() || ! is_user_member_of_blog() ) ) {
			return false;
		}

		return $status;
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

} // DevHub_User_Submitted_Content

DevHub_User_Submitted_Content::init();
