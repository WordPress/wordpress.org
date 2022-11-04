<?php
/**
 * Routes: GP_Notifications class
 *
 * Manages the notifications of the plugin.
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class GP_Notifications {
	/**
	 * Stores the related comments to the first one when the validator makes a bulk rejection.
	 *
	 * @since 0.0.2
	 * @var array
	 */
	private static array $related_comments = array();
	/**
	 * Sends notifications when a new comment in the discussion is stored using the WP REST API.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment      $comment  Inserted or updated comment object.
	 * @param WP_REST_Request $request  Request object.
	 * @param bool            $creating True when creating a comment, false when updating.
	 *
	 * @return void
	 */
	public static function init( WP_Comment $comment, $request, $creating ) {
		$post = get_post( $comment->comment_post_ID );
		if ( Helper_Translation_Discussion::POST_TYPE === $post->post_type ) {
			if ( ( '1' === $comment->comment_approved ) || ( 'approve' === $comment->comment_approved ) ) {
				$comment_meta = get_comment_meta( $comment->comment_ID );
				if ( ( '0' !== $comment->comment_parent ) ) { // Notify to the thread only if the comment is in a thread.
					self::send_emails_to_thread_commenters( $comment, $comment_meta );
				}
				$root_comment      = self::get_root_comment_in_a_thread( $comment );
				$root_comment_meta = get_comment_meta( $root_comment->comment_ID );
				if ( array_key_exists( 'comment_topic', $root_comment_meta ) ) {
					switch ( $root_comment_meta['comment_topic'][0] ) {
						case 'typo':
						case 'context': // Notify to the GlotPress admins.
							self::send_emails_to_gp_admins( $comment, $comment_meta );
							break;
						case 'question': // Notify to the project validator.
							self::send_emails_to_validators( $comment, $comment_meta );
							break;
					}
				} elseif ( ( '0' === $comment->comment_parent ) ) {  // Notify an approval, rejection or fuzzy without parent comments.
					self::send_action_email_to_translator( $comment, $comment_meta );
				}
			}
		}
	}

	/**
	 * Sends notifications when a comment changes its status to "approved".
	 *
	 * @since 0.0.2
	 *
	 * @param int|string $new_status The new comment status.
	 * @param int|string $old_status The old comment status.
	 * @param WP_Comment $comment    The comment object.
	 *
	 * @return void
	 */
	public static function on_comment_status_change( $new_status, $old_status, WP_Comment $comment ) {
		$post = get_post( $comment->comment_post_ID );
		if ( Helper_Translation_Discussion::POST_TYPE === $post->post_type ) {
			if ( $old_status != $new_status ) {
				if ( ( 'approved' === $new_status ) ) {
					self::init( $comment, '', '' );
				}
			}
		}
	}

	/**
	 * Sends an email to the users that have commented on the thread, except to the last comment author.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return void
	 */
	public static function send_emails_to_thread_commenters( WP_Comment $comment, array $comment_meta ) {
		$parent_comments = self::get_parent_comments( $comment->comment_parent );
		$email_addresses = self::get_commenters_email_addresses( $parent_comments, $comment->comment_author_email );
		/**
		 * Filters the email addresses in a thread.
		 *
		 * @since 0.0.2
		 *
		 * @param array      $email_addresses       The email addresses in the thread.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$email_addresses = apply_filters( 'gp_notification_commenter_email_addresses', $email_addresses, $comment, $comment_meta );
		self::send_emails( $comment, $comment_meta, $email_addresses );
	}

	/**
	 * Sends the action notification (approval, rejection, fuzzy) to the translator.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return void
	 */
	public static function send_action_email_to_translator( WP_Comment $comment, array $comment_meta ) {
		$translation_id = $comment_meta['translation_id'];
		$translation    = GP::$translation->get( $translation_id );
		$translator     = get_user_by( 'id', $translation->user_id );
		if ( false === $translator ) {
			$translator = get_user_by( 'id', $translation->user_id_last_modified );
		}
		if ( $translator ) {
			self::send_emails( $comment, $comment_meta, array( $translator->user_email ) );
		}
	}

	/**
	 * Sends an email to the GlotPress admins.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return bool Whether the email has been sent or not.
	 */
	public static function send_emails_to_gp_admins( WP_Comment $comment, array $comment_meta ) {
		$email_addresses = self::get_admins_email_addresses( $comment, $comment_meta );
		return self::send_emails( $comment, $comment_meta, $email_addresses );
	}

	/**
	 * Sends an email to the all the project validators.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return bool Whether the email has been sent or not.
	 */
	public static function send_emails_to_validators( WP_Comment $comment, array $comment_meta ) {
		$post    = get_post( $comment->comment_post_ID );
		$project = self::get_project_from_post( $post );

		$email_addresses = self::get_validators_email_addresses( $project->path );
		/**
		 * Filters the validators' email addresses.
		 *
		 * @since 0.0.2
		 *
		 * @param array      $email_addresses       The email addresses in the thread.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$email_addresses = apply_filters( 'gp_notification_validator_email_addresses', $email_addresses, $comment, $comment_meta );
		$parent_comments = self::get_parent_comments( $comment->comment_ID ); // Includes the current comment.

		$email_addresses_from_the_thread = self::get_commenters_email_addresses( $parent_comments );
		// If one validator has a comment in the thread, we don't need to inform any other validators.
		if ( true !== empty( array_intersect( $email_addresses, $email_addresses_from_the_thread ) ) ) {
			$email_addresses = array();
		}

		return self::send_emails( $comment, $comment_meta, $email_addresses );
	}

	/**
	 * Returns the comments in the thread, including the last one.
	 *
	 * @since 0.0.2
	 *
	 * @param int $comment_id Last comment of the thread.
	 *
	 * @return array The comments in the thread.
	 */
	public static function get_parent_comments( int $comment_id ): array {
		$comments = array();
		$comment  = get_comment( $comment_id );
		if ( $comment && $comment->comment_parent ) {
			$comments = self::get_parent_comments( $comment->comment_parent );
		}
		if ( ! is_null( $comment ) ) {
			$comments[] = $comment;
		}
		return $comments;
	}

	/**
	 * Gets the emails to be notified from the thread comments.
	 *
	 * Removes the second parameter from the returned array if it is found.
	 *
	 * @since 0.0.2
	 *
	 * @param array       $comments                Array with the parent comments to the posted comment.
	 * @param string|null $email_address_to_ignore Email from the posted comment.
	 *
	 * @return array The emails to be notified from the thread comments.
	 */
	public static function get_commenters_email_addresses( array $comments, string $email_address_to_ignore = null ): array {
		$email_addresses = array();
		foreach ( $comments as $comment ) {
			if ( $email_address_to_ignore !== $comment->comment_author_email ) {
				$email_addresses[ $comment->comment_author_email ] = $comment->comment_author_email;
			}
		}

		return $email_addresses;
	}

	/**
	 * Gets the emails of the validators of a project.
	 *
	 * @since 0.0.2
	 *
	 * @param string $project_path The project path.
	 *
	 * @return array The emails of the validators for the given project.
	 */
	public static function get_validators_email_addresses( string $project_path ): array {
		$email_addresses = array();

		$project = GP::$project->by_path( $project_path );

		$path_to_root = array_slice( $project->path_to_root(), 1 );
		$permissions  = GP::$validator_permission->by_project_id( $project->id );

		$sort_by_locale_slug = function( $x, $y ) {
			return strcmp( $x->locale_slug, $y->locale_slug );
		};
		usort( $permissions, $sort_by_locale_slug );
		$parent_permissions = array();

		foreach ( $path_to_root as $parent_project ) {
			$this_parent_permissions = GP::$validator_permission->by_project_id( $parent_project->id );
			usort( $this_parent_permissions, $sort_by_locale_slug );
			foreach ( $this_parent_permissions as $permission ) {
				$permission->project = $parent_project;
			}
			$parent_permissions = array_merge( $parent_permissions, (array) $this_parent_permissions );
		}

		// We can't join on users table.
		foreach ( array_merge( (array) $permissions, (array) $parent_permissions ) as $permission ) {
			$permission->user  = get_user_by( 'id', $permission->user_id );
			$email_addresses[] = $permission->user->data->user_email;
		}

		return $email_addresses;
	}

	/**
	 * Gets the email addresses of the GlotPress admins.
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return array The GlotPress admins' email addresses.
	 */
	public static function get_admins_email_addresses( WP_Comment $comment, array $comment_meta ):array {
		global $wpdb;
		/**
		 * Filters the validators' emails.
		 *
		 * @since 0.0.2
		 *
		 * @param array      $email_addresses       The email addresses in the thread.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$email_addresses = apply_filters( 'gp_notification_admin_email_addresses', array(), $comment, $comment_meta );
		if ( ! empty( $email_addresses ) ) {
			return $email_addresses;
		}

		try {
			$admin_email_addresses = $wpdb->get_results(
				"SELECT user_email FROM {$wpdb->users}
			INNER JOIN {$wpdb->gp_permissions}
			ON {$wpdb->users}.ID = {$wpdb->gp_permissions}.user_id
			WHERE action='admin'"
			);
		} catch ( Exception $e ) {
			$admin_email_addresses = array();
		}
		foreach ( $admin_email_addresses as $admin ) {
			$email_addresses[] = $admin->user_email;
		}
		$parent_comments                 = self::get_parent_comments( $comment->comment_parent );
		$email_addresses_from_the_thread = self::get_commenters_email_addresses( $parent_comments );
		// If an admin is already involved in the thread, don't notify the other admins.
		if ( true !== empty( array_intersect( $email_addresses, $email_addresses_from_the_thread ) ) || in_array( $comment->comment_author_email, $email_addresses, true ) ) {
			return array();
		}

		return array_unique( $email_addresses );
	}

	/**
	 * Sends an email to all the email addresses.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 * @param array      $email_addresses       The email addresses that will receive the notification.
	 *
	 * @return bool Whether the email has been sent or not.
	 */
	public static function send_emails( WP_Comment $comment, array $comment_meta, array $email_addresses ): bool {
		/**
		 * Filters the email addresses before sending the notifications.
		 *
		 * @since 0.0.2
		 *
		 * @param array $email_addresses The email addresses to be notified.
		 */
		$email_addresses = apply_filters( 'gp_notification_before_send_emails', $email_addresses );
		if ( null === $comment || null === $comment_meta || empty( $email_addresses ) ) {
			return false;
		}
		$original = self::get_original( $comment );
		if ( ! $original ) {
			return false;
		}
		$email_addresses = self::remove_commenter_email_address( $comment, $email_addresses );
		$email_addresses = self::remove_optout_discussion_email_addresses( $original->id, $email_addresses );

		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
		);
		/**
		 * Filters the email headers.
		 *
		 * @since 0.0.2
		 *
		 * @param array $headers The email headers.
		 */
		$headers = apply_filters( 'gp_notification_email_headers', $headers );
		$subject = esc_html__( 'New comment in a translation discussion', 'glotpress' );
		$body    = self::get_email_body( $comment, $comment_meta );
		foreach ( $email_addresses as $bcc ) {
			$headers[] = 'Bcc: ' . $bcc;
		}
		wp_mail( '', $subject, $body, $headers );
		return true;
	}

	/**
	 * Creates the email body message.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return string|null
	 */
	public static function get_email_body( WP_Comment $comment, array $comment_meta ): string {
		$post            = get_post( $comment->comment_post_ID );
		$project         = self::get_project_from_post( $post );
		$original        = self::get_original( $comment );
		$url             = GP_Route_Translation_Helpers::get_permalink( $project->path, $original->id );
		$link_to_comment = $url . '#comment-' . $comment->comment_ID;
		$output          = '';
		/**
		 * Filters the content of the email at the beginning of the function that gets its content.
		 *
		 * @since 0.0.2
		 *
		 * @param string     $output       The content of the email.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$output  = apply_filters( 'gp_notification_pre_email_body', $output, $comment, $comment_meta );
		$output .= esc_html__( 'Hi there,', 'glotpress' );
		$output .= '<br><br>';
		$output .= wp_kses(
			/* translators: The discussion URL where the user can find the comment. */
			sprintf( __( 'There is a <a href="%1$s">new comment in a GlotPress discussion</a> that may be of interest to you.', 'glotpress' ), $link_to_comment ),
			array(
				'a' => array( 'href' => array() ),
			)
		) . '<br/>';
		if ( ! empty( self::$related_comments ) ) {
			$output .= wp_kses(
			/* translators: The number of different translations related with the comment. */
				sprintf( __( 'This comment affects to <strong>%1$d different translations</strong>.', 'glotpress' ), count( self::$related_comments ) + 1 ),
				array(
					'a'      => array( 'href' => array() ),
					'strong' => array(),
				)
			) . '<br/>';
		}
		$output .= '<br>';
		$output .= esc_html__( 'It would be nice if you have some time to review this comment and reply to it if needed.', 'glotpress' );
		$output .= '<br><br>';
		if ( array_key_exists( 'locale', $comment_meta ) && ( ! empty( $comment_meta['locale'][0] ) ) ) {
			/* translators: The translation locale for the comment. */
			$output .= '- ' . wp_kses( sprintf( __( '<strong>Locale:</strong> %s', 'glotpress' ), $comment_meta['locale'][0] ), array( 'strong' => array() ) ) . '<br/>';
		}
		if ( empty( self::$related_comments ) ) { // Only show original and translation strings if we don't have related comments (bulk rejection).
			/* translators: The original string to translate. */
			$output .= '- ' . wp_kses( sprintf( __( '<strong>Original string:</strong> %s', 'glotpress' ), $original->singular ), array( 'strong' => array() ) ) . '<br/>';
			if ( array_key_exists( 'translation_id', $comment_meta ) && $comment_meta['translation_id'][0] ) {
				$translation_id = $comment_meta['translation_id'][0];
				$translation    = GP::$translation->get( $translation_id );
				// todo: add the plurals.
				if ( ! is_null( $translation ) ) {
					/* translators: The translation string. */
					$output .= '- ' . wp_kses( sprintf( __( '<strong>Translation string:</strong> %s', 'glotpress' ), $translation->translation_0 ), array( 'strong' => array() ) ) . '<br/>';
				}
			}
		}
		if ( isset( $comment_meta['reject_reason'][0] ) && ! empty( maybe_unserialize( $comment_meta['reject_reason'][0] ) ) ) {
			$reasons         = array();
			$comment_reasons = Helper_Translation_Discussion::get_comment_reasons();
			$reasons         = array_map(
				function( $reason ) use ( $comment_reasons ) {
					if ( array_key_exists( $reason, $comment_reasons ) ) {
						return $comment_reasons[ $reason ]['name'];
					}
				},
				maybe_unserialize( $comment_meta['reject_reason'][0] )
			);
			/* translators: The reason(s) for rejection. */
			$output .= '- ' . wp_kses( sprintf( __( '<strong>Reason(s):</strong> %s', 'glotpress' ), implode( ', ', $reasons ) ), array( 'strong' => array() ) ) . '<br/>';
		}
		/* translators: The comment made. */
		$output .= '- ' . wp_kses( sprintf( __( '<strong>Comment:</strong> %s', 'glotpress' ), $comment->comment_content ), array( 'strong' => array() ) ) . '<br/>';
		if ( empty( self::$related_comments ) ) {
			$output .= '- ' . __( '<strong>Discussion URL:</strong>' ) . '<br/>';
		} else {
			$output .= '- ' . __( '<strong>Discussion URLs:</strong>' ) . '<br/>';
		}
		$output .= '&nbsp;&nbsp;&nbsp; - ' . wp_kses(
			/* translators: The discussion URL where the user can find the comment. */
			sprintf( __( '<a href="%1$s">%1$s</a>', 'glotpress' ), $url ),
			array(
				'strong' => array(),
				'a'      => array( 'href' => array() ),
			)
		) . '<br/>';
		foreach ( self::$related_comments as $related_comment ) {
			$original = self::get_original( $related_comment );
			$url      = GP_Route_Translation_Helpers::get_permalink( $project->path, $original->id );
			$output  .= '&nbsp;&nbsp;&nbsp; - ' . wp_kses(
				/* translators: The discussion URL where the user can find the comment. */
				sprintf( __( '<a href="%1$s">%1$s</a>', 'glotpress' ), $url ),
				array(
					'strong' => array(),
					'a'      => array( 'href' => array() ),
				)
			) . '<br/>';
		}
		$output .= '<br><br>';
		$output .= esc_html__( 'Have a nice day!', 'glotpress' );
		$output .= '<br><br>';
		$output .= esc_html__( 'This is an automated message. Please, do not reply directly to this email.', 'glotpress' );
		/**
		 * Filters the content of the email at the end of the function that gets its content.
		 *
		 * @since 0.0.2
		 *
		 * @param string     $output       The content of the email.
		 * @param WP_Comment $comment      The comment object.
		 * @param array      $comment_meta The meta values for the comment.
		 */
		$output = apply_filters( 'gp_notification_post_email_body', $output, $comment, $comment_meta );
		return $output;
	}

	/**
	 * Gets the root comment in a thread.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return WP_Comment The root comment in the thread.
	 */
	public static function get_root_comment_in_a_thread( WP_Comment $comment ): WP_Comment {
		$comments = self::get_parent_comments( $comment->comment_ID );
		foreach ( $comments as $item ) {
			if ( 0 === intval( $item->comment_parent ) ) {
				return $item;
			}
		}
		return $comment;
	}

	/**
	 * Removes the commenter email from the emails to be notified.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment         The comment object.
	 * @param array      $email_addresses A list of emails.
	 *
	 * @return array The list of emails without the commenter's email.
	 */
	public static function remove_commenter_email_address( WP_Comment $comment, array $email_addresses ): array {
		$index = array_search( $comment->comment_author_email, $email_addresses, true );
		if ( false !== $index ) {
			unset( $email_addresses[ $index ] );
		}
		return array_values( $email_addresses );
	}

	/**
	 * Removes the opt-out emails in the current discussion.
	 *
	 * @since 0.0.2
	 *
	 * @param int   $original_id     The id of the original string used for the discussion.
	 * @param array $email_addresses A list of emails.
	 *
	 * @return array
	 */
	public static function remove_optout_discussion_email_addresses( int $original_id, array $email_addresses ): array {
		foreach ( $email_addresses as $email_address ) {
			$user            = get_user_by( 'email', $email_address );
			$is_user_opt_out = ! empty(
				get_users(
					array(
						'meta_key'   => 'gp_opt_out',
						'meta_value' => $original_id,
						'include'    => array( $user->ID ),
					)
				)
			);
			if ( $is_user_opt_out ) {
				$index = array_search( $email_address, $email_addresses, true );
				unset( $email_addresses[ $index ] );
			}
		}

		return array_values( $email_addresses );
	}

	/**
	 * Gets the project that the translated string belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Post $post The post object.
	 *
	 * @return GP_Project|bool The project that the translated string belongs to.
	 */
	private static function get_project_from_post( WP_Post $post ) {
		$terms = wp_get_object_terms( $post->ID, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		$original   = GP::$original->get( $terms[0]->slug );
		$project_id = $original->project_id;
		$project    = GP::$project->get( $project_id );

		return $project;
	}

	/**
	 * Adds a related comment (to the first one) when the validator makes a bulk rejection.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The related comment to add.
	 *
	 * @return void
	 */
	public static function add_related_comment( WP_Comment $comment ) {
		self::$related_comments[] = $comment;
	}

	/**
	 * Gets the project the original_id belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param int $original_id The id of the original string used for the discussion.
	 *
	 * @return GP_Project The project the original_id belongs to.
	 */
	public static function get_project_from_original_id( int $original_id ): GP_Project {
		$original = GP::$original->get( $original_id );
		return GP::$project->get( $original->project_id );
	}

	/**
	 * Gets the original string that the translated string belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return GP_Thing|false The original string that the translated string belongs to.
	 */
	public static function get_original( WP_Comment $comment ) {
		$post_id = $comment->comment_post_ID;
		$terms   = wp_get_object_terms( $post_id, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		return GP::$original->get( $terms[0]->slug );
	}

	/**
	 * Gets the post_id for the discussion of an original_id.
	 *
	 * If the post doesn't exist, the result is 0.
	 *
	 * @param int $original_id The id of the original string used for the discussion.
	 *
	 * @return int The post_id for the discussion of an original_id.
	 */
	public static function get_post_id( int $original_id ): int {
			$gp_posts = get_posts(
				array(
					'tax_query'        => array(
						array(
							'taxonomy' => Helper_Translation_Discussion::LINK_TAXONOMY,
							'terms'    => $original_id,
							'field'    => 'slug',
						),
					),
					'post_type'        => Helper_Translation_Discussion::POST_TYPE,
					'posts_per_page'   => 1,
					'post_status'      => Helper_Translation_Discussion::POST_STATUS,
					'suppress_filters' => false,
				)
			);

		return ! empty( $gp_posts ) ? $gp_posts[0]->ID : 0;
	}

	/**
	 * Returns if the given user is an GlotPress admin or not.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_User $user A user object.
	 *
	 * @return bool
	 */
	public static function is_user_an_gp_admin( WP_User $user ): bool {
		global $wpdb;
		try {
			$db_email_addresses = $wpdb->get_results(
				"
			SELECT user_email FROM {$wpdb->users} 
			INNER JOIN {$wpdb->gp_permissions}
			ON {$wpdb->users}.ID = {$wpdb->gp_permissions}.user_id 
			WHERE action='admin'",
				ARRAY_N
			);
			foreach ( $db_email_addresses as $email_address ) {
				$email_addresses[] = $email_address[0];
			}
		} catch ( Exception $e ) {
			$email_addresses = array();
		}
		if ( empty( $email_addresses ) || empty( array_intersect( array( $user->user_email ), $email_addresses ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Returns if the given user is an GlotPress validator for the post or not.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_User $user        A user object.
	 * @param int     $original_id The id of the original string used for the discussion.
	 *
	 * @return bool
	 */
	public static function is_user_an_gp_validator( WP_User $user, int $original_id ): bool {
		$project         = self::get_project_from_original_id( $original_id );
		$email_addresses = self::get_validators_email_addresses( $project->path );
		if ( empty( $email_addresses ) || empty( array_intersect( array( $user->user_email ), $email_addresses ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Indicates whether an e-mail address is opt-out in a discussion.
	 *
	 * @since 0.0.2
	 *
	 * @param int     $original_id The id of the original string used for the discussion.
	 * @param WP_User $user        A user object.
	 *
	 * @return bool True if the user has opt-out, otherwise false.
	 */
	public static function is_user_opt_out_in_discussion( int $original_id, WP_User $user ): bool {
		return ! empty(
			get_users(
				array(
					'meta_key'   => 'gp_opt_out',
					'meta_value' => $original_id,
					'include'    => array( $user->ID ),
				)
			)
		);
	}

	/**
	 * Gets the opt-in/oup-out message to show at the bottom of the discussions.
	 *
	 * @since 0.0.2
	 *
	 * @param int $original_id The id of the original string used for the discussion.
	 *
	 * @return string The opt-in/oup-out message to show at the bottom of the discussions.
	 */
	public static function optin_message_for_each_discussion( int $original_id ): string {
		$post_id = self::get_post_id( $original_id );
		/**
		 * Filters the optin message that will be showed in each discussion.
		 *
		 * @since 0.0.2
		 *
		 * @param string $message     The opt-in/oup-out message to show at the bottom of the discussions.
		 * @param int    $original_id The id of the original string used for the discussion.
		 */
		$message = apply_filters( 'gp_get_optin_message_for_each_discussion', '', $original_id );
		if ( $message ) {
			return $message;
		}
		$user            = wp_get_current_user();
		$is_user_opt_out = self::is_user_opt_out_in_discussion( $original_id, $user );
		if ( ! $is_user_opt_out ) {
			$comments = get_comments(
				array(
					'user_id'            => $user->ID,
					'post_id'            => $post_id,
					'status'             => 'approve',
					'type'               => 'comment',
					'include_unapproved' => array( $user->ID ),
				)
			);
		}

		if ( $is_user_opt_out ) {  // Opt-out user.
			$output  = __( 'You will not receive notifications for this discussion because you have opt-out to get notifications for it. ' );
			$output .= ' <a href="#" class="opt-in-discussion" data-original-id="' . $original_id . '" data-opt-type="optin">' . __( 'Start receiving notifications for this discussion.' ) . '</a>';
			return $output;
		}
		if ( $comments && ( ! self::is_user_an_gp_admin( $user ) ) && ( ! self::is_user_an_gp_validator( $user, $original_id ) ) ) { // Regular user with comments.
			$output  = __( 'You are going to receive notifications for the threads where you have participated. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_an_gp_admin( $user ) && self::is_user_an_gp_validator( $user, $original_id ) ) {  // Admin and validator user.
			$output  = __( 'You are going to receive notifications because you are a GlotPress administrator and a validator for this project and language. ' );
			$output .= __( 'You will not receive notifications if another administrator or another validator participate in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_an_gp_admin( $user ) ) {   // Admin user.
			$output  = __( 'You are going to receive notifications because you are a GlotPress administrator. ' );
			$output .= __( 'You will not receive notifications if another administrator participate in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_an_gp_validator( $user, $original_id ) ) { // Validator user.
			$output  = __( 'You are going to receive notifications because you are a GlotPress validator for this project and language. ' );
			$output .= __( 'You will not receive notifications if another validator participate in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			return $output;
		}
		return __( 'You will not receive notifications for this discussion. We will send you notifications as soon as you get involved.' ); // Regular user without comments.

	}
}
