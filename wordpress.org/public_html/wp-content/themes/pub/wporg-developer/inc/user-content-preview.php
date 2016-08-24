<?php
/**
 * Code Reference user submitted content preview.
 *
 * @package wporg-developer
 */

/**
 * Class to handle user submitted content preview.
 */
class DevHub_Note_Preview {

	/**
	 * Initializer
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'do_init' ) );
	}

	/**
	 * Handles adding hooks to enable previews.
	 */
	public static function do_init() {

		// Ajax actions to process preview
		add_action( "wp_ajax_preview_comment",         array( __CLASS__, "ajax_preview" ) );
		add_action( "wp_ajax_nopriv_preview_comment",  array( __CLASS__, "ajax_preview" ) );

		// Enqueue scripts and styles
		add_action( 'wp_enqueue_scripts',              array( __CLASS__, 'scripts_and_styles' ), 11 );
	}

	/**
	 * Enqueues scripts and styles.
	 */
	public static function scripts_and_styles() {
		if ( is_singular() ) {
			wp_enqueue_script( 'wporg-developer-tabs', get_template_directory_uri() . '/js/tabs.js', array( 'jquery' ), '20160809', true );
			wp_enqueue_script( 'wporg-developer-preview', get_template_directory_uri() . '/js/user-notes-preview.js', array( 'jquery', 'wporg-developer-function-reference', 'wporg-developer-tabs'  ), '20160809', true );
			wp_localize_script( 'wporg-developer-preview', 'wporg_note_preview', array(
				'ajaxurl'       => admin_url( 'admin-ajax.php' ),
				'nonce'         => wp_create_nonce( 'preview_nonce' ),
				'preview'       => __( 'preview note', 'wporg' ),
				'preview_empty' => __( 'Nothing to preview', 'wporg' ),
				'is_admin'      => is_admin(),
			) );
		}
	}

	/**
	 * Ajax action to update the comment preview.
	 */
	public static function ajax_preview( ) {
		check_ajax_referer( 'preview_nonce', 'preview_nonce' );

		if ( ! isset( $_POST['preview_comment'] ) ) {
			wp_send_json_error( array( 'comment' => '' ) );
		}

		$comment = apply_filters( 'pre_comment_content', $_POST['preview_comment'] );
		$comment = wp_unslash( $comment );
		$comment = apply_filters( 'get_comment_text', $comment );
		$comment = apply_filters( 'comment_text', $comment );

		wp_send_json_success( array( 'comment' => $comment ) );
	}

	/**
	 * Captures the comment-preview markup displayed (and populated) below the Add Note form.
	 *
	 * @access public
	 * @static
	 *
	 * @return string Comment preview HTML markup.
	 */
	public static function comment_preview() {
		if ( ! class_exists( 'DevHub_Note_Preview' ) ) {
			return '';
		}

		ob_start();
?>
		<div id='comment-preview' class='tab-section comment byuser depth-1 comment-preview' aria-hidden="true">
			<article class='preview-body comment-body'>
				<div class='preview-content comment-content'></div>
			</article>
		</div>
		<?php

		return ob_get_clean();
	}
}

DevHub_Note_Preview::init();
