<?php

namespace WordPressdotorg\Plugin_Directory\Admin;

use WordPressdotorg\Plugin_Directory\Tools;
use WordPressdotorg\Plugin_Directory\Tools\SVN;
use WordPressdotorg\Plugin_Directory\Tools\Filesystem;
use WordPressdotorg\Plugin_Directory\Email\Plugin_Approved as Plugin_Approved_Email;
use WordPressdotorg\Plugin_Directory\Email\Plugin_Rejected as Plugin_Rejected_Email;
use WordPressdotorg\Plugin_Directory\Admin\Metabox\Reviewer as Reviewer_Metabox;
use WordPressdotorg\Plugin_Directory\Jobs\API_Update_Updater;
use WordPressdotorg\Plugin_Directory\Standalone\Plugins_Info_API;

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
	 * Get the list of allowed status transitions for a given plugin status & post.
	 *
	 * @param string   $post_status Plugin post status.
	 * @param \WP_Post $post        Plugin post object.
	 *
	 * @return array An array of allowed post status transitions.
	 */
	public static function get_allowed_transitions( $post_status, $post ) {
		// NOTE: $post_status and $post->post_status will differ, as it's used during a pre-update hook.
		switch ( $post_status ) {
			case 'new':
				$transitions = array( 'pending', 'approved', 'rejected' );
				break;
			case 'pending':
				$transitions = array( 'approved', 'rejected', 'new' );
				break;
			case 'approved':
				// Plugins move from 'approved' to 'publish' on first commit, but cannot be published manually.
				$transitions = array( 'disabled', 'closed' );
				break;
			case 'rejected':
				$transitions = array();
				// If it was rejected less than a week ago, allow it to be recovered.
				$rejected_date = get_post_meta( $post->ID, '_rejected', true );
				if ( $rejected_date >= strtotime( '-1 week' ) ) {
					$transitions[] = 'pending';
				}
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
		if ( current_user_can( 'plugin_approve', $postarr['ID'] ) && in_array( $postarr['post_status'], self::get_allowed_transitions( $old_status, get_post( $postarr['ID'] ) ) ) ) {
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
				$this->clear_reviewer( $post );
				break;

			case 'rejected':
				$this->save_rejected_reason( $post->ID );
				$this->rejected( $post->ID, $post );
				$this->clear_reviewer( $post );
				break;

			case 'publish':
				$this->clean_closed_date( $post->ID );
				$this->set_translation_status( $post, 'active' );
				$this->clear_reviewer( $post );
				break;

			case 'disabled':
			case 'closed':
				$this->save_close_reason( $post->ID );
				$this->set_translation_status( $post, 'inactive' );
				break;

			case 'pending':
				if ( 'rejected' === $old_status ) {
					$this->restore_rejected_plugin( $post );
				}

			case 'new':
				// If it's moved from Pending to new, unasign.
				if ( 'pending' === $old_status ) {
					$this->clear_reviewer( $post );
				}
		}

		// Record the time a plugin was transitioned into a specific status.
		update_post_meta( $post->ID, "_{$new_status}", time() );

		// Clear any relevant caches.
		$this->flush_caches( $post );
	}

	/**
	 * Updates project status of the plugin on translate.wordpress.org.
	 *
	 * @param \WP_Post $post Post object.
	 * @param string   $status Project status. Accepts 'active' or 'inactive'.
	 */
	public static function set_translation_status( $post, $status ) {
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
		$plugin_author = get_user_by( 'id', $post->post_author );

		// Create SVN repo.
		$this->approved_create_svn_repo( $post, $plugin_author );

		// Grant commit access.
		Tools::grant_plugin_committer( $post->post_name, $plugin_author );

		// Send email.
		$email = new Plugin_Approved_Email( $post, $plugin_author );
		$email->send();

		Tools::audit_log( 'Plugin approved.', $post_id );
	}

	/**
	 * Create a SVN repository for this plugin.
	 *
	 * @param \WP_Post $post          Post object.
	 * @param \WP_User $plugin_author Plugin author.
	 * @return bool
	 */
	public function approved_create_svn_repo( $post, $plugin_author ) {
		$dir = Filesystem::temp_directory( $post->post_name );
		foreach ( array( 'assets', 'tags', 'trunk' ) as $folder ) {
			mkdir( "$dir/$folder", 0777 );
		}

		/*
		 Temporarily disable SVN prefill from ZIP files
		$attachments = get_attached_media( 'application/zip', $post_id );
		if ( $attachments ) {
			$attachment = end( $attachments );

			$unzip_dir = Filesystem::unzip( get_attached_file( $attachment->ID ) );

			$plugin_root = $this->get_plugin_root( $unzip_dir );

			if ( $plugin_root ) {
				rename( $plugin_root, "$dir/trunk" );
			}
		}
		*/

		$result = SVN::import( $dir, 'http://plugins.svn.wordpress.org/' . $post->post_name, sprintf( 'Adding %1$s by %2$s.', $post->post_title, $plugin_author->user_login ) );

		if ( $result['errors'] ) {
			Tools::audit_log( 'Error creating SVN repository: ' . var_export( $result['errors'], true ), $post->ID );
			return false;
		}

		return true;
	}

	/**
	 * Fires when a post is transitioned to 'rejected'.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 */
	public function rejected( $post_id, $post ) {

		// Default data for review.
		$original_permalink = $post->post_name;
		$submission_date    = get_the_modified_date( 'F j, Y', $post_id );

		// Change slug to 'rejected-plugin-name-rejected' to free up 'plugin-name'.
		wp_update_post( array(
			'ID'        => $post_id,
			'post_name' => sprintf( 'rejected-%s-rejected', $post->post_name ),
		) );

		// Update last_updated to now.
		update_post_meta( $post_id, 'last_updated', gmdate( 'Y-m-d H:i:s' ) );

		// Send email.
		$email = new Plugin_Rejected_Email(
			$post,
			$post->post_author,
			[
				'slug'            => $original_permalink,
				'submission_date' => $submission_date,
				'reason'          => sanitize_key( $_POST['rejection_reason'] ?? '' )
			]
		);

		$email->send();
	}

	/**
	 * Restores a rejected plugin.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function restore_rejected_plugin( $post ) {
		$slug = $post->post_name;
		$slug = preg_replace( '!^rejected-(.+)-rejected$!i', '$1', $slug );

		// Change slug back to 'plugin-name'.
		wp_update_post( array(
			'ID'        => $post->ID,
			'post_name' => $slug,
		) );

		delete_post_meta( $post_id, '_rejection_reason' );
		delete_post_meta( $post_id, 'plugin_rejected_date' );
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
		if ( ! isset( $_REQUEST['close_reason'] ) ) {
			return;
		}

		if ( ! current_user_can( 'plugin_close', $post_id ) ) {
			return;
		}

		$close_reason = sanitize_key( $_REQUEST['close_reason'] );

		update_post_meta( $post_id, '_close_reason', $close_reason );
		update_post_meta( $post_id, 'plugin_closed_date', current_time( 'mysql' ) );

		Tools::audit_log( sprintf( 'Plugin closed. Reason: %s', $close_reason ), $post_id );
	}

	/**
	 * Save the reason for rejecting a plugin.
	 *
	 * @param int $post_id Post ID.
	 */
	public function save_rejected_reason( $post_id ) {
		if ( ! isset( $_REQUEST['rejection_reason'] ) ) {
			return;
		}

		if ( ! current_user_can( 'plugin_reject', $post_id ) ) {
			return;
		}

		$rejection_reason = sanitize_key( $_REQUEST['rejection_reason'] );

		update_post_meta( $post_id, '_rejection_reason', $rejection_reason );
		update_post_meta( $post_id, 'plugin_rejected_date', current_time( 'mysql' ) );

		Tools::audit_log( sprintf( 'Plugin rejected. Reason: %s', $rejection_reason ), $post_id );
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

	/**
	 * Clear the assigned reviewer.
	 *
	 * @param \WP_Post $post Post object.
	 */
	public function clear_reviewer( $post ) {
		// Unset the reviewer, but don't log it, as the triggering status changes should've been logged in some form.
		Reviewer_Metabox::set_reviewer( $post, false, false );
	}

	/**
	 * Flush the caches for the plugin.
	 */
	protected function flush_caches( $post ) {
		// Update the API endpoints with the new data
		API_Update_Updater::update_single_plugin( $post->post_name );
		Plugins_Info_API::flush_plugin_information_cache( $post->post_name );
	}
}
