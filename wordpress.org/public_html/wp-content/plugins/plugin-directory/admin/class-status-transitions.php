<?php
namespace WordPressdotorg\Plugin_Directory\Admin;
use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;

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
		add_action( 'approved_plugin', array( $this, 'approved' ), 10, 2 );
		add_action( 'rejected_plugin', array( $this, 'rejected' ), 10, 2 );
	}

	/**
	 * Checks permissions before allowing a post_status change for plugins.
	 *
	 * @param array $data    An array of slashed post data.
	 * @param array $postarr An array of sanitized, but otherwise unmodified post data.
	 * @return array
	 */
	public static function can_change_post_status( $data, $postarr ) {
		$old_status = get_post_field( 'post_status', $postarr['ID'] );

		// Keep going if this is not a plugin...
		if ( 'plugin' !== $postarr['post_type'] ) {
			return $data;
		}

		// ...or the status never changed...
		if ( $old_status === $postarr['post_status'] ) {
			return $data;
		}

		// ...or it's a plugin admin...
		if ( current_user_can( 'plugin_approve', $postarr['ID'] ) ) {
			return $data;
		}

		// ...or it's a white-listed status for plugin reviewers.
		if ( current_user_can( 'plugin_review', $postarr['ID'] ) && in_array( $postarr['post_status'], array( 'draft', 'pending' ) ) ) {
			return $data;
		}

		// ...DIE!!!!!
		wp_die( __( 'You do not have permission to assign this post status to a plugin.', 'wporg-plugins' ), '', array(
			'back_link' => true,
		) );
	}

	/**
	 * Fires when a post is transitioned to 'approved'.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function approved( $post_id, $post ) {
		$attachments = get_attached_media( 'application/zip', $post_id );

		// If there is no zip we have nothing to commit. Bail.
		if ( empty( $attachments ) ) {
			return;
		}

		$attachment    = end( $attachments );
		$plugin_author = get_user_by( 'id', $post->post_author );

		// Create SVN repo.
		$dir = Filesystem::temp_directory( $post->post_name );
		$dir = Filesystem::unzip( get_attached_file( $attachment->ID ), $dir );
		foreach ( array( 'assets', 'branches', 'tags', 'trunk' ) as $folder ) {
			mkdir( "$dir/$folder", 0777 );
		}

		$plugin_root = $this->get_plugin_root( $dir );
		// If there is no plugin file we have nothing to commit. Bail.
		if ( empty( $plugin_root ) ) {
			return;
		}
		rename( $plugin_root, "$dir/trunk" );

		SVN::import( $dir, 'http://plugins.svn.wordpress.org/' . $post->post_name, sprintf( 'Adding %1$s by %2$s.', $post->post_title, $plugin_author->user_login ) );

		// Delete zips.
		foreach ( $attachments as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}

		// Grant commit access.
		Tools::grant_plugin_committer( $post->post_name, $plugin_author );

		// Promote author if they don't have access yet.
		if ( ! user_can( $plugin_author, 'plugin_dashboard_access' ) ) {
			$plugin_author->add_role( 'plugin_committer' );
		}

		// Send email.
		$subject = sprintf( __( '[WordPress Plugin Directory] %s has been approved!', 'wporg-plugins' ), $post->post_title );

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

		$content .= __( 'The WordPress Plugin Directory Team', 'wporg-plugins' ) . "\n";
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

		// Delete zips.
		foreach ( get_attached_media( 'application/zip', $post_id ) as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}

		// Send email.
		$email   = get_user_by( 'id', $post->post_author )->user_email;
		$subject = sprintf( __( '[WordPress Plugin Directory] %s has been rejected', 'wporg-plugins' ), $post->post_title );

		/* Translators: Plugin name. */
		$content  = sprintf( __( 'Unfortunately your plugin hosting request for %s has been rejected.', 'wporg-plugins' ), $post->post_title ). "\n\n\n";
		$content .= __( 'The WordPress Plugin Directory Team', 'wporg-plugins' ) . "\n";
		$content .= 'https://make.wordpress.org/plugins';

		wp_mail( $email, $subject, $content, 'From: plugins@wordpress.org' );
	}

	/**
	 * Returns the path to a plugins root directory.
	 *
	 * @param string $dir Directory to search in.
	 * @return string
	 */
	private function get_plugin_root( $dir ) {
		$plugin_root  = '';
		$plugin_files = Filesystem::list_files( $dir, true /* Recursive */, '!\.php$!i' );

		foreach ( $plugin_files as $plugin_file ) {

			// No markup/translation needed.
			$plugin_data = get_plugin_data( $plugin_file, false, false );

			if ( ! empty( $plugin_data['Name'] ) ) {
				$plugin_root = dirname( $plugin_file );
				break;
			}
		}

		return $plugin_root;
	}
}
