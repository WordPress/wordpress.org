<?php
/*
Plugin Name: WordCamp Participation Notifier
Description: Updates a user's WordPress.org profile when they participate in a WordCamp.
Author:      WordCamp Central
Author URI:  http://wordcamp.org
Version:     0.1
License:     GPLv2 or later
*/

class WordCamp_Participation_Notifier {
	const PROFILES_HANDLER_URL = 'https://profiles.wordpress.org/wp-admin/admin-ajax.php';

	/**
	 * Constructor
	 */
	public function __construct() {
		if ( ! in_array( get_current_blog_id(), apply_filters( 'wpn_enabled_blog_ids', array( 206 ) ) ) ) {	// testing.wordcamp.org
			return;
			
			// todo reverse this when going live so that testing cpts on testing.wcorg doesn't affect real profiles.
				// or maybe just put admin_notice on those pages to let admins know if they use real usernames, it'll affect their profiles
		}
		
		add_action( 'transition_post_status', array( $this, 'post_updated' ), 5, 3 );
	}

	/**
	 * Determines how to handle post updates.
	 *
	 * Ignores updates that shouldn't trigger notifications, and routes ones that do to the appropriate notifier.
	 *
	 * This hooks in before the custom post types save their post meta fields, so that we can access the
	 * WordPress.org username from the previous revision and from the current one.
	 * 
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	function post_updated( $new_status, $old_status, $post ) {
		if ( ! $this->is_post_notifiable( $post ) ) {
			return;
		}

		if ( 'publish' == $new_status && 'publish' == $old_status ) {
			$this->published_post_updated( $post );
		} elseif ( 'publish' == $new_status || 'publish' == $old_status ) {
			$this->post_published_or_unpublished( $new_status, $old_status, $post );
		}
	}

	/**
	 * Determines whether or not a post should trigger a notification.
	 *
	 * Note: post_status values also need to be checked, but that is handled by post_updated()
	 * because the logic differs based on the payload being generated.
	 *
	 * @param WP_Post $post The post
	 * @return boolean true if the post can be notified about; false if it can't
	 */
	protected function is_post_notifiable( $post ) {
		$notifiable       = false;
		$valid_post_types = array( 'wcb_speaker', 'wcb_organizer' );

		if ( is_a( $post, 'WP_Post' ) && empty( $post->post_password ) && in_array( $post->post_type, $valid_post_types ) ) {
			$notifiable = true;
		}

		return apply_filters( 'wpn_is_post_notifiable', $notifiable, $post );
	}

	/**
	 * Updates the activity and associations of a profile when the WordPress.org username on a published post changes.
	 * 
	 * @todo The handler doesn't support removing activity, but maybe do that here if support is added.
	 * 
	 * @param WP_Post $post
	 */
	public function published_post_updated( $post ) {
		$previous_user_id = $this->get_wporg_user_id( $post, false );
		$new_user_id      = $this->get_wporg_user_id( $post );
		
		// There is no username, or it hasn't changed, so we don't need to do anything here.
		if ( $previous_user_id === $new_user_id ) {
			return;
		}
		
		// A new username was added, so add the activity and association.
		if ( $new_user_id && ! $previous_user_id ) {
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_activity_payload( $post ) );
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_association_payload( $post, 'add' ) );
		}
		
		// The username was removed, so remove the association.
		if ( ! $new_user_id && $previous_user_id ) {
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_association_payload( $post, 'remove', $previous_user_id ) );
		}

		// The username changed, so remove the association from the previous user and add both the activity and association to the new user.
		if ( $new_user_id && $previous_user_id ) {
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_association_payload( $post, 'remove', $previous_user_id ) );
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_activity_payload( $post ) );
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_association_payload( $post, 'add' ) );
		}
	}

	/**
	 * Adds new activity and associations to a user's profile when custom posts are published, and removes
	 * associations when custom posts are unpublished.
	 *
	 * @todo The handler doesn't support removing activity, but maybe do that here if support is added.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $post
	 */
	public function post_published_or_unpublished( $new_status, $old_status, $post ) {
		if ( 'publish' == $new_status ) {
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_activity_payload( $post ) );
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_association_payload( $post, 'add' ) );
		} elseif( 'publish' == $old_status ) {
			// Get the $user_id from post meta instead of $_POST in case it changed during the unpublish update.
			// This makes sure that the association is removed from the same user that it was originally added to.
			
			$user_id = $this->get_wporg_user_id( $post, false );
			$this->remote_post( self::PROFILES_HANDLER_URL, $this->get_post_association_payload( $post, 'remove', $user_id ) );
		}
	}

	/**
	 * Builds the payload for an activity notification based on a new post
	 * 
	 * @param WP_Post $post
	 * @return array|false
	 */
	protected function get_post_activity_payload( $post ) {
		$activity = false;
		$user_id  = $this->get_wporg_user_id( $post );
		
		if ( $user_id ) {
			$activity = array(
				'action'        => 'wporg_handle_activity',
				'source'        => 'wordcamp',
				'user_id'       => $user_id,
				'wordcamp_name' => get_wordcamp_name(),
				'url'           => site_url(),
			);
	
			switch( $post->post_type ) {
				case 'wcb_speaker':
					$activity['speaker_id']   = $post->ID;
				break;
	
				case 'wcb_organizer':
					$activity['organizer_id'] = $post->ID;
				break;
	
				default:
					$activity = false;
				break;
			}
		}

		return apply_filters( 'wpn_post_activity_payload', $activity, $post, $user_id );
	}

	/**
	 * Build the payload for an association notification based on a new or updated post
	 * 
	 * @param WP_Post $post
	 * @param string  $command 'add' | 'remove'
	 * @param int     $user_id
	 * @return array|false
	 */
	protected function get_post_association_payload( $post, $command, $user_id = null ) {
		$association = false;
		
		if ( ! $user_id ) {
			$user_id = $this->get_wporg_user_id( $post );
		}

		if ( $user_id ) {
			$association = array(
				'action'        => 'wporg_handle_association',
				'source'        => 'wordcamp',
				'command'       => $command,
				'user_id'       => $user_id,
				'wordcamp_name' => get_wordcamp_name(),
				'url'           => site_url(),
			);
			
			switch( $post->post_type ) {
				case 'wcb_speaker':
					$association['association'] = 'wordcamp_speaker';
				break;
	
				case 'wcb_organizer':
					$association['association'] = 'wordcamp_organizer';
				break;
	
				default:
					$association = false;
				break;
			}
		}

		return apply_filters( 'wpn_post_association_payload', $association, $post, $command, $user_id );
	}
	
	/**
	 * Get the WordPress.org user_id associated with a custom post
	 *
	 * When creating a new post, the user_id may be needed before the post meta is saved,
	 * so we first check the request for the latest value.
	 * 
	 * @param WP_Post $post
	 * @param string $username_field
	 * @return false|int
	 */
	protected function get_wporg_user_id( $post, $username_field = 'wcpt-wporg-username' ) {
		$user_id = false;
		
		if ( $username_field && isset( $_POST[ $username_field ] ) ) {
			$user = get_user_by( 'login', $_POST[ $username_field ] );
			
			if ( ! empty( $user->ID ) ) {
				$user_id = $user->ID;
			}
		} else {
			$user_id = (int) get_post_meta( $post->ID, '_wcpt_user_id', true );
		}
		
		return $user_id;
	}

	/**
	 * Wrapper for wp_remote_post()
	 *
	 * This reduces the amount of duplicated code in the callers, makes them more readable, and logs errors to aid in debugging
	 * 
	 * @param string $url
	 * @param array  $body The value intended to be passed to wp_remote_post() as $args['body']
	 * @return false|array|WP_Error False if a valid $body was not passed; otherwise the results from wp_remote_post()
	 */
	protected function remote_post( $url, $body ) {
		$response = $error = false;

		// todo verify/update when can see actual responses from server
		
		if ( $body ) {
			$response = wp_remote_post( $url, array( 'body' => $body ) );
		
			if ( is_wp_error( $response ) ) {
				$error = sprintf(
					'Recieved WP_Error message: %s Request was: %s',
					implode( ', ', $response->get_error_messages() ),
					print_r( $body, true )
				);
			} elseif ( 200 != $response['response']['code'] || 1 != (int) $response['body'] ) {
				// trigger_error() has a message limit of 1024 bytes, so we truncate $response['body'] to make sure that $body doesn't get truncated.
				
				$error = sprintf(
					'Recieved HTTP code: %s and body: %s. Request was: %s',
					$response['response']['code'],
					substr( sanitize_text_field( $response['body'] ), 0, 500 ),
					print_r( $body, true )
				);
			}
			
			if ( $error ) {
				trigger_error( sprintf( '%s error: %s', __METHOD__, sanitize_text_field( $error ) ), E_USER_WARNING );
			}
		}
		
		return $response;
	}
}

$GLOBALS['WordCamp_Participation_Notifier'] = new WordCamp_Participation_Notifier();
