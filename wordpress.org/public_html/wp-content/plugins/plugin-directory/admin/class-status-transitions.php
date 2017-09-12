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
	 * Fetch the instance of the Status_Transitions class.
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
	 * Get the list of allowed status transitions for a given plugin.
	 *
	 * @param string $post_status Plugin post status.
	 * @return array An array of allowed post status transitions.
	 */
	public static function get_allowed_transitions( $post_status ) {
		switch ( $post_status ) {
			case 'new':
				$transitions = array( 'pending', 'approved', 'rejected' );
				break;
			case 'pending':
				$transitions = array( 'approved', 'rejected' );
				break;
			case 'approved':
				// Plugins move from 'approved' to 'publish' on first commit, but cannot be published manually.
				$transitions = array( 'disabled', 'closed' );
				break;
			case 'rejected':
				// Rejections cannot be recovered.
				$transitions = array();
				break;
			case 'publish':
				$transitions = array( 'disabled', 'closed' );
				break;
			case 'disabled':
				$transitions = array( 'publish', 'closed' );
				break;
			case 'closed':
				$transitions = array( 'publish', 'disabled' );
				break;
			default:
				$transitions = array( 'new', 'pending' );
				break;
		}

		return $transitions;
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
		if ( current_user_can( 'plugin_approve', $postarr['ID'] ) && in_array( $postarr['post_status'], self::get_allowed_transitions( $old_status ) ) ) {
			return $data;
		}

		// ...or it's a white-listed status for plugin reviewers.
		if ( current_user_can( 'plugin_review', $postarr['ID'] ) && in_array( $postarr['post_status'], array( 'new', 'pending' ) ) ) {
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
		$plugin_author = get_user_by( 'id', $post->post_author );

		// Create SVN repo.
		$dir = Filesystem::temp_directory( $post->post_name );
		foreach ( array( 'assets', 'branches', 'tags', 'trunk' ) as $folder ) {
			mkdir( "$dir/$folder", 0777 );
		}

		/* Temporarily disable SVN prefill from ZIP files
		if ( $attachments ) {
			$attachment = end( $attachments );

			$unzip_dir = Filesystem::unzip( get_attached_file( $attachment->ID ) );

			$plugin_root = $this->get_plugin_root( $unzip_dir );

			if ( $plugin_root ) {
				rename( $plugin_root, "$dir/trunk" );
			}
		}
		*/

		SVN::import( $dir, 'http://plugins.svn.wordpress.org/' . $post->post_name, sprintf( 'Adding %1$s by %2$s.', $post->post_title, $plugin_author->user_login ) );

		// Delete zips.
		foreach ( $attachments as $attachment ) {
			wp_delete_attachment( $attachment->ID, true );
		}

		// Grant commit access.
		Tools::grant_plugin_committer( $post->post_name, $plugin_author );

		// Send email.
		$subject = sprintf( __( '[WordPress Plugin Directory] %s has been approved!', 'wporg-plugins' ), $post->post_title );

		/* translators: 1: plugin name, 2: plugin slug */
		$content  = sprintf( __( 'Congratulations, your plugin hosting request for %1$s has been approved.

Within one hour you will have access to your SVN repository with the WordPress.org username and password you used to log in and submit your request. Your username is case sensitive.

https://plugins.svn.wordpress.org/%2$s

Here are some handy links to help you get started.

WordPress Plugin Directory Guidelines:
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

Using Subversion with the WordPress Plugin Directory:
https://developer.wordpress.org/plugins/wordpress-org/how-to-use-subversion/

FAQ about the WordPress Plugin Directory:
https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/

WordPress Plugin Directory readme.txt standard:
https://wordpress.org/plugins/developers/#readme

A readme.txt validator:
https://wordpress.org/plugins/developers/readme-validator/

Plugin Assets (header images, etc):
https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/

If you have issues or questions, please reply to this email and let us know.

Enjoy!

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins' ),
			$post->post_title,
			$post->post_name
		);

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

		// Prevent recursive calling via wp_update_post().
		remove_action( 'rejected_plugin', array( $this, 'rejected' ), 10 );

		// Change slug to 'rejected-plugin-name-rejected' to free up 'plugin-name'.
		wp_update_post( array(
			'ID'        => $post_id,
			'post_name' => sprintf( 'rejected-%s-rejected', $post->post_name )
		) );

		// Re-add action.
		add_action( 'rejected_plugin', array( $this, 'rejected' ), 10, 2 );

		// Send email.
		$email   = get_user_by( 'id', $post->post_author )->user_email;
		$subject = sprintf( __( '[WordPress Plugin Directory] %s has been rejected', 'wporg-plugins' ), $post->post_title );

		/* translators: 1: plugin name, 2: plugins@wordpress.org */
		$content  = sprintf( __( 'Unfortunately your plugin submission for %1$s has been rejected from the WordPress.org Directory.

If you believe this to be in error, please email %2$s with your plugin attached as a zip and explain why you feel your plugin should be an exception.

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins' ),
			$post->post_title,
			'plugins@wordpress.org'
		);

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
