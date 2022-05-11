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
					return self::get_author_email_address( $comment, $comment_meta );
				},
				10,
				3
			);
			add_filter(
				'gp_notification_validator_email_addresses',
				function ( $email_addresses, $comment, $comment_meta ) {
					return self::get_validator_email_addresses( $comment, $comment_meta );
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
		$locale                 = $comment_meta['locale'][0];
		$email_addresses        = self::get_gte_email_addresses( $locale );
		$email_addresses        = array_merge( $email_addresses, self::get_pte_email_addresses_by_project_and_locale( $comment, $locale ) );
		$email_addresses        = array_merge( $email_addresses, self::get_clpte_email_addresses_by_project( $comment ) );
		$parent_comments        = GP_Notifications::get_parent_comments( $comment->comment_parent );
		$emails_from_the_thread = GP_Notifications::get_commenters_email_addresses( $parent_comments );
		// Set the email addresses array as empty if one GTE/PTE/CLPTE has a comment in the thread.
		if ( ! empty( array_intersect( $email_addresses, $emails_from_the_thread ) ) || in_array( $comment->comment_author_email, $email_addresses, true ) ) {
			return array();
		}
		return $email_addresses;
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
	 * @param WP_Comment $comment The comment object.
	 * @param string     $locale  The locale. E.g. 'zh-tw'.
	 *
	 * @return array The project translation editors (PTE) emails.
	 */
	public static function get_pte_email_addresses_by_project_and_locale( $comment, $locale ): array {
		return self::get_pte_clpte_email_addresses_by_project_and_locale( $comment, $locale );
	}

	/**
	 * Gets the cross language project translation editors (CLPTE) emails for the given translation_id (from a project).
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return array The cross language project translation editors (CLPTE) emails.
	 */
	public static function get_clpte_email_addresses_by_project( $comment ): array {
		return self::get_pte_clpte_email_addresses_by_project_and_locale( $comment, 'all-locales' );
	}

	/**
	 * Gets the PTE/CLPTE emails for the given translation_id (from a project) and locale.
	 *
	 * @since 0.0.2
	 *
	 * @param WP_Comment $comment The comment object.
	 * @param string     $locale  The locale. E.g. 'zh-tw'.
	 *
	 * @return array The PTE/CLPTE emails for the project and locale.
	 */
	private static function get_pte_clpte_email_addresses_by_project_and_locale( WP_Comment $comment, string $locale ): array {
		global $wpdb;

		if ( 'all-locales' === $locale ) {
			$gp_locale = 'all-locales';
		} else {
			$gp_locale = GP_Locales::by_field( 'slug', $locale );
		}

		if ( ( ! defined( 'WPORG_TRANSLATE_BLOGID' ) ) || ( false === $gp_locale ) ) {
			return $array();
		}

		$project = self::get_project_to_translate( $comment );

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
	 *
	 * @param WP_Comment $comment      The comment object.
	 * @param array      $comment_meta The meta values for the comment.
	 *
	 * @return array The email addresses for the author of a theme or a plugin.
	 */
	public static function get_author_email_address( WP_Comment $comment, array $comment_meta ): array {
		global $wpdb;

		$email_addresses = array();
		$project         = self::get_project_to_translate( $comment );
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
		$parent_comments        = GP_Notifications::get_parent_comments( $comment->comment_parent );
		$emails_from_the_thread = GP_Notifications::get_commenters_email_addresses( $parent_comments );
		// If one author has a comment in the thread or if one validator is the commenter, we don't need to inform any other validator.
		if ( ( true !== empty( array_intersect( $email_addresses, $emails_from_the_thread ) ) ) || in_array( $comment->comment_author_email, $email_addresses, true ) ) {
			return array();
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
		$project  = self::get_project_to_translate( $comment );
		$original = self::get_original( $comment );
		$output   = esc_html__( 'Hi:' );
		$output  .= '<br><br>';
		$output  .= esc_html__( 'There is a new comment in a discussion of the WordPress translation system that may be of interest to you.' );
		$output  .= '<br>';
		$output  .= esc_html__( 'It would be nice if you have some time to review this comment and reply to it if needed.' );
		$output  .= '<br><br>';
		$url      = GP_Route_Translation_Helpers::get_permalink( $project->path, $original->id );
		$output  .= '- <strong>' . esc_html__( 'Discussion URL: ' ) . '</strong><a href="' . $url . '">' . $url . '</a><br>';
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
	 * @param WP_Comment $comment The comment object.
	 *
	 * @return GP_Project The project the translated string belongs to.
	 */
	private static function get_project_to_translate( WP_Comment $comment ): GP_Project {
		$post_id = $comment->comment_post_ID;
		$terms   = wp_get_object_terms( $post_id, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
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
		if ( ( ! is_null( $project->parent_project_id ) ) && ( ! in_array( $project->parent_project_id, $main_projects, true ) ) ) {
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

		$main_projects = $wpdb->get_col( "SELECT id FROM {$wpdb->gp_projects} WHERE parent_project_id IS NULL" );

		return $main_projects;
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
	private static function get_original( WP_Comment $comment ) {
		$post_id = $comment->comment_post_ID;
		$terms   = wp_get_object_terms( $post_id, Helper_Translation_Discussion::LINK_TAXONOMY, array( 'number' => 1 ) );
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
		foreach ( $email_addresses as $email_address ) {
			$user            = get_user_by( 'email', $email_address );
			$gp_default_sort = get_user_option( 'gp_default_sort', $user->ID );
			if ( 'on' != gp_array_get( $gp_default_sort, 'notifications_optin', 'off' ) ) {
				$index = array_search( $email_address, $email_addresses, true );
				if ( false !== $index ) {
					unset( $email_addresses[ $index ] );
				}
			}
		}
		return array_values( $email_addresses );
	}
}
