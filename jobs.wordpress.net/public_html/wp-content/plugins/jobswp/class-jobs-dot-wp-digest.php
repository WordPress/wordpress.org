<?php
/**
 * Daily job digests, emailed to subscribers through Jetpack Subscriptions.
 *
 * Jetpack only emails posts of the `post` type, so each day the newly published
 * jobs are gathered into digest posts: one listing every new job, filed under
 * the Job Alerts category, and one per job category, filed under a child
 * category that mirrors it. These categories are offered as Jetpack newsletter
 * categories, so subscribers get either the one daily email or the categories
 * they picked. Digest posts exist to be emailed; on the site they redirect to
 * the job listings.
 *
 * @package JobsWP
 */

declare( strict_types = 1 );

defined( 'ABSPATH' ) || die();

/**
 * Builds and publishes the daily job digests.
 */
class Jobs_Dot_WP_Digest {

	/**
	 * Cron hook that publishes the digests.
	 *
	 * @var string
	 */
	const CRON_HOOK = 'jobswp_publish_digests';

	/**
	 * Slug of the core category for the digest of all new jobs, and parent of the per-category ones.
	 *
	 * @var string
	 */
	const CATEGORY = 'job-alerts';

	/**
	 * Option holding the Unix timestamp the last digest covered jobs up to.
	 *
	 * @var string
	 */
	const LAST_RUN_OPTION = 'jobswp_digest_last_run';

	/**
	 * Term meta on a digest category, holding the ID of the job category it mirrors.
	 *
	 * @var string
	 */
	const JOB_CATEGORY_META = '_jobswp_job_category';

	/**
	 * Registers hooks.
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( self::CRON_HOOK, array( __CLASS__, 'publish_digests' ) );
		add_action( 'created_job_category', array( __CLASS__, 'set_up_categories' ), 10, 0 );

		add_action( 'pre_get_posts', array( __CLASS__, 'exclude_digests' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'exclude_digests_from_sitemap' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_digests' ) );
	}

	/**
	 * Schedules the daily digest run, setting up the categories it needs on first run.
	 *
	 * @return void
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) && ! defined( 'WP_INSTALLING' ) ) {
			self::set_up_categories();
			wp_schedule_event( time(), 'daily', self::CRON_HOOK );
		}
	}

	/**
	 * Creates the digest categories and offers them as Jetpack newsletter categories.
	 *
	 * Newsletter categories are switched on the first time, so the subscription
	 * forms can preselect a category from the start. A site that later switches
	 * them off keeps them off.
	 *
	 * @return array{0: WP_Term, 1: WP_Term[]}|null The Job Alerts category, and the digest categories keyed by job category ID.
	 */
	public static function set_up_categories(): ?array {
		$parent_category = self::get_category( true );
		if ( ! $parent_category ) {
			return null;
		}

		$digest_categories = array();
		foreach ( Jobs_Dot_WP::get_job_categories() as $job_category ) {
			$digest_category = self::get_digest_category( $job_category, $parent_category );
			if ( $digest_category ) {
				$digest_categories[ $job_category->term_id ] = $digest_category;
			}
		}

		// Jetpack only loads the helper in its settings endpoints.
		$helper = defined( 'JETPACK__PLUGIN_DIR' ) ? JETPACK__PLUGIN_DIR . '_inc/lib/class-jetpack-newsletter-category-helper.php' : '';
		if ( ! class_exists( 'Jetpack_Newsletter_Category_Helper' ) && $helper && file_exists( $helper ) ) {
			require_once $helper;
		}

		if ( class_exists( 'Jetpack_Newsletter_Category_Helper' ) ) {
			$ids     = Jetpack_Newsletter_Category_Helper::get_category_ids();
			$updated = array_values( array_unique( array_merge( $ids, array( $parent_category->term_id ), wp_list_pluck( $digest_categories, 'term_id' ) ) ) );

			if ( count( $updated ) !== count( $ids ) ) {
				Jetpack_Newsletter_Category_Helper::save_category_ids( $updated );
			}

			if ( null === get_option( 'wpcom_newsletter_categories_enabled', null ) ) {
				update_option( 'wpcom_newsletter_categories_enabled', true );
			}
		}

		return array( $parent_category, $digest_categories );
	}

	/**
	 * Unschedules the daily digest run.
	 *
	 * @return void
	 */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * Whether subscribers can choose Jetpack newsletter categories.
	 *
	 * Without them every subscriber gets every post, so only the digest of all
	 * new jobs is published, or subscribers would get each job twice.
	 *
	 * @return bool
	 */
	private static function has_newsletter_categories(): bool {
		return (bool) get_option( 'wpcom_newsletter_categories_enabled' );
	}

	/**
	 * Publishes the digests of the jobs published since the last run.
	 *
	 * Jobs are submitted as drafts without a fixed date, so publishing one sets
	 * its post date to the time it went live.
	 *
	 * @return void
	 */
	public static function publish_digests(): void {
		$categories = self::set_up_categories();
		if ( ! $categories ) {
			return;
		}
		list( $parent_category, $digest_categories ) = $categories;

		// Cron runs late without traffic, so the window starts where the last digest ended.
		$now   = time();
		$since = (int) get_option( self::LAST_RUN_OPTION, $now - DAY_IN_SECONDS );

		$jobs = get_posts(
			array(
				'post_type'      => 'job',
				'post_status'    => 'publish',
				'date_query'     => array(
					array(
						'column' => 'post_date_gmt',
						'after'  => gmdate( 'Y-m-d H:i:s', $since ),
					),
					array(
						'column'    => 'post_date_gmt',
						'before'    => gmdate( 'Y-m-d H:i:s', $now ),
						'inclusive' => true,
					),
				),
				'orderby'        => 'date',
				'order'          => 'ASC',
				'posts_per_page' => -1,
			)
		);

		if ( ! $jobs ) {
			update_option( self::LAST_RUN_OPTION, $now, false );
			return;
		}

		$groups = array();
		foreach ( $jobs as $job ) {
			$terms = get_the_terms( $job, 'job_category' );
			$key   = $terms && ! is_wp_error( $terms ) ? $terms[0]->term_id : 0;

			$groups[ $key ]['term']   = $key ? $terms[0] : null;
			$groups[ $key ]['jobs'][] = $job;
		}
		$uncategorized = $groups[0] ?? null;
		unset( $groups[0] );
		uasort( $groups, static fn ( array $a, array $b ): int => strnatcasecmp( $a['term']->name, $b['term']->name ) );
		if ( $uncategorized ) {
			$groups[0] = $uncategorized;
		}

		// Digest markup is built from escaped values only, and kses would strip its links on this site.
		kses_remove_filters();

		$content = '';
		foreach ( $groups as $group ) {
			$content .= '<h2>' . esc_html( $group['term'] ? $group['term']->name : __( 'Other', 'jobswp' ) ) . "</h2>\n";
			$content .= self::job_list( $group['jobs'] );
		}

		$published = self::insert_digest(
			sprintf(
				/* translators: 1: Number of jobs, 2: Date. */
				_n( '%1$d new job, %2$s', '%1$d new jobs, %2$s', count( $jobs ), 'jobswp' ),
				count( $jobs ),
				wp_date( get_option( 'date_format' ) )
			),
			$content . self::footer_link( home_url( '/' ), __( 'See all open jobs', 'jobswp' ) ),
			$parent_category
		);

		if ( $published && self::has_newsletter_categories() ) {
			foreach ( $groups as $key => $group ) {
				if ( ! isset( $digest_categories[ $key ] ) ) {
					continue;
				}

				$job_category = $group['term'];
				self::insert_digest(
					sprintf(
						/* translators: 1: Number of jobs, 2: Job category name, 3: Date. */
						_n( '%1$d new %2$s job, %3$s', '%1$d new %2$s jobs, %3$s', count( $group['jobs'] ), 'jobswp' ),
						count( $group['jobs'] ),
						$job_category->name,
						wp_date( get_option( 'date_format' ) )
					),
					self::job_list( $group['jobs'] ) . self::footer_link(
						get_term_link( $job_category ),
						/* translators: %s: Job category name. */
						sprintf( __( 'See all open %s jobs', 'jobswp' ), $job_category->name )
					),
					$digest_categories[ $key ]
				);
			}
		}

		kses_init();

		if ( $published ) {
			update_option( self::LAST_RUN_OPTION, $now, false );
		}
	}

	/**
	 * Inserts a digest post.
	 *
	 * @param string  $title    Post title.
	 * @param string  $content  Post content.
	 * @param WP_Term $category Category to file it under.
	 * @return bool Whether the digest was published.
	 */
	private static function insert_digest( string $title, string $content, WP_Term $category ): bool {
		$post_id = wp_insert_post(
			array(
				'post_type'     => 'post',
				'post_status'   => 'publish',
				'post_title'    => $title,
				'post_content'  => $content,
				'post_category' => array( $category->term_id ),
			),
			true
		);

		return ! is_wp_error( $post_id );
	}

	/**
	 * Returns the digest list of jobs.
	 *
	 * @param WP_Post[] $jobs Jobs.
	 * @return string
	 */
	private static function job_list( array $jobs ): string {
		$list = "<ul>\n";
		foreach ( $jobs as $job ) {
			$details = array_filter(
				array(
					get_post_meta( $job->ID, 'company', true ),
					get_post_meta( $job->ID, 'location', true ),
					get_post_meta( $job->ID, 'jobtype', true ),
				),
				static fn ( $detail ): bool => $detail && 'N/A' !== $detail
			);

			$list .= sprintf(
				"<li><a href=\"%s\">%s</a>%s</li>\n",
				esc_url( get_permalink( $job ) ),
				self::text( wp_strip_all_tags( get_the_title( $job ) ) ),
				$details ? ' &mdash; ' . self::text( implode( ', ', $details ) ) : ''
			);
		}

		return $list . "</ul>\n";
	}

	/**
	 * Returns the closing link of a digest.
	 *
	 * @param string $url  Link URL.
	 * @param string $text Link text.
	 * @return string
	 */
	private static function footer_link( string $url, string $text ): string {
		return sprintf( '<p><a href="%1$s">%2$s</a></p>', esc_url( $url ), esc_html( $text ) );
	}

	/**
	 * Encodes job-supplied text for a digest.
	 *
	 * Brackets are encoded so shortcodes stay text, and collapsing whitespace
	 * keeps each list item on one line, so a URL can never stand on a line of
	 * its own and be embedded.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	private static function text( string $text ): string {
		return str_replace( '[', '&#91;', esc_html( trim( (string) preg_replace( '/\s+/u', ' ', $text ) ) ) );
	}

	/**
	 * Returns the Job Alerts category.
	 *
	 * @param bool $create Optional. Whether to create the category if it does not exist. Default false.
	 * @return WP_Term|null
	 */
	private static function get_category( bool $create = false ): ?WP_Term {
		$category = get_term_by( 'slug', self::CATEGORY, 'category' );
		if ( $category || ! $create ) {
			return $category ?: null;
		}

		$term = wp_insert_term( __( 'All Jobs', 'jobswp' ), 'category', array( 'slug' => self::CATEGORY ) );
		if ( is_wp_error( $term ) ) {
			return null;
		}

		return get_term( $term['term_id'], 'category' );
	}

	/**
	 * Returns the digest category mirroring a job category, creating it if a parent is given.
	 *
	 * The two are linked by term meta rather than slug, so renaming a job
	 * category keeps its subscribers.
	 *
	 * @param WP_Term      $job_category    Job category.
	 * @param WP_Term|null $parent_category Optional. Job Alerts category, to create a missing digest category under.
	 * @return WP_Term|null
	 */
	private static function get_digest_category( WP_Term $job_category, ?WP_Term $parent_category = null ): ?WP_Term {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'number'     => 1,
				'meta_key'   => self::JOB_CATEGORY_META, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Small taxonomy.
				'meta_value' => $job_category->term_id, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Small taxonomy.
			)
		);

		if ( $terms && ! is_wp_error( $terms ) ) {
			return $terms[0];
		}

		if ( ! $parent_category ) {
			return null;
		}

		$term = wp_insert_term(
			$job_category->name,
			'category',
			array(
				'parent' => $parent_category->term_id,
				'slug'   => self::CATEGORY . '-' . $job_category->slug,
			)
		);
		if ( is_wp_error( $term ) ) {
			return null;
		}

		add_term_meta( $term['term_id'], self::JOB_CATEGORY_META, $job_category->term_id, true );

		return get_term( $term['term_id'], 'category' );
	}

	/**
	 * Returns the tax query clause that leaves out digest posts.
	 *
	 * @return array|null Null when there are no digests yet.
	 */
	private static function exclusion_clause(): ?array {
		$category = self::get_category();
		if ( ! $category ) {
			return null;
		}

		return array(
			'taxonomy'         => 'category',
			'terms'            => array( $category->term_id ),
			'include_children' => true,
			'operator'         => 'NOT IN',
		);
	}

	/**
	 * Leaves digest posts out of front-end listings and feeds.
	 *
	 * @param WP_Query $query The query object.
	 * @return void
	 */
	public static function exclude_digests( WP_Query $query ): void {
		if ( is_admin() || ! $query->is_main_query() || $query->is_singular() ) {
			return;
		}

		$clause = self::exclusion_clause();
		if ( ! $clause ) {
			return;
		}

		$tax_query   = is_array( $query->get( 'tax_query' ) ) ? $query->get( 'tax_query' ) : array();
		$tax_query[] = $clause;
		$query->set( 'tax_query', $tax_query );
	}

	/**
	 * Leaves digest posts out of the posts sitemap.
	 *
	 * @param array  $args      WP_Query arguments.
	 * @param string $post_type Post type.
	 * @return array
	 */
	public static function exclude_digests_from_sitemap( array $args, string $post_type ): array {
		$clause = self::exclusion_clause();
		if ( 'post' !== $post_type || ! $clause ) {
			return $args;
		}

		$args['tax_query']   = isset( $args['tax_query'] ) && is_array( $args['tax_query'] ) ? $args['tax_query'] : array(); // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Sitemap query.
		$args['tax_query'][] = $clause;

		return $args;
	}

	/**
	 * Sends digest posts and digest category archives to the job listings they summarize.
	 *
	 * @return void
	 */
	public static function redirect_digests(): void {
		$parent_category = self::get_category();
		if ( ! $parent_category ) {
			return;
		}

		$category = null;
		if ( is_singular( 'post' ) ) {
			$terms    = get_the_terms( get_queried_object_id(), 'category' );
			$category = $terms && ! is_wp_error( $terms ) ? $terms[0] : null;
		} elseif ( is_category() ) {
			$category = get_queried_object();
		}

		if ( ! $category instanceof WP_Term ) {
			return;
		}

		if ( $parent_category->term_id === $category->term_id ) {
			wp_safe_redirect( home_url( '/' ) );
			exit;
		}

		if ( $parent_category->term_id === $category->parent ) {
			$job_category = get_term( (int) get_term_meta( $category->term_id, self::JOB_CATEGORY_META, true ), 'job_category' );
			$location     = $job_category instanceof WP_Term ? get_term_link( $job_category ) : home_url( '/' );

			wp_safe_redirect( is_wp_error( $location ) ? home_url( '/' ) : $location );
			exit;
		}
	}

	/**
	 * Returns the Jetpack subscription form.
	 *
	 * With newsletter categories on, the form subscribes to the given job
	 * category's digest, or else to the digest of all new jobs, so nobody gets both.
	 *
	 * @param WP_Term|null $job_category Optional. Job category to subscribe to.
	 * @param bool         $compact      Optional. Whether to fit a narrow space: the button goes below the
	 *                                   email field, sized like the theme's small buttons. Default false.
	 * @return string Form markup, or an empty string when Jetpack Subscriptions is unavailable.
	 */
	public static function get_subscribe_form( ?WP_Term $job_category = null, bool $compact = false ): string {
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'jetpack/subscriptions' ) ) {
			return '';
		}

		$attrs = array(
			'submitButtonText'     => __( 'Get job alerts', 'jobswp' ),
			'subscribePlaceholder' => __( 'Your email address', 'jobswp' ),
			'showSubscribersTotal' => false,
		);

		if ( $compact ) {
			// Jetpack pads 1.5 times as much horizontally, so this matches the theme's .btn-sm.
			$attrs['buttonOnNewLine'] = true;
			$attrs['padding']         = 8;
			$attrs['customFontSize']  = '14px';
		}

		$category = $job_category ? self::get_digest_category( $job_category ) : self::get_category();
		if ( $category && self::has_newsletter_categories() ) {
			$attrs['selectedNewsletterCategoryIds'] = array( $category->term_id );
			$attrs['preselectNewsletterCategories'] = true;
		}

		return render_block(
			array(
				'blockName'    => 'jetpack/subscriptions',
				'attrs'        => $attrs,
				'innerBlocks'  => array(),
				'innerHTML'    => '',
				'innerContent' => array(),
			)
		);
	}
}

Jobs_Dot_WP_Digest::init();
register_deactivation_hook( __DIR__ . '/jobswp.php', array( 'Jobs_Dot_WP_Digest', 'unschedule' ) );
