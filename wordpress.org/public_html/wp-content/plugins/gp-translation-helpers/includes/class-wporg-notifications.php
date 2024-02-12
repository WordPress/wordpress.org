<?php
/**
 * Routes: WPorg_Notifications class
 *
 * Manages the WPorg notifications in the translation notifications.
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class WPorg_GlotPress_Notifications {
	/**
	 * Emails to receive the comments about typos and asking for feedback in core, patterns, meta and apps.
	 *
	 * @todo Update these emails to the correct ones.
	 *
	 * @since 0.0.2
	 * @var array
	 */
	private static $i18n_email = array(
		'i18n@wordpress.org',
		'i18n2@wordpress.org',
	);

	/**
	 * Adds the hooks to modify the email authors, validators and the email body.
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public static function init() {
		if ( defined( 'WPORG_TRANSLATE_BLOGID' ) && ( get_current_blog_id() === WPORG_TRANSLATE_BLOGID ) ) {
			add_filter(
				'gp_notification_admin_email_addresses',
				function ( $email_addresses, $comment, $comment_meta ) {
					$original               = GP_Notifications::get_original( $comment );
					$email_addresses        = self::get_author_email_address( $original->id );
					$parent_comments        = GP_Notifications::get_parent_comments( $comment->comment_parent );
					$emails_from_the_thread = GP_Notifications::get_commenters_email_addresses( $parent_comments );
					// If one author has a comment in the thread, we don't need to inform to any author, because this author will be notified in the thread.
					if ( ( ! empty( array_intersect( $email_addresses, $emails_from_the_thread ) ) ) || in_array( $comment->comment_author_email, $email_addresses, true ) ) {
						return array();
					}
					return $email_addresses;
				},
				10,
				3
			);
			add_filter(
				'gp_notification_validator_email_addresses',
				function ( $email_addresses, $comment, $comment_meta ) {
					$email_addresses        = self::get_validator_email_addresses( $comment, $comment_meta );
					$parent_comments        = GP_Notifications::get_parent_comments( $comment->comment_parent );
					$emails_from_the_thread = GP_Notifications::get_commenters_email_addresses( $parent_comments );
					// If one validator (GTE/PTE/CLPTE) has a comment in the thread, we don't need to inform to any validator, because this validator will be notified in the thread.
					if ( ! empty( array_intersect( $email_addresses, $emails_from_the_thread ) ) || in_array( $comment->comment_author_email, $email_addresses, true ) ) {
						return array();
					}
					return $email_addresses;
				},
				10,
				3
			);
			add_filter(
				'gp_notification_post_email_body',
				function ( $output, $comment, $comment_meta ) {
					return self::get_email_body( $comment, $comment_meta );
				},
				10,
				3
			);
			add_filter(
				'gp_notification_before_send_emails',
				function ( $email_addresses ) {
					return self::get_opted_in_email_addresses( $email_addresses );
				},
				10,
				1
			);
			add_filter(
				'gp_notification_email_headers',
				function () {
					return array(
						'Content-Type: text/html; charset=UTF-8',
						'From: Translating WordPress.org <no-reply@wordpress.org>',
					);
				}
			);
			add_filter(
				'gp_get_optin_message_for_each_discussion',
				function ( $message, $original_id ) {
					return self::optin_message_for_each_discussion( $original_id );
				},
				10,
				2
			);
			add_filter(
				'gp_mentions_list',
				function( $result, $comments, $locale, $original_id ) {
					$validator_email_addresses  = ( $locale && $original_id ) ? WPorg_GlotPress_Notifications::get_validator_details_for_original_id( $locale, $original_id ) : array();
					$commenters_email_addresses = array_values( GP_Notifications::get_commenters_email_addresses( $comments ) );

					// Remove commenter email if it already exists as a GTE.
					$commenters_email_addresses = array_filter(
						$commenters_email_addresses,
						function( $commenter_email ) use ( $validator_email_addresses ) {
							return ( ! in_array( $commenter_email, array_column( $validator_email_addresses, 'email' ) ) );
						}
					);

					$commenters_email_role = array_map(
						function( $commenter_email ) {
							return(
							array(
								'role'  => 'commenter',
								'email' => $commenter_email,
							)
							);
						},
						$commenters_email_addresses
					);

					$all_email_addresses = array_merge(
						$validator_email_addresses,
						$commenters_email_role
					);

					$current_user       = wp_get_current_user();
					$current_user_email = $current_user->user_email;

					// Find all instances of the logged_in user in the array
					$user_search_result = array_keys( array_column( $all_email_addresses, 'email' ), $current_user_email );

					if ( false !== $user_search_result ) {
						foreach ( $user_search_result as $index ) {
							unset( $all_email_addresses[ $index ] );
						}
						$all_email_addresses = array_values( $all_email_addresses );
					}

					$users = array_map(
						function( $mentionable_user ) {
							$email = $mentionable_user;
							$role  = '';
							if ( is_array( $mentionable_user ) ) {
								$email = $mentionable_user['email'];
								$role  = ! ( 'commenter' === $mentionable_user['role'] ) ? ' - ' . $mentionable_user['role'] : '';
							}

							$user = get_user_by( 'email', $email );
							if ( $user ) {
								return array(
									'ID'            => $user->ID,
									'user_login'    => $user->user_login,
									'user_nicename' => $user->user_nicename . $role,
									'display_name'  => '',
									'source'        => array( 'translators' ),
									'image_URL'     => get_avatar_url( $user->ID ),
								);
							}

							return false;
						},
						$all_email_addresses
					);

					$users = array_filter( $users );

					return $users;
				},
				10,
				4
			);
		}
	}

	/**
	 * Gets the email addresses of all project validators: GTE, PTE and CLPTE.
	 *
	 * Returns an empty array if one GTE/PTE/CLPTE has a comment in the thread,
	 * so only one validators is notified.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return array    The validators' emails.
	 */
	public static function get_validator_email_addresses( WP_Comment $comment, array $comment_meta ): array {
		$locale          = $comment_meta['locale'][0];
		$email_addresses = self::get_gte_email_addresses( $locale );
		$original        = GP_Notifications::get_original( $comment );
		$email_addresses = array_merge( $email_addresses, self::get_pte_email_addresses_by_project_and_locale( $original->id, $locale ) );
		return array_merge( $email_addresses, self::get_clpte_email_addresses_by_project( $original->id ) );
	}

	/**
	 * Gets the email addresses and roles(GTE/PTE/CLPTE) of all project validators: GTE, PTE and CLPTE.
	 *
	 * @since 0.0.2
	 *
	 * @param string $locale  The locale for the translation.
	 * @param int    $original_id  The original id for the string.
	 *
	 * @return array    The emails and roles(GTE/PTE/CLPTE) of the validators.
	 */
	public static function get_validator_details_for_original_id( $locale, $original_id ): array {
		$gtes_email_and_role = array_map(
			function( $gte_email ) {
				return array(
					'role'  => 'GTE',
					'email' => $gte_email,
				);
			},
			self::get_gte_email_addresses( $locale )
		);

		$ptes_email_and_role = array_map(
			function( $pte_email ) {
				return array(
					'role'  => 'GTE',
					'email' => $pte_email,
				);
			},
			self::get_pte_email_addresses_by_project_and_locale( $original_id, $locale )
		);

		$clptes_email_and_role = array_map(
			function( $clpte_email ) {
				return array(
					'role'  => 'GTE',
					'email' => $clpte_email,
				);
			},
			self::get_clpte_email_addresses_by_project( $original_id )
		);

		return array_merge(
			array_merge( $gtes_email_and_role, $ptes_email_and_role ),
			$clptes_email_and_role
		);
	}


	/**
	 * Gets the general translation editors (GTE) emails for the given locale.
	 *
	 * @since 0.0.2
	 *
	 * @param string $locale The locale. E.g. 'zh-tw'.
	 *
	 * @return array The general translation editors (GTE) emails.
	 */
	public static function get_gte_email_addresses( string $locale ): array {
		$email_addresses = array();

		$gp_locale = GP_Locales::by_field( 'slug', $locale );
		if ( ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) ) || ( false === $gp_locale ) ) {
			return $email_addresses;
		}
		$result  = get_sites(
			array(
				'locale'     => $gp_locale->wp_locale,
				'network_id' => WPORG_GLOBAL_NETWORK_ID,
				'path'       => '/',
				'fields'     => 'ids',
				'number'     => '1',
			)
		);
		$site_id = array_shift( $result );
		if ( ! $site_id ) {
			return $email_addresses;
		}

		$users = get_users(
			array(
				'blog_id'     => $site_id,
				'role'        => 'general_translation_editor',
				'count_total' => false,
			)
		);
		foreach ( $users as $user ) {
			$email_addresses[] = $user->data->user_email;
		}

		return $email_addresses;
	}

	/**
	 * Gets the project translation editors (PTE) emails for the given translation_id (from a project) and locale.
	 *
	 * @since 0.0.2
	 *
	 * @param int    $original_id The id of the original string used for the discussion.
	 * @param string $locale  The locale. E.g. 'zh-tw'.
	 *
	 * @return array The project translation editors (PTE) emails.
	 */
	public static function get_pte_email_addresses_by_project_and_locale( int $original_id, string $locale ): array {
		return self::get_pte_clpte_email_addresses_by_project_and_locale( $original_id, $locale );
	}

	/**
	 * Gets the cross language project translation editors (CLPTE) emails for the given translation_id (from a project).
	 *
	 * @since 0.0.2
	 *
	 * @param int $original_id The id of the original string used for the discussion.
	 *
	 * @return array The cross language project translation editors (CLPTE) emails.
	 */
	public static function get_clpte_email_addresses_by_project( int $original_id ): array {
		return self::get_pte_clpte_email_addresses_by_project_and_locale( $original_id, 'all-locales' );
	}

	/**
	 * Gets the PTE/CLPTE emails for the given translation_id (from a project) and locale.
	 *
	 * @since 0.0.2
	 *
	 * @param int    $original_id The id of the original string used for the discussion.
	 * @param string $locale      The locale. E.g. 'zh-tw'.
	 *
	 * @return array The PTE/CLPTE emails for the project and locale.
	 */
	private static function get_pte_clpte_email_addresses_by_project_and_locale( int $original_id, string $locale ): array {
		global $wpdb;

		if ( 'all-locales' === $locale ) {
			$gp_locale = 'all-locales';
		} else {
			$gp_locale = GP_Locales::by_field( 'slug', $locale );
		}

		if ( ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) ) || ( false === $gp_locale ) ) {
			return array();
		}

		$project = self::get_project_from_original_id( $original_id );
		if ( ! $project ) {
			return array();
		}
		// todo: remove the deleted users in the SQL query.
		$translation_editors = $wpdb->get_results(
			$wpdb->prepare(
				"
			SELECT
				{$wpdb->wporg_translation_editors}.user_id, 
			    {$wpdb->wporg_translation_editors}.locale
			FROM {$wpdb->wporg_translation_editors}
			WHERE {$wpdb->wporg_translation_editors}.project_id = %d AND
			      {$wpdb->wporg_translation_editors}.locale = %s 
		",
				$project->id,
				$locale
			)
		);

		$email_addresses = array();
		foreach ( $translation_editors as $pte ) {
			$email_addresses[] = get_user_by( 'id', $pte->user_id )->user_email;
		}
		return $email_addresses;
	}

	/**
	 * Gets the email addresses for the author of a theme or a plugin.
	 *
	 * Themes: only one email.
	 * Plugins: all the plugin authors.
	 * Other projects: the special users, available at $i18n_email.
	 *
	 * @param int $original_id The id of the original string used for the discussion.
	 *
	 * @return array The email addresses for the author of a theme or a plugin.
	 */
	public static function get_author_email_address( int $original_id ): array {
		global $wpdb;

		$email_addresses = array();
		$project         = GP_Notifications::get_project_from_original_id( $original_id );
		if ( ! $project ) {
			return array();
		}
		if ( 'wp-themes' === substr( $project->path, 0, 9 ) ) {
			$author = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT post_author 
                            FROM wporg_35_posts 
                            WHERE 
                                post_type = 'repopackage' AND 
                                post_name = %s
                            ",
					$project->slug
				)
			);
			if ( $author ) {
				$author            = get_user_by( 'id', $author->post_author );
				$email_addresses[] = $author->data->user_email;
			}
		}
		if ( 'wp-plugins' === substr( $project->path, 0, 10 ) ) {
			$committers = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT user FROM plugin_2_svn_access WHERE path = %s',
					'/' . $project->slug
				)
			);
			foreach ( $committers as $user_login ) {
				$email_addresses[] = get_user_by( 'login', $user_login )->user_email;
			}
		}
		if ( ! ( ( 'wp-themes' === substr( $project->path, 0, 9 ) ) || ( 'wp-plugins' === substr( $project->path, 0, 10 ) ) ) ) {
			$email_addresses = self::$i18n_email;
		}
		return $email_addresses;
	}

	/**
	 * Creates the email body message.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array|null $comment_meta The meta values for the comment.
	 *
	 * @return string|null The email body message.
	 */
	public static function get_email_body( WP_Comment $comment, ?array $comment_meta ): ?string {
		$project         = self::get_project_from_post_id( $comment->comment_post_ID );
		$original        = self::get_original( $comment->comment_post_ID );
		$url             = GP_Route_Translation_Helpers::get_permalink( $project->path, $original->id );
		$link_to_comment = $url . '#comment-' . $comment->comment_ID;
		$output          = esc_html__( 'Hi:' );
		$output         .= '<br><br>';
		$output         .= wp_kses(
			/* translators: The comment URL where the user can find the comment. */
			sprintf( __( 'There is a <a href="%1$s">new comment in a discussion</a> of the WordPress translation system that may be of interest to you.', 'glotpress' ), $link_to_comment ),
			array(
				'a' => array( 'href' => array() ),
			)
		) . '<br>';
		$output .= esc_html__( 'It would be nice if you have some time to review this comment and reply to it if needed.' );
		$output .= '<br><br>';
		$output .= '- <strong>' . esc_html__( 'Discussion URL: ' ) . '</strong><a href="' . $url . '">' . $url . '</a><br>';
		if ( array_key_exists( 'locale', $comment_meta ) && ( ! empty( $comment_meta['locale'][0] ) ) ) {
			$output .= '- <strong>' . esc_html__( 'Locale: ' ) . '</strong>' . esc_html( $comment_meta['locale'][0] ) . '<br>';
		}
		$output .= '- <strong>' . esc_html__( 'Original string: ' ) . '</strong>' . esc_html( $original->singular ) . '<br>';
		if ( array_key_exists( 'translation_id', $comment_meta ) && $comment_meta['translation_id'][0] ) {
			$translation_id = $comment_meta['translation_id'][0];
			$translation    = GP::$translation->get( $translation_id );
			// todo: add the plurals.
			if ( ! is_null( $translation ) ) {
				$output .= '- <strong>' . esc_html__( 'Translation string: ' ) . '</strong>' . esc_html( $translation->translation_0 ) . '<br>';
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
			$output         .= '- <strong>' . esc_html__( 'Reason(s): ' ) . '</strong>' . esc_html( implode( ', ', $reasons ) ) . '<br>';
		}
		$output .= '- <strong>' . esc_html__( 'Comment: ' ) . '</strong>' . esc_html( $comment->comment_content ) . '</pre>';
		$output .= '<br><br>';
		$output .= esc_html__( 'Have a nice day' );
		$output .= '<br><br>';
		$output .= esc_html__( 'This is an automated message. Please, do not reply directly to this email.' );

		return $output;
	}


	/**
	 * Gets the project the translated string belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param int $post_id The id of the shadow post used for the discussion.
	 *
	 * @return false|GP_Project The project the translated string belongs to.
	 */
	private static function get_project_from_post_id( int $post_id ) {
		$terms = wp_get_object_terms( $post_id, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		$original      = GP::$original->get( $terms[0]->slug );
		$project_id    = $original->project_id;
		$project       = GP::$project->get( $project_id );
		$main_projects = self::get_main_projects();

		// If the parent project is not a main project, get the parent project. We need to do this
		// because we have 3 levels of projects. E.g. wp-plugins->akismet->stable and the PTE are
		// assigned to the second level.
		if ( ( ! is_null( $project->parent_project_id ) ) && ( ! in_array( $project->parent_project_id, $main_projects, false ) ) ) {
			$project = GP::$project->get( $project->parent_project_id );
		}
		return $project;
	}

	/**
	 * Gets the project the original_id belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param int $original_id The id of the original string used for the discussion.
	 *
	 * @return false|GP_Project The project the original_id belongs to.
	 */
	public static function get_project_from_original_id( int $original_id ) {
		$original      = GP::$original->get( $original_id );
		if ( ! $original ) {
			return false;
		}
		$project_id    = $original->project_id;
		$project       = GP::$project->get( $project_id );

		if ( ! $project ) {
			return false;
		}
		$main_projects = self::get_main_projects();

		// If the parent project is not a main project, get the parent project. We need to do this
		// because we have 3 levels of projects. E.g. wp-plugins->akismet->stable and the PTE are
		// assigned to the second level.
		if ( ( ! is_null( $project->parent_project_id ) ) && ( ! in_array( $project->parent_project_id, $main_projects, false ) ) ) {
			$project = GP::$project->get( $project->parent_project_id );
		}
		return $project;
	}

	/**
	 * Gets the id of the main projects without parent projects.
	 *
	 * @since 0.0.2
	 *
	 * @return array The id of the main projects.
	 */
	private static function get_main_projects():array {
		global $wpdb;

		return $wpdb->get_col( "SELECT id FROM {$wpdb->gp_projects} WHERE parent_project_id IS NULL" );
	}

	/**
	 * Gets the original string that the translated string belongs to.
	 *
	 * @since 0.0.2
	 *
	 * @param int $post_id The id of the shadow post used for the discussion.
	 *
	 * @return GP_Thing|false The original string that the translated string belongs to.
	 */
	private static function get_original( int $post_id ) {
		$terms = wp_get_object_terms( $post_id, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
		if ( empty( $terms ) ) {
			return false;
		}

		return GP::$original->get( $terms[0]->slug );
	}

	/**
	 * Gets a list with the opt-in emails.
	 *
	 * @since 0.0.2
	 *
	 * @param array $email_addresses The list of emails to be notified.
	 *
	 * @return array The list of emails with the opt-in enabled.
	 */
	private static function get_opted_in_email_addresses( array $email_addresses ): array {
		foreach ( $email_addresses as $index => $email_address ) {
			if ( ! is_string( $email_address ) || empty( $email_address ) || self::is_global_optout_email_address( $email_address ) ) {
				unset( $email_addresses[ $index ] );
			}
		}
		return array_values( $email_addresses );
	}

	/**
	 * Indicates whether a user is globally opt-out.
	 *
	 * @since 0.0.2
	 *
	 * @param string $email_address The user's email address.
	 *
	 * @return bool Whether a user wis globally opt-out.
	 */
	private static function is_global_optout_email_address( string $email_address ): bool {
		if ( empty( $email_address ) || ! is_email( $email_address ) ) {
			return false;
		}
		$user            = get_user_by( 'email', $email_address );
		$gp_default_sort = get_user_option( 'gp_default_sort', $user->ID );
		if ( 'on' != gp_array_get( $gp_default_sort, 'notifications_optin', 'off' ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Indicates if the given user is a GTE at translate.wordpress.org.
	 *
	 * @since 0.0.2
	 *
	 * @todo Cache the GTE email addresses, because getting it made a lot of queries, slowing down the load time.
	 *
	 * @param WP_User $user A user object.
	 *
	 * @return bool Whether the user is GTE for any of the languages to which the comments in the post belong.
	 */
	public static function is_user_an_wporg_gte( WP_User $user ): bool {
		$locales             = GP_Locales::locales();
		$gte_email_addresses = array();
		foreach ( $locales as $locale ) {
			$gte_email_addresses = array_merge( $gte_email_addresses, self::get_gte_email_addresses( $locale->slug ) );
		}
		if ( empty( array_intersect( array( $user->user_email ), $gte_email_addresses ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Indicates if the given user is PTE for the project and for any of the languages to which the comments in the post belong.
	 *
	 * @since 0.0.2
	 *
	 * @todo Cache the PTE email addresses for each project, because getting it made a lot of queries, slowing down the load time.
	 *
	 * @param int     $original_id The id of the original string used for the discussion.
	 * @param WP_User $user        A user object.
	 *
	 * @return bool Whether the user is PTE for the project and for any of the languages to which the comments in the post belong.
	 */
	public static function is_user_an_wporg_pte_for_the_project( int $original_id, WP_User $user ): bool {
		$locales             = GP_Locales::locales();
		$pte_email_addresses = array();
		foreach ( $locales as $locale ) {
			$pte_email_addresses = array_merge( $pte_email_addresses, self::get_pte_email_addresses_by_project_and_locale( $original_id, $locale->slug ) );
		}
		if ( empty( $pte_email_addresses ) || empty( array_intersect( array( $user->user_email ), $pte_email_addresses ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Indicates if the given user is CLPTE for the project to which the post belong.
	 *
	 * @since 0.0.2
	 *
	 * @param int     $original_id The id of the original string used for the discussion.
	 * @param WP_User $user        A user object.
	 *
	 * @return bool Whether the user is a CLPTE for the project to which the post belong.
	 */
	public static function is_user_an_wporg_clpte_for_the_project( int $original_id, WP_User $user ): bool {
		$clpte_email_addresses = self::get_clpte_email_addresses_by_project( $original_id );
		if ( empty( $clpte_email_addresses ) || empty( array_intersect( array( $user->user_email ), $clpte_email_addresses ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Indicates if the given user is the author for the project to which the post belong.
	 *
	 * Only works with plugins and themes.
	 *
	 * @since 0.0.2
	 *
	 * @param int     $original_id The id of the original string used for the discussion.
	 * @param WP_User $user        A user object.
	 *
	 * @return bool Whether the user is the author for the project to which the post belong.
	 */
	public static function is_user_an_author_of_the_project( int $original_id, WP_User $user ): bool {
		$author_email_addresses = self::get_author_email_address( $original_id );
		if ( empty( $author_email_addresses ) || empty( array_intersect( array( $user->user_email ), $author_email_addresses ) ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Indicates if the given user is a special user for projects different that themes and plugins.
	 *
	 * @since 0.0.2
	 *
	 * @param int     $original_id The id of the original string used for the discussion.
	 * @param WP_User $user        A user object.
	 *
	 * @return bool Whether the user is a special user or not for projects different than themes and plugins.
	 */
	public static function is_an_special_user_in_a_special_project( int $original_id, WP_User $user ):bool {
		$project = self::get_project_from_original_id( $original_id );
		if ( ! $project ) {
			return false;
		}
		if ( 'wp-themes' !== substr( $project->path, 0, 9 ) && ( 'wp-plugins' !== substr( $project->path, 0, 10 ) ) ) {
			if ( empty( self::$i18n_email ) || empty( array_intersect( array( $user->user_email ), self::$i18n_email ) ) ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Indicates if the given user has made a comment in the discussion.
	 *
	 * @since 0.0.2
	 *
	 * @param int     $original_id The id of the original string used for the discussion.
	 * @param WP_User $user        A user object.
	 *
	 * @return bool Whether the user has made a comment in the discussion.
	 */
	private static function is_user_a_commenter_in_the_discussion( int $original_id, WP_User $user ):bool {
		$post_id  = GP_Notifications::get_post_id( $original_id );
		$comments = get_comments(
			array(
				'post_id'            => $post_id,
				'user_id'            => $user->ID,
				'status'             => 'approve',
				'type'               => 'comment',
				'include_unapproved' => array( get_current_user_id() ),
			)
		);
		if ( empty( $comments ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Gets the opt-in/oup-out message to show at the bottom of the discussions at translate.wordpress.org.
	 *
	 * @param int $original_id The id of the original string used for the discussion.
	 *
	 * @return string The message to show at the bottom of the discussions at translate.wordpress.org.
	 */
	public static function optin_message_for_each_discussion( int $original_id ): string {
		$user = wp_get_current_user();

		if ( ! $user->user_email ) {
			return __( "You will not receive notifications because you don't have an e-mail address set." );
		}
		if ( self::is_global_optout_email_address( $user->user_email ) ) {
			$output  = __( 'You will not receive notifications because you have not yet opted-in.' );
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Start receiving notifications.' ) . '</a>';
			return $output;
		}
		if ( GP_Notifications::is_user_opt_out_in_discussion( $original_id, $user ) ) {
			$output  = __( 'You will not receive notifications for this discussion because you have opt-out to get notifications for it. ' );
			$output .= ' <a href="#" class="opt-in-discussion" data-original-id="' . $original_id . '" data-opt-type="optin">' . __( 'Start receiving notifications for this discussion.' ) . '</a>';
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_an_wporg_gte( $user ) ) {
			$output  = __( 'You are going to receive notifications for the questions in your language because you are a GTE. ' );
			$output .= __( 'You will not receive notifications if another GTE or PTE for your language or CLPTE participates in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_an_wporg_pte_for_the_project( $original_id, $user ) ) {
			$output  = __( 'You are going to receive notifications for the questions in your language because you are a PTE. ' );
			$output .= __( 'You will not receive notifications if another GTE or PTE for your language or CLPTE participates in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_an_wporg_clpte_for_the_project( $original_id, $user ) ) {
			$output  = __( 'You are going to receive notifications for the questions because you are a CLPTE. ' );
			$output .= __( 'You will not receive notifications if another GTE or PTE for their language or CLPTE participates in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
			return $output;
		}
		if ( self::is_an_special_user_in_a_special_project( $original_id, $user ) ) {
			$output  = __( 'You are going to receive notifications for some questions (typos and more context) because you are a special user. ' );
			$output .= __( 'You will not receive notifications if another special user participates in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_an_author_of_the_project( $original_id, $user ) ) {
			$output  = __( 'You are going to receive notifications for some questions (typos and more context) because you are an author. ' );
			$output .= __( 'You will not receive notifications if another author participates in a thread where you do not take part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
			return $output;
		}
		if ( self::is_user_a_commenter_in_the_discussion( $original_id, $user ) ) {
			$output  = __( 'You are going to receive notifications for some threads where you have taken part. ' );
			$output .= ' <a href="#" class="opt-out-discussion" data-original-id="' . $original_id . '" data-opt-type="optout">' . __( 'Stop receiving notifications for this discussion.' ) . '</a>';
			$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
			return $output;
		}
		$output  = __( 'You will not receive notifications for this discussion. We will send you notifications as soon as you get involved.' );
		$output .= ' <a href="https://translate.wordpress.org/settings/">' . __( 'Stop receiving notifications.' ) . '</a>';
		return $output;
	}

}
