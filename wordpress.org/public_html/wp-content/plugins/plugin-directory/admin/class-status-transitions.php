<?php
namespace WordPressdotorg\Plugin_Directory\Admin;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;

/**
 * All functionality related to Status Transitions.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin
 */
class Status_Transitions {

	/**
	 * Fetch the instance of the Status Transitions class.
	 */
	public static function instance() {
		static $instance = null;

		return ! is_null( $instance ) ? $instance : $instance = new Status_Transitions();
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'publish_plugin', array( $this, 'publish' ), 10, 2 );
		add_action( 'rejected_plugin', array( $this, 'rejected' ), 10, 2 );
	}

	/**
	 * Fires when a post is transitioned to 'publish'.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function publish( $post_id, $post ) {
		$plugin_author = get_user_by( 'id', $post->post_author );

		// Create SVN repo.
		$svn_dirs = array(
			"{$post->post_name}/",
			"{$post->post_name}/trunk",
			"{$post->post_name}/branches",
			"{$post->post_name}/tags",
			"{$post->post_name}/assets",
		);
		SVN::mkdir( $svn_dirs, array(
			'message' => sprintf( 'Adding %1$s by %2$s.', $post->post_title, $plugin_author->user_login ),
		) );

		// Read zip and add/commit files to svn.
		$attachments   = get_attached_media( 'application/zip', $post_id );
		$attachment_id = $attachments[0]->ID;
		SVN::add( Filesystem::unzip( get_attached_file( $attachment_id ) ) );

		// Delete zip.
		wp_delete_attachment( $attachment_id, true );

		// Grant commit access.
		Tools::grant_plugin_committer( $post->post_name, $plugin_author );

		// Send email.
		$subject = sprintf( __( '[WordPress Plugins] %s has been approved!', 'wporg-plugins' ), $post->post_title );

		/* Translators: Plugin name. */
		$content  = sprintf( __( 'Congratulations, your plugin hosting request for %s has been approved.', 'wporg-plugins' ), $post->post_title ). "\n";

		$content .= __( 'Within one hour, you will have access to your SVN repository with your WordPress.org username and password (the same one you use on the forums).', 'wporg-plugins' ) . "\n";
		$content .= "http://plugins.svn.wordpress.org/{$post->post_name}\n\n";

		$content .= __( 'Here are some handy links to help you get started.', 'wporg-plugins' ) . "\n";
		$content .= __( 'Using Subversion with the WordPress Plugins Directory', 'wporg-plugins' ) . "\n";
		$content .= "https://wordpress.org/plugins/about/svn/\n\n";
		$content .= __( 'FAQ about the WordPress Plugins Directory', 'wporg-plugins' ) . "\n";
		$content .= "https://wordpress.org/plugins/about/faq/\n\n";
		$content .= __( 'WordPress Plugins Directory readme.txt standard', 'wporg-plugins' ) . "\n";
		$content .= "https://wordpress.org/plugins/about/readme.txt\n\n";
		$content .= __( 'readme.txt validator:', 'wporg-plugins' ) . "\n";
		$content .= "https://wordpress.org/plugins/about/validator/\n\n\n";

		$content .= __( 'The WordPress.org Plugins Team', 'wporg-plugins' ) . "\n";
		$content .= 'https://make.wordpress.org/plugins';

		wp_mail( $plugin_author->user_email, $subject, $content, 'From: plugins@wordpress.org' );
	}

	/**
	 * Fires when a post is transitioned to 'rejected'.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function rejected( $post_id, $post ) {
		// Delete zip?

		// Send email.
		$email   = get_user_by( 'id', $post->post_author )->user_email;
		$subject = sprintf( __( '[WordPress Plugins] %s has been rejected', 'wporg-plugins' ), $post->post_title );

		/* Translators: Plugin name. */
		$content  = sprintf( __( 'Unfortunately your plugin hosting request for %s has been rejected.', 'wporg-plugins' ), $post->post_title ). "\n\n\n";
		$content .= __( 'The WordPress.org Plugins Team', 'wporg-plugins' ) . "\n";
		$content .= 'https://make.wordpress.org/plugins';

		wp_mail( $email, $subject, $content, 'From: plugins@wordpress.org' );
	}
}
