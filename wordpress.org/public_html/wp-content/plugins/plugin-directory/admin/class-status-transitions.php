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
		add_action( 'transition_post_status', array( $this, 'record_status_change' ), 11, 3 );

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
	 * Records the time a plugin was transitioned into a specific status.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function record_status_change( $new_status, $old_status, $post ) {
		if ( 'plugin' === $post->post_type ) {
			update_post_meta( $post->ID, "_{$new_status}", strtotime( $post->post_modified_gmt ) );
		}
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

		$content .= __( 'Within one hour you will have access to your SVN repository with the WordPress.org username and password you used to log in and submit your request. Your username is case sensitive.', 'wporg-plugins' ) . "\n\n";

		$content .= "http://plugins.svn.wordpress.org/{$post->post_name}\n\n";

		$content .= __( 'Here are some handy links to help you get started.', 'wporg-plugins' ) . "\n\n";

		$content .= __( 'WordPress Plugin Directory Guidelines:', 'wporg-plugins' ) . "\n";
		$content .= "https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/\n\n";

		$content .= __( 'Using Subversion with the WordPress Plugin Directory:', 'wporg-plugins' ) . "\n";
		$content .= "https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/\n\n";

		$content .= __( 'FAQ about the WordPress Plugin Directory:', 'wporg-plugins' ) . "\n";
		$content .= "https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/\n\n";

		$content .= __( 'WordPress Plugin Directory readme.txt standard:', 'wporg-plugins' ) . "\n";
		$content .= "https://wordpress.org/plugins/about/readme.txt\n\n";

		$content .= __( 'A readme.txt validator:', 'wporg-plugins' ) . "\n";
		$content .= "https://wordpress.org/plugins/about/validator/\n\n";

		$content .= __( 'Plugin Assets (header images etc):', 'wporg-plugins' ) . "\n"; 
		$content .= "https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/\n\n";

		$content .= __( 'If you have issues or questions, please reply to this email and let us know.', 'wporg-plugins' ) . "\n\n";

		$content .= __( 'Enjoy!', 'wporg-plugins' ) . "\n\n";

		$content .= __( '-The WordPress Plugin Directory Team', 'wporg-plugins' ) . "\n";
		$content .= 'https://make.wordpress.org/plugins/';

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
		$content  = sprintf( __( 'Unfortunately your plugin submission for %s has been rejected from the WordPress.org Directory.', 'wporg-plugins' ), $post->post_title ). "\n\n\n";
		$content .= sprintf( __( 'If you believe this to be in error, please email %s with your plugin attached as a zip and explain why you feel your plugin should be an exception.', 'wporg-plugins' ), 'plugins@wordpress.org' ). "\n\n\n";
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
