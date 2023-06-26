<?php
/**
 * Routes: WPorg_Customizations class
 *
 * Manages the WPorg customizations.
 *
 * @package gp-translation-helpers
 * @since 0.0.2
 */
class WPorg_GlotPress_Customizations {
	/**
	 * Adds the hooks to modify the options in the select item where we add a new comment in a discussion.
	 *
	 * @since 0.0.2
	 *
	 * @return void
	 */
	public static function init() {
		if ( defined( 'WPORG_TRANSLATE_BLOGID' ) && ( get_current_blog_id() === WPORG_TRANSLATE_BLOGID ) ) {
			add_filter(
				'gp_discussion_new_comment_options',
				function ( $options, $locale_slug ) {
					$optgroup_question = '';
					if ( $locale_slug ) {
						$gp_locale = GP_Locales::by_slug( $locale_slug );
						if ( $gp_locale ) {
							$optgroup_question = '
								<optgroup label="Notify GTE/PTE/CLPTE (if opted-in)">
									<option value="question">Question about translating to ' . esc_html( $gp_locale->english_name ) . '</option>
								</optgroup>';
						}
					}

					return '<select required="" name="comment_topic" id="comment_topic">
								<option value="">Select a topic</option>
								<optgroup label="Notify developers (if opted-in)">
									<option value="typo">Typo in the English text</option>
									<option value="context">Where does this string appear? (more context)</option>
								</optgroup>' .
								$optgroup_question .
							'</select>';
				},
				10,
				2
			);

			add_filter(
				'gp_custom_reasons',
				function ( $default_reasons, $locale ) {
					return array_merge( $default_reasons, GP_Custom_Locale_Reasons::get_custom_reasons( $locale ) );
				},
				10,
				2
			);

			add_filter( 'jetpack_mentions_should_load_ui', '__return_true' );
			add_filter(
				'jetpack_mentions_allowed_post_types',
				function( $post_types ) {
					$post_types[] = Helper_Translation_Discussion::POST_TYPE;
					return $post_types;
				}
			);

			add_filter(
				'gp_validators_involved',
				function ( $gtes_involved, $locale_slug, $original_id, $comment_authors ) {
					$gte_emails   = WPorg_GlotPress_Notifications::get_gte_email_addresses( $locale_slug );
					$pte_emails   = WPorg_GlotPress_Notifications::get_pte_email_addresses_by_project_and_locale( $original_id, $locale_slug );
					$clpte_emails = WPorg_GlotPress_Notifications::get_clpte_email_addresses_by_project( $original_id );
					return array_intersect( array_merge( $gte_emails, $pte_emails, $clpte_emails ), $comment_authors );

				},
				10,
				4
			);

			add_filter(
				'gp_involved_table_heading',
				function () {
					return __( 'GTEs/PTEs/CLPTEs Involved' );
				}
			);
			add_filter(
				'gp_get_openai_key',
				function () {
					$default_sort = get_user_option( 'gp_default_sort' );
					if ( is_array( $default_sort ) && ! empty( $default_sort['openai_api_key'] ) ) {
						$gp_openai_key = $default_sort['openai_api_key'];
						return $gp_openai_key;
					}
					return;
				}
			);
		}
	}
}
