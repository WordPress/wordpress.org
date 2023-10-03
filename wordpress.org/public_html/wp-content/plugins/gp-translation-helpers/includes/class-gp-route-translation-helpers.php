<?php
/**
 * Routes: GP_Route_Translation_Helpers class
 *
 * @package gp-translation-helpers
 * @since 0.0.1
 */
class GP_Route_Translation_Helpers extends GP_Route {

	/**
	 * Stores an instance of each helper.
	 *
	 * @since 0.0.1
	 * @var array
	 */
	private $helpers = array();

	/**
	 * GP_Route_Translation_Helpers constructor.
	 *
	 * @since 0.0.1
	 */
	public function __construct() {
		$this->helpers       = GP_Translation_Helpers::load_helpers();
		$this->template_path = dirname( __FILE__ ) . '/../templates/';
	}

	/**
	 * Loads the 'discussions-dashboard' template.
	 *
	 * @since 0.0.2
	 *
	 * @param string|null $locale_slug          Optional. The locale slug. E.g. "es".
	 *
	 * @return void
	 */
	public function discussions_dashboard( $locale_slug ) {
		if ( ! is_user_logged_in() ) {
			$this->die_with_404();
		}
		$user_id = wp_get_current_user()->ID;

		$all_comments_post_ids = $this->get_comment_post_ids( $locale_slug );
		$comments_count        = count( $all_comments_post_ids );
		$comment_post_ids      = $all_comments_post_ids;

		$participating          = $this->get_user_comments( $locale_slug, $user_id );
		$participating_post_ids = array_unique( array_column( $participating, 'comment_post_ID' ) );

		$not_participating_post_ids = array_diff( $all_comments_post_ids, $participating_post_ids );

		$comments_per_page   = 12;
		$page_num_from_query = (int) get_query_var( 'page' );
		$offset              = $page_num_from_query > 0 ? ( $page_num_from_query - 1 ) * $comments_per_page : 0;
		$filter              = isset( $_GET['filter'] ) ? esc_html( $_GET['filter'] ) : '';
		$page_number         = ( ! empty( $page_num_from_query ) && is_int( $page_num_from_query ) ) ? $page_num_from_query : 1;
		$gp_locale           = GP_Locales::by_slug( $locale_slug );
		if ( 'participating' == $filter ) {
			$comment_post_ids = $participating_post_ids;
			$comments_count   = count( $participating_post_ids );
		}
		if ( 'not_participating' == $filter ) {
			$comment_post_ids = $not_participating_post_ids;
			$comments_count   = count( $not_participating_post_ids );
		}
		$total_pages = (int) ceil( $comments_count / $comments_per_page );

		$post_ids = array();

		$post_ids       = array_splice( $comment_post_ids, $offset, $comments_per_page );
		$args           = array(
			'meta_key'   => 'locale',
			'meta_value' => $locale_slug,
			'post__in'   => $post_ids,
		);
		$comments_query = new WP_Comment_Query( $args );
		$comments       = $comments_query->comments;

		$this->tmpl( 'discussions-dashboard', get_defined_vars() );
	}

	/**
	 * Loads the 'original-permalink' template.
	 *
	 * @since 0.0.2
	 *
	 * @param string      $project_path         The project path. E.g. "wp/dev".
	 * @param int         $original_id          The original id. E.g. "2440".
	 * @param string|null $locale_slug          Optional. The locale slug. E.g. "es".
	 * @param string      $translation_set_slug The translation slug. E.g. "default".
	 * @param int|null    $translation_id       Optional. The translation id. E.g. "4525".
	 *
	 * @return void
	 */
	public function original_permalink( $project_path, $original_id, $locale_slug = null, $translation_set_slug = null, $translation_id = null ) {
		$original = GP::$original->get( $original_id );
		if ( ! $original ) {
			$this->die_with_404();
		}
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			$this->die_with_404();
		}

		if ( $project->id !== $original->project_id ) {
			$project = GP::$project->get( $original->project_id );

			// Let's use the parameters that we have to create a URL in the right project.
			$corrected_url = self::get_permalink( $project->path, $original_id, $locale_slug, $translation_set_slug );

			wp_safe_redirect( $corrected_url );
			exit;
		}

		if ( ! $original ) {
			$this->die_with_404();
		}

		$args = array(
			'project_id'     => $project->id,
			'locale_slug'    => $locale_slug,
			'set_slug'       => $translation_set_slug,
			'original_id'    => $original_id,
			'translation_id' => $translation_id,
			'project'        => $project,

		);
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );

		$all_translation_sets = GP::$translation_set->by_project_id( $project->id );

		$row_id      = $original_id;
		$translation = null;
		if ( $translation_id ) {
			$row_id     .= '-' . $translation_id;
			$translation = GP::$translation->get( $translation_id );
		}
		$original_permalink = gp_url_project( $project, array( 'filters[original_id]' => $original_id ) );

		$original_translation_permalink = false;
		if ( $translation_set ) {
			$original_translation_permalink = gp_url_project_locale( $project, $locale_slug, $translation_set->slug, array( 'filters[original_id]' => $original_id ) );
		}

		/** Get translation for this original */
		$existing_translations = array();
		if ( ! $translation && $translation_set && $original_id ) {
			$translation = GP::$translation->find_one(
				array(
					'status'             => 'current',
					'original_id'        => $original_id,
					'translation_set_id' => $translation_set->id,
				)
			);

			if ( ! $translation ) {
				$existing_translations = GP::$translation->find_many_no_map(
					array(
						'original_id'        => $original_id,
						'translation_set_id' => $translation_set->id,
					)
				);
				usort(
					$existing_translations,
					function ( $t1, $t2 ) {
						$cmp_prop_t1 = $t1->date_modified ?? $t1->date_added;
						$cmp_prop_t2 = $t2->date_modified ?? $t2->date_added;
						return $cmp_prop_t1 < $cmp_prop_t2;
					}
				);

				// Something falsy is not enough.
				$translation = null;
			}
		}

		$priorities_key_value = $original->get_static( 'priorities' );
		$priority             = $priorities_key_value[ $original->priority ];

		$args     = compact( 'project', 'locale_slug', 'translation_set_slug', 'original_id', 'translation_id', 'translation', 'original_permalink' );
		$sections = $this->get_translation_helper_sections( $args );

		$translations       = GP::$translation->find_many_no_map(
			array(
				'status'      => 'current',
				'original_id' => $original_id,
			)
		);
		$no_of_translations = count( $translations );

		add_action(
			'gp_head',
			function() use ( $original, $no_of_translations ) {
				echo '<meta property="og:title" content="' . esc_html( $original->singular ) . ' | ' . esc_html( $no_of_translations ) . ' translations" />';
			}
		);

		$this->tmpl( 'original-permalink', get_defined_vars() );
	}

	/**
	 * Gets the sections of each active helper.
	 *
	 * @param      array $data   The data to be passed on to the sections.
	 *
	 * @return     array   The translation helper sections.
	 */
	public function get_translation_helper_sections( $data ) {
		$sections = array();
		foreach ( $this->helpers as $helper => $translation_helper ) {
			$translation_helper->set_data( $data );

			if ( ! $translation_helper->activate() ) {
				continue;
			}

			$sections[] = array(
				'title'             => $translation_helper->get_title(),
				'content'           => $translation_helper->get_output(),
				'classname'         => $translation_helper->get_div_classname(),
				'id'                => $translation_helper->get_div_id(),
				'priority'          => $translation_helper->get_priority(),
				'has_async_content' => $translation_helper->has_async_content(),
				'count'             => $translation_helper->get_count(),
				'load_inline'       => $translation_helper->load_inline(),
				'helper'            => $helper,
			);
		}

		usort(
			$sections,
			function( $s1, $s2 ) {
				return $s1['priority'] <=> $s2['priority'];
			}
		);

		return $sections;
	}

	/**
	 * Returns the content of each section (tab).
	 *
	 * @since 0.0.2
	 *
	 * @param string   $project_path    The project path. E.g. "wp/dev".
	 * @param string   $locale_slug     The locale slug. E.g. "es".
	 * @param string   $set_slug        The translation set slug. E.g. "default".
	 * @param int      $original_id     The original id. E.g. "2440".
	 * @param int|null $translation_id  Optional. The translation id. E.g. "4525".
	 *
	 * @return string                   JSON with the content of each section.
	 */
	public function ajax_translation_helpers_locale( string $project_path, string $locale_slug, string $set_slug, int $original_id, int $translation_id = null ) {
		return $this->ajax_translation_helpers( $project_path, $original_id, $translation_id, $locale_slug, $set_slug );
	}

	/**
	 * Returns the content of each section (tab).
	 *
	 * @since 0.0.1
	 *
	 * @param string      $project_path     The project path. E.g. "wp/dev".
	 * @param int         $original_id      The original id. E.g. "2440".
	 * @param int|null    $translation_id   Optional. The translation id. E.g. "4525".
	 * @param string|null $locale_slug      The locale slug. E.g. "es".
	 * @param string|null $set_slug         The translation set slug. E.g. "default".
	 *
	 * @return void                         Prints the JSON with the content of each section.
	 */
	public function ajax_translation_helpers( string $project_path, int $original_id, int $translation_id = null, string $locale_slug = null, string $set_slug = null ): void {
		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			$this->die_with_404();
		}

		$permalink = self::get_permalink( $project->path, $original_id, $set_slug, $locale_slug );

		$args = array(
			'project_id'           => $project->id,
			'locale_slug'          => $locale_slug,
			'translation_set_slug' => $set_slug,
			'original_id'          => $original_id,
			'translation_id'       => $translation_id,
			'permalink'            => $permalink,
			'project'              => $project,
		);

		$selected = gp_get( 'helpers' );
		if ( ! empty( $selected ) ) {
			$helpers = array_filter(
				$this->helpers,
				function ( $key ) use ( $selected ) {
					return in_array( $key, (array) $selected, true );
				},
				ARRAY_FILTER_USE_KEY
			);
		} else {
			$helpers = $this->helpers;
		}

		$sections = array();
		foreach ( $helpers as $translation_helper ) {
			$translation_helper->set_data( $args );
			if ( $translation_helper->has_async_content() && $translation_helper->activate() ) {
				$sections[ $translation_helper->get_div_id() ] = array(
					'content' => $translation_helper->get_async_output(),
					'count'   => $translation_helper->get_count(),
				);
			};
		}

		wp_send_json( $sections );
	}

	/**
	 * Gets the locales with comments.
	 *
	 * @since 0.0.2
	 *
	 * @param array|null $comments  Array with comments.
	 *
	 * @return array                Array with the locales with comments.
	 */
	private function get_locales_with_comments( ?array $comments ): array {
		$comment_locales = array();
		if ( $comments ) {
			foreach ( $comments as $comment ) {
				$comment_meta          = get_comment_meta( $comment->comment_ID, 'locale' );
				$single_comment_locale = is_array( $comment_meta ) && ! empty( $comment_meta ) ? $comment_meta[0] : '';
				if ( $single_comment_locale && ! in_array( $single_comment_locale, $comment_locales, true ) ) {
					$comment_locales[] = $single_comment_locale;
				}
			}
		}
		return $comment_locales;
	}

	/**
	 * Gets the full permalink.
	 *
	 * @since 0.0.2
	 *
	 * @param string      $project_path The project path. E.g. "wp/dev".
	 * @param int|null    $original_id  The original id. E.g. "2440".
	 * @param string|null $set_slug     The translation set slug. E.g. "default".
	 * @param string|null $locale_slug  Optional. The locale slug. E.g. "es".
	 *
	 * @return string                   The full permalink.
	 */
	public static function get_permalink( string $project_path, ?int $original_id, string $set_slug = null, string $locale_slug = null ): string {
		$permalink = $project_path . '/' . $original_id;
		if ( $set_slug && $locale_slug ) {
			$permalink .= '/' . $locale_slug . '/' . $set_slug;
		}
		return home_url( gp_url_project( $permalink ) );
	}

	/**
	 * Gets the translation permalink.
	 *
	 * @param      GP_Project $project               The project.
	 * @param      string     $locale_slug           The locale slug.
	 * @param      string     $translation_set_slug  The translation set slug.
	 * @param      int        $original_id           The original id.
	 * @param      int        $translation_id        The translation id.
	 *
	 * @return     bool    The translation permalink.
	 */
	public static function get_translation_permalink( $project, $locale_slug, $translation_set_slug, $original_id, $translation_id = null ) {
		if ( ! $project || ! $locale_slug || ! $translation_set_slug || ! $original_id ) {
			return false;
		}

		$args = array(
			'filters[original_id]' => $original_id,
		);

		if ( $translation_id ) {
			$args['filters[status]']         = 'either';
			$args['filters[translation_id]'] = $translation_id;
		}

		$translation_permalink = gp_url_project_locale(
			$project,
			$locale_slug,
			$translation_set_slug,
			$args
		);
		return $translation_permalink;
	}

	/**
	 * Gets distinct post_ids for all comments made by user
	 *
	 * @param      string $locale_slug           The locale slug.
	 * @param      int    $user_id           The user id.
	 *
	 * @return     array    The array of comment_post_IDs.
	 */
	private function get_user_comments( $locale_slug, $user_id ) {
		$args     = array(
			'meta_key'   => 'locale',
			'meta_value' => $locale_slug,
			'user_id'    => $user_id,
		);
		$query    = new WP_Comment_Query( $args );
		$comments = $query->comments;

		return $comments;
	}

	/**
	 * Run a query to fetch comment_post_ID of all comments
	 *
	 * @param string $locale_slug           The locale slug.
	 *
	 * @return array Array of unique comment_post_IDs for all comments.
	 */
	private function get_comment_post_ids( $locale_slug ) {
		global $wpdb;
		return $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT {$wpdb->comments}.comment_post_ID FROM {$wpdb->comments} INNER JOIN {$wpdb->commentmeta} ON {$wpdb->comments}.comment_ID = {$wpdb->commentmeta}.comment_id WHERE meta_key='locale' AND meta_value = %s ORDER BY comment_date DESC",
				$locale_slug
			)
		);
	}
}
