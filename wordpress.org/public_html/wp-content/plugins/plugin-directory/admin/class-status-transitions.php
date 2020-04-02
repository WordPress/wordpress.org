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
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 11, 3 );
		add_action( 'post_updated', array( $this, 'record_owner_change' ), 11, 3 );
	}

	/**
	 * Get the list of allowed status transitions for a given plugin.
	 *
	 * @param string $post_status Plugin post status.
	 *
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
	 *
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
		if ( current_user_can( 'plugin_review', $postarr['ID'] ) && in_array(
			$postarr['post_status'], array(
				'new',
				'pending',
			)
		) ) {
			return $data;
		}

		// ...DIE!!!!!
		wp_die( __( 'You do not have permission to assign this post status to a plugin.', 'wporg-plugins' ), '', array(
			'back_link' => true,
		) );
	}

	/**
	 * Calls the methods that should fire when a plugin is transitioned
	 * to a specific status.
	 *
	 * @param string   $new_status New post status.
	 * @param string   $old_status Old post status.
	 * @param \WP_Post $post       Post object.
	 */
	public function transition_post_status( $new_status, $old_status, $post ) {
		if ( 'plugin' !== $post->post_type ) {
			return;
		}

		// Bail if no status change.
		if ( $old_status === $new_status ) {
			return;
		}

		switch ( $new_status ) {
			case 'approved':
				$this->approved( $post->ID, $post );
				break;

			case 'rejected':
				$this->rejected( $post->ID, $post );
				break;

			case 'publish':
				$this->clean_closed_date( $post->ID );
				$this->set_translation_status( $post, 'active' );
				break;

			case 'disabled':
			case 'closed':
				$this->save_close_reason( $post->ID );
				$this->set_translation_status( $post, 'inactive' );
				break;
		}

		// Record the time a plugin was transitioned into a specific status.
		if ( '0000-00-00 00:00:00' === $post->post_modified_gmt ) {
			// Assume now.
			update_post_meta( $post->ID, "_{$new_status}", time() );
		} else {
			update_post_meta( $post->ID, "_{$new_status}", strtotime( $post->post_modified_gmt ) );
		}
	}

	/**
	 * Updates project status of the plugin on translate.wordpress.org.
	 *
	 * @param \WP_Post $post Post object.
	 * @param string   $status Project status. Accepts 'active' or 'inactive'.
	 */
	public function set_translation_status( $post, $status ) {
		if ( ! defined( 'TRANSLATE_API_INTERNAL_BEARER_TOKEN' ) ) {
			return;
		}

		wp_remote_post( 'https://translate.wordpress.org/wp-json/translate/v1/jobs', array(
			'body'       => json_encode(
				array(
					'timestamp'  => time() + 2 * 60,
					'recurrence' => 'once',
					'hook'       => 'wporg_translate_update_plugin_status',
					'args'       => array(
						array(
							'plugin' => $post->post_name,
							'status' => $status,
						),
					),
				)
			),
			'headers'    => array(
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . TRANSLATE_API_INTERNAL_BEARER_TOKEN,
			),
			'blocking'   => false,
			'user-agent' => 'WordPress.org Plugin Status',
		) );
	}

	/**
	 * Fires when a post is transitioned to 'approved'.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function approved( $post_id, $post ) {
		$attachments   = get_attached_media( 'application/zip', $post_id );
		$plugin_author = get_user_by( 'id', $post->post_author );

		// Create SVN repo.
		$dir = Filesystem::temp_directory( $post->post_name );
		foreach ( array( 'assets', 'branches', 'tags', 'trunk' ) as $folder ) {
			mkdir( "$dir/$folder", 0777 );
		}

		/*
		 Temporarily disable SVN prefill from ZIP files
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

		/* translators: 1: plugin name, 2: plugin author's username, 3: plugin slug */
		$content = sprintf(
			__(
				'Congratulations, your plugin hosting request for %1$s has been approved.

Within one (1) hour your account will be granted commit access to your Subversion (SVN) repository. Your username is %2$s and your password is the one you already use to log in to WordPress.org. Keep in mind, your username is case sensitive and you cannot use your email address to log in to SVN.

https://plugins.svn.wordpress.org/%3$s

Once your account has been added, you will need to upload your code using a SVN client of your choice. We are unable to upload or maintain your code for you.

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

WordPress Plugin Directory Guidelines:
https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/

If you have issues or questions, please reply to this email and let us know.

Enjoy!

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins'
			),
			$post->post_title,
			$plugin_author->user_login,
			$post->post_name
		);

		Tools::audit_log( 'Plugin approved.', $post_id );
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

		// Change slug to 'rejected-plugin-name-rejected' to free up 'plugin-name'.
		wp_update_post( array(
			'ID'        => $post_id,
			'post_name' => sprintf( 'rejected-%s-rejected', $post->post_name ),
		) );

		// Send email.
		$email   = get_user_by( 'id', $post->post_author )->user_email;
		$subject = sprintf( __( '[WordPress Plugin Directory] %s has been rejected', 'wporg-plugins' ), $post->post_title );

		/* translators: 1: plugin name, 2: plugins@wordpress.org */
		$content = sprintf(
			__(
				'Unfortunately your plugin submission for %1$s has been rejected from the WordPress Plugin Directory.

If you believe this to be in error, please email %2$s with your plugin attached as a zip and explain why you feel your plugin should be an exception.

--
The WordPress Plugin Directory Team
https://make.wordpress.org/plugins', 'wporg-plugins'
			),
			$post->post_title,
			'plugins@wordpress.org'
		);

		Tools::audit_log( 'Plugin rejected.', $post_id );
		wp_mail( $email, $subject, $content, 'From: plugins@wordpress.org' );
	}

	/**
	 * Returns the path to a plugins root directory.
	 *
	 * @param string $dir Directory to search in.
	 *
	 * @return string
	 */
	private function get_plugin_root( $dir ) {
		$plugin_root  = '';
		$plugin_files = Filesystem::list_files( $dir, true /* Recursive */, '!\.php$!i', 1 /* Depth */ );

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

	/**
	 * Deletes the plugin closed date meta field.
	 *
	 * @param integer $post_id Post ID.
	 */
	public function clean_closed_date( $post_id ) {
		delete_post_meta( $post_id, 'plugin_closed_date' );
		delete_post_meta( $post_id, '_close_reason' );

		$post = get_post( $post_id );
		if ( $post && 'approved' != $post->post_status ) {
			Tools::audit_log( 'Plugin reopened.', $post_id );
		}
	}

	/**
	 * Save the reason for closing or disabling a plugin.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_close_reason( $post_id ) {
		if ( ! isset( $_POST['close_reason'] ) ) {
			return;
		}

		if ( ! current_user_can( 'plugin_approve', $post_id ) ) {
			return;
		}

		$close_reason = sanitize_key( $_POST['close_reason'] );

		update_post_meta( $post_id, '_close_reason', $close_reason );
		update_post_meta( $post_id, 'plugin_closed_date', current_time( 'mysql' ) );

		Tools::audit_log( sprintf( 'Plugin closed. Reason: %s', $close_reason ), $post_id );
	}

	/**
	 * Records a plugin owner change.
	 *
	 * @param integer $post_id     Post ID.
	 * @param WP_Post $post_after  Post object following the update.
	 * @param WP_Post $post_before Post object before the update.
	 */
	public function record_owner_change( $post_id, $post_after, $post_before ) {
		if ( 'plugin' !== $post_after->post_type ) {
			return;
		}

		if ( $post_after->post_author === $post_before->post_author ) {
			return;
		}

		$new_owner = get_userdata( $post_after->post_author );

		Tools::audit_log( sprintf(
			'Ownership transferred to <a href="%s">%s</a>.',
			esc_url( 'https://profiles.wordpress.org/' . $new_owner->user_nicename .'/' ),
			$new_owner->user_login
		), $post_id );
	}

}
