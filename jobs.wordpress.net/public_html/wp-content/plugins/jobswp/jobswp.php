<?php
/*
Plugin Name: JobsWP
Version: 1.2
Plugin URI: http://jobs.wordpress.net
Author: Scott Reilly
Description: Functionality for jobs.wordpress.net
*/

defined( 'ABSPATH' ) or die();

require_once( dirname( __FILE__ ) . '/jobswp-captcha.php' );
require_once( dirname( __FILE__ ) . '/jobswp-template.php' );
require_once( dirname( __FILE__ ) . '/jobswp-walker.php' );

class Jobs_Dot_WP {

	/**
	 * Fields that must have a value when submitted by job poster.
	 */
	private $required_fields = array(
		'first_name', 'last_name', 'email', 'phone',
		'company', 'howtoapply_method', 'howtoapply',
		'job_title', 'category', 'jobtype', 'job_description'
	);

	/**
	 * All of the meta fields.
	 */
	private $meta_fields = array(
		'first_name', 'last_name', 'email', 'phone',
		'company', 'howtoapply_method', 'howtoapply',
		'budget', 'jobtype', 'location'
	);

	/**
	 * Internally used temporary variables.
	 */
	private $success      = false;
	private $skip_content = false;

	/**
	 * The instance of the class, accessible via Jobs_Dot_WP::get_instance().
	 */
	private static $instance;

	/**
	 * Custom walker instance
	 */
	private static $walker;

	/**
	 * Returns the singleton instance of the class. If there isn't one, creates it.
	 * @return Jobs_Dot_WP
	 */
	public static function get_instance() {
		if ( ! self::$instance )
			self::$instance = new Jobs_Dot_WP;

		return self::$instance;
	}

	/**
	 * Returns the number of days a job is left open before automatic pruning.
	 *
	 * By default it is 21 days.
	 *
	 * @return int
	 */
	public static function get_days_until_pruning() {
		return apply_filters( 'jobswp_days_until_pruning', 21 );
	}

	/**
	 * Constructor
	 */
	protected function __construct() {
		if ( class_exists( 'Walker_Jobs_Category' ) )
			self::$walker = new Walker_Jobs_Category;

		register_deactivation_hook( __FILE__, array( __CLASS__, 'unschedule_job_pruning' ) );

		add_action( 'init', array( $this, 'registrations' ), 1 );
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Invokes registration of custom post type, taxonomy, and statuses.
	 */
	public function registrations() {
		$this->register_post_type();
		$this->register_post_status();
		$this->register_taxonomy();
	}

	/**
	 * Initializations. Mostly registering hooks for actions and filters, as well
	 * as any processing that needs to happen directly on 'init'.
	 */
	public function init() {
		add_filter( 'manage_posts_columns',           array( $this, 'posts_columns' ), 8, 2 );
		add_action( 'manage_job_posts_custom_column', array( $this, 'custom_posts_columns' ), 10, 2 );

		add_filter( 'the_content',                    array( $this, 'add_post_a_job_form' ) );
		add_filter( 'wp_kses_allowed_html',           array( $this, 'wp_kses_allowed_html' ), 10, 2 );
		add_filter( 'body_class',                     array( $this, 'body_class' ) );

		add_action( 'admin_notices',                  array( $this, 'alert_if_no_jobposter' ) );
		add_action( 'admin_notices',                  array( $this, 'job_closure_success' ) );
		add_action( 'post_submitbox_start',           array( $this, 'post_submitbox_start' ) );
		add_action( 'admin_action_close-job',         array( $this, 'handle_close_job' ) );
		add_action( 'post_row_actions',               array( $this, 'post_row_actions' ), 10, 2 );

		add_action( 'pre_get_posts',                  array( $this, 'add_jobs_to_feed' ) );

		// For enabling admin job post type searches to also search custom fields.
		add_filter( 'posts_join',                     array( $this, 'admin_search_job_posts_join' ), 10, 2 );
		add_filter( 'posts_search',                   array( $this, 'admin_search_job_posts_search' ), 10, 2 );
		add_filter( 'posts_request',                  array( $this, 'admin_search_job_posts_request' ), 10, 2 );

		foreach ( array( 'the_content', 'the_title', 'single_post_title' ) as $filter )
			add_filter( $filter,                      array( $this, 'WordPress_dangit' ) );

		add_action( 'save_post_job',                  array( $this, 'email_job_poster' ), 10, 3 );
		add_action( 'wp',                             array( $this, 'maybe_remove_job' ) );
		$this->save_job();
		$this->schedule_job_pruning();

		add_action( 'jobswp_scheduled_job_pruning',   array( $this, 'scheduled_job_pruning' ) );
	}

	/**
	 * Amends additional HTML tags as allowed for use in job descriptions.
	 *
	 * Adds allowance for ol, ul, li
	 *
	 * @param array  $allowedtags The already allowed tags
	 * @param string $content     The content
	 * @return array The amended list of allowed tags
	 */
	public function wp_kses_allowed_html( $allowedtags, $content ) {
		// Add permissable tags
		$allowedtags['ol'] = array();
		$allowedtags['ul'] = array();
		$allowedtags['li'] = array();

		// Remove unnecessary tags
		$disallowed_tags = array( 'a', 'del', 'strike' );
		foreach ( $disallowed_tags as $tag ) {
			if ( isset( $allowedtags[ $tag ] ) )
				unset( $allowedtags[ $tag ] );
		}

		return $allowedtags;
	}

	/**
	 * Adds custom class name to 'body' tag for all output pages
	 *
	 * @param array $classes Classes to be added to 'body' tag
	 * @return array The amended list of classes to be added to 'body' tag
	 */
	public function body_class( $classes ) {
		$classes[] = 'jobswp';
		return $classes;
	}

	/**
	 * Adds additional columns to the admin post listing table for the job custom post type.
	 *
	 * Adds a column to display the name of the person who posted the job, as well as a
	 * a column with their email address.
	 *
	 * @param array $columns Associative array of column names and labels
	 * @return array Amended associated array of column names and labels
	 */
	public function posts_columns( $columns, $post_type ) {
		if ( 'job' !== $post_type ) {
			return $columns;
		}
		$columns['poster']       = __( 'Poster', 'jobswp' );
		$columns['poster_email'] = __( 'Poster Email', 'jobswp' );
		return $columns;
	}

	/**
	 * Outputs the contents of the custom admin post listing columns for a given job.
	 *
	 * @param string $column_name The column name
	 * @param int $post_id The post ID
	 */
	public function custom_posts_columns( $column_name, $post_id ) {
		switch ( $column_name ) {
			case 'poster':
				$first_name = get_post_meta( $post_id, 'first_name', true );
				$last_name  = get_post_meta( $post_id, 'last_name', true );
				$name = trim( $first_name . ' ' . $last_name );
				// If no name was obtained via either of the name components, then fall back to old-school meta field
				if ( empty( $name ) )
					$name = get_post_meta( $post_id, 'post_name', true );
				echo esc_html( $name );
				break;
			case 'poster_email':
				$email = get_post_meta( $post_id, 'email', true );

				echo esc_html( $email );

				// Also show the public howtoapply email address.
				if ( 'email' === get_post_meta( $post_id, 'howtoapply_method', true ) ) {
					$contact_email = get_post_meta( $post_id, 'howtoapply', true );
					if ( strtolower( $contact_email ) !== strtolower( $email ) ) {
						echo '<br />';
						echo esc_html( $contact_email );
					}
				}
				break;
		}
	}

	/**
	 * Registers the custom post type.
	 */
	protected function register_post_type() {
		$labels = array(
			'name'                => _x( 'Jobs', 'Post Type General Name', 'jobswp' ),
			'singular_name'       => _x( 'Job', 'Post Type Singular Name', 'jobswp' ),
			'menu_name'           => __( 'Jobs', 'jobswp' ),
			'parent_item_colon'   => __( 'Parent Job:', 'jobswp' ),
			'all_items'           => __( 'All Jobs', 'jobswp' ),
			'view_item'           => __( 'View Job', 'jobswp' ),
			'add_new_item'        => __( 'Add New Job', 'jobswp' ),
			'add_new'             => __( 'New Job', 'jobswp' ),
			'edit_item'           => __( 'Edit Job', 'jobswp' ),
			'update_item'         => __( 'Update Job', 'jobswp' ),
			'search_items'        => __( 'Search jobs', 'jobswp' ),
			'not_found'           => __( 'No jobs found', 'jobswp' ),
			'not_found_in_trash'  => __( 'No jobs found in Trash', 'jobswp' ),
		);
		$args = array(
			'label'               => __( 'job', 'jobswp' ),
			'description'         => __( 'Job information pages', 'jobswp' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'revisions', 'custom-fields', ),
			'taxonomies'          => array( 'job_category' ),
			'hierarchical'        => false,
			'public'              => true,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 5,
//			'menu_icon'           => 'jobs.png',
			'can_export'          => true,
			'has_archive'         => true,
			'exclude_from_search' => false,
			'publicly_queryable'  => true,
			'capability_type'     => 'page',
		);
		register_post_type( 'job', $args );
	}

	/**
	 * Registers the custom post statuses.
	 */
	protected function register_post_status() {
		// Status of 'closed' indicates a job that was unpublished from the site.
		register_post_status( 'closed', array(
			'label'                     => __( 'Closed', 'jobswp' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Closed <span class="count">(%s)</span>', 'Closed <span class="count">(%s)</span>', 'jobswp' ),
		) );

		// Status of 'pruned' indicates a job automatically unpublished from the site after its
		// allotted listing availability had expired.
		register_post_status( 'pruned', array(
			'label'                     => __( 'Pruned', 'jobswp' ),
			'public'                    => false,
			'exclude_from_search'       => true,
			'show_in_admin_all_list'    => false,
			'show_in_admin_status_list' => true,
			'label_count'               => _n_noop( 'Pruned <span class="count">(%s)</span>', 'Pruned <span class="count">(%s)</span>', 'jobswp' ),
		) );
	}

	/**
	 * Registers the custom taxonomy.
	 */
	protected function register_taxonomy()  {
		$labels = array(
			'name'                       => _x( 'Job Categories', 'Taxonomy General Name', 'jobswp' ),
			'singular_name'              => _x( 'Job Category', 'Taxonomy Singular Name', 'jobswp' ),
			'menu_name'                  => __( 'Job Category', 'jobswp' ),
			'all_items'                  => __( 'All Job Categories', 'jobswp' ),
			'parent_item'                => __( 'Parent Job Category', 'jobswp' ),
			'parent_item_colon'          => __( 'Parent Job Category:', 'jobswp' ),
			'new_item_name'              => __( 'New Job Category Name', 'jobswp' ),
			'add_new_item'               => __( 'Add New Job Category', 'jobswp' ),
			'edit_item'                  => __( 'Edit Job Category', 'jobswp' ),
			'update_item'                => __( 'Update Category', 'jobswp' ),
			'separate_items_with_commas' => __( 'Separate job categories with commas', 'jobswp' ),
			'search_items'               => __( 'Search job categories', 'jobswp' ),
			'add_or_remove_items'        => __( 'Add or remove job categories', 'jobswp' ),
			'choose_from_most_used'      => __( 'Choose from the most used job categories', 'jobswp' ),
		);
		$args = array(
			'labels'                     => $labels,
			'hierarchical'               => false,
			'public'                     => true,
			'show_ui'                    => true,
			'show_admin_column'          => true,
			'show_in_nav_menus'          => true,
			'show_tagcloud'              => true,
		);
		register_taxonomy( 'job_category', 'job', $args );
	}

	/**
	 * Outputs admin notice on job post type listing admin page if the jobposter
	 * user account does not exist.
	 */
	public function alert_if_no_jobposter() {
		global $pagenow;

		if ( 'edit.php' != $pagenow || ! isset( $_GET['post_type'] ) || 'job' != $_GET['post_type'] )
			return;

		$jobposter_username = apply_filters( 'jobswp_jobposter_username', 'jobposter' );
		if ( $user = get_user_by( 'login', $jobposter_username ) )
			return;

		echo '<div class="error"><p>';
		printf( __( 'ERROR: The username configured for posting jobs &mdash; %s &mdash; does not exist.', 'jobswp' ), $jobposter_username );
		echo '</p></div>';
	}

	/**
	 * Outputs button to close a job on the post edit page.
	 */
	public function post_submitbox_start() {
		global $post;

		// Only show the close button if viewing an existing job post and
		// the user has the ability to delete it.
		if ( ! $this->can_job_be_closed( $post ) )
			return;

		$post_type_object = get_post_type_object( $post->post_type );
		if ( ! $post_type_object )
			return;

		echo $this->_get_close_link( $post, 'button button-large alignright' );
	}

	/**
	 * Returns the markup for the admin link to close a given post.
	 *
	 * @param WP_Post $post The post.
	 * @param string $class Space-separated list of CSS classes to add to the markup link.
	 * @return string
	 */
	private function _get_close_link( $post, $class = '' ) {
		$post_type_object = get_post_type_object( $post->post_type );

		$action = 'close-job';
		$close_link = add_query_arg( 'action', $action, admin_url( sprintf( $post_type_object->_edit_link, $post->ID ) ) );
		$close_link = wp_nonce_url( $close_link, "$action-post_{$post->ID}" );

		$link = '<a href="' . $close_link . '"';
		if ( $class )
			$link .= ' class="' . esc_attr( $class ) . '"';
		$link .= ' title="' . esc_attr__( 'Close this job', 'jobswp' ) . '">';
		$link .= __( 'Close', 'jobswp' );
		$link .= '</a>';

		return $link;
	}

	/**
	 * Determines if a job is in a state that permits closure and that the current_user
	 * is capable of doing so.
	 *
	 * @param WP_Post The post.
	 * @return boolean True == the job can be closed
	 */
	protected function can_job_be_closed( $post ) {
		// The post must exist
		if ( ! $post )
			return false;

		// The post must be published
		if ( 'publish' != $post->post_status )
			return false;

		$post_type = $post->post_type;

		// The post must be of the 'job' post_type
		if ( 'job' != $post_type )
			return false;

		// The post_type object must instantiate
		$post_type_object = get_post_type_object( $post_type );
		if ( ! $post_type_object )
			return false;

		// The user must have the capability to delete such posts
		return current_user_can( $post_type_object->cap->delete_posts, $post->ID );
	}

	/**
	 * Handles admin request to close a job.
	 *
	 * Performs all necessary checks before calling close_job().
	 */
	function handle_close_job() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : null;

		if ( ! $post_id )
			wp_die( __( 'No job specified to close.', 'jobswp' ) );

		check_admin_referer( 'close-job-post_' . $post_id );

		$post = get_post( $post_id );

		if ( ! $post )
			wp_die( __( 'The job you are trying to close no longer exists.', 'jobswp' ) );

		$post_type = $post->post_type;
		$post_type_object = get_post_type_object( $post_type );

		if ( ! $post_type_object )
			wp_die( __( 'Unknown post type.' ) );

		if ( ! current_user_can( 'delete_post', $post_id ) )
			wp_die( __( 'You are not allowed to close this job.', 'jobswp' ) );

		if ( $user_id = wp_check_post_lock( $post_id ) ) {
			$user = get_userdata( $user_id );
			wp_die( sprintf( __( 'You cannot close this job. %s is currently editing.', 'jobswp' ), $user->display_name ) );
		}

		if ( ! $this->close_job( $post ) )
			wp_die( __( 'Error in closing job.', 'jobswp' ) );

		// Redirect back to jobs listing
		$sendback = wp_get_referer();
		if ( ! $sendback || strpos( $sendback, 'post.php' ) !== false ) {
			$sendback = admin_url( 'edit.php' );
			$sendback .= '?post_type=' . $post_type;
		} else {
			$sendback = remove_query_arg( array( 'close-job', 'closed-job', 'ids'), $sendback );
		}

		wp_redirect( add_query_arg( array( 'closed-job' => 1, 'ids' => $post_id ), $sendback ) );
		exit();
	}

	/**
	 * Closes a job.
	 *
	 * Presumes all checks have been performed.
	 */
	private function close_job( $post ) {
		$post->post_status = 'closed';
		return wp_update_post( $post );
	}

	/**
	 * Outputs admin notice when a job has been successfully closed.
	 */
	function job_closure_success() {
		global $pagenow;

		if ( 'edit.php' != $pagenow || ! isset( $_GET['closed-job'] ) || '1' != $_GET['closed-job'] )
			return;

		echo '<div class="updated"><p>';
		_e( 'Job closed.', 'jobswp' );
		echo '</p></div>';
	}

	/**
	 * Adds a 'Close' link to post_row_actions for appropriate jobs in admin listing.
	 *
	 * @param array $actions Existing post row actions
	 * @param WP_Post The post
	 * @return array
	 */
	public function post_row_actions( $actions, $post ) {
		if ( $this->can_job_be_closed( $post ) )
			$actions['close'] = $this->_get_close_link( $post );

		return $actions;
	}

	/**
	 * Replaces all malformed attempts at "WordPress" with "WordPress".
	 *
	 * This is a bit broad-stroked for general use, but should be sufficient for job postings.
	 *
	 * @param string $text Text to process for malformed "WordPress" usage
	 * @return string The fixed text
	 */
	public function WordPress_dangit( $text ) {
		return str_replace(
			array( 'Wordpress', 'wordpress', 'wordPress', 'word press', 'Word press', 'word Press', 'Word Press' ),
			'WordPress',
			$text
		);
		return $text;
	}

	/**
	 * Adds job postings to the feed.
	 *
	 * @param WP_Query $query The query object.
	 */
	public function add_jobs_to_feed( $query ) {
		if ( $query->is_feed() ) {
			$query->set( 'post_type', array( 'post', 'job' ) );
		}
	}

	/**
	 * Inserts the post-a-job form into the body of the post-a-job page.
	 *
	 * @param string $content Existing page content.
	 * @return string The content appended with the post-a-job form
	 */
	public function add_post_a_job_form( $content ) {
		if ( ! $this->skip_content && is_page( 'post-a-job' ) ) {
			$this->skip_content = true;
			if ( $this->success ) {
				$content .= get_template_part( 'content', 'post-job-success' );
				$GLOBALS['post'] = get_post( $this->success );
				setup_postdata( $GLOBALS['post'] );
			}
			$template = $this->success ? 'single' : 'post-job';
			$this->success = false;
			$content .= get_template_part( 'content', $template );
		}
		return $content;
	}

	/**
	 * Generates a random token.
	 *
	 * Incorporates the job id to further ensure uniqueness and to facilitate
	 * later use.
	 *
	 * @param int     $job_id The job post ID.
	 * @return string The token.
	 */
	protected function generate_job_token( $job_id ) {
		return $job_id . '|' . bin2hex( openssl_random_pseudo_bytes( 20 ) );
	}

	/**
	 * Gets the published job with the given token.
	 *
	 * @param string         $token  The token.
	 * @return WP_Post|false The job, or false if no matching job found.
	 */
	public function get_job_by_token( $token ) {
		$job = false;

		$parts = explode( '|', trim( $token ), 2 );

		if ( count( $parts ) > 1 ) {
			list( $job_id, $job_token ) = $parts;

			$stored_job_token = get_post_meta( (int) $job_id, 'job_token', true );

			if ( $token === $stored_job_token ) {
				$job = get_post( (int) $job_id );
			}
		}

		return $job;
	}

	/**
	 * Handles front-end submission of a job removal request.
	 */
	public function maybe_remove_job() {
		if ( isset( $_POST['removejob'] ) && 1 == $_POST['removejob'] ) {
			check_admin_referer( 'jobswpremovejob' );
			$has_errors    = false;
			$this->success = false;

			// Verify job token is provided.
			if ( ! isset( $_POST['job_token'] ) || empty( $_POST['job_token'] ) ) {
				$has_errors = true;
			}

			$has_errors = apply_filters( 'jobswp_remove_job_errors', $has_errors );

			// Only query for job if no errors thus far.
			if ( ! $has_errors ) {
				$job = $this->get_job_by_token( $_POST['job_token'] );
				if ( ! $job ) {
					$has_errors = __( 'The provided job token does not match an open or pending job posting.', 'jobswp' );
				}
			}

			if ( $has_errors ) {
				$_POST['errors'] = $has_errors;
			} else {
				$this->success = true;
			}

			// If everything checks out, try to remove the job.
			if ( $this->success ) {
				$updated = $this->close_job( $job );

				if ( is_wp_error( $updated ) || ! $updated ) {
					$this->success = false;
					$_POST['errors'] = __( 'Unable to remove job. Please try again or contact us for assistance.', 'jobswp' );
				} else {
					wp_safe_redirect( '/remove-a-job/?removedjob=1' );
				}
			}

		}
	}

	/**
	 * Emails the job poster after submission of their job posting.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	*/
	public function email_job_poster( $post_id, $post, $update ) {
		if ( ! $update ) {
			$to      = get_post_meta( $post_id, 'email', true );
			$title   = get_the_title( $post );
			$subject = sprintf( 'Job submitted: %s', $title );
			$token   = get_post_meta( $post_id, 'job_token', true );
			$body    = <<<EMAIL
Hi,

Your job "%s" has been successfully submitted to %s. Please be patient as it may take our team of volunteer moderators 24-48 hours to review and publish it to the site.

Take note of this special job token: %s

Your job will automatically be removed from the site after %s days. If you wish to remove the job sooner than that, you can do so by using the job removal form at %s and providing the job token provided above.

Cheers.

- The jobs.wordpress.net team.

EMAIL;

			$headers = '';
			$headers['From'] = 'jobs.wordpress.net <jobs@wordpress.net>';

			$body = sprintf( $body, $title, 'http://jobs.wordpress.net/', $token, self::get_days_until_pruning(), 'http://jobs.wordpress.net/remove-a-job/' );

			if ( $to ) {
				wp_mail( $to, $subject, $body, $headers );
			}
		}

		return $post_id;
	}

	/**
	 * Saves a job posting submission, which is coming from the front-end by an
	 * unverified visitor.
	 */
	public function save_job() {
		if ( isset( $_POST['postjob'] ) && 1 == $_POST['postjob'] ) {
			check_admin_referer( 'jobswppostjob' );
			$has_errors = false;
			$this->success = false;

			$needs_to_verify = ( ! isset( $_POST['verify'] ) || 1 != $_POST['verify'] );

			// Verify all required fields have values.
			foreach ( $this->required_fields as $field ) {
				if ( ! isset( $_POST[ $field ] ) || empty( $_POST[ $field ] ) ) {
					$has_errors = true;
					break;
				}
			}
			// Validate syntax of certain fields
			if ( ! $has_errors ) :
				if ( ! is_email( $_POST['email'] ) ) {
					$has_errors = __( 'The provided "Email Address" is not a proper email address.', 'jobswp' );
					unset( $_POST['email'] );
				} elseif ( 'email' == $_POST['howtoapply_method'] && ! is_email( $_POST['howtoapply'] ) ) {
					$has_errors = __( 'The provided "How to Apply" email address is not a proper email address.', 'jobswp' );
					unset( $_POST['howtoapply'] );
				}
			endif;

			// Validate acceptance of terms during review step.
			if ( ! $has_errors && ! $needs_to_verify && ( ! isset( $_POST['accept'] ) || 1 != $_POST['accept'] ) ) {
				$has_errors = __( 'You must also mark the checkbox indicating that you agree to the terms listed below the form.', 'jobswp' );
			}

			$has_errors = apply_filters( 'jobswb_save_job_errors', $has_errors );
			if ( $has_errors )
				$_POST['errors'] = $has_errors;
			elseif ( $needs_to_verify )
				$_POST['verify'] = true;
			else
				$this->success = true;

			// If everything checks out, create the job
			if ( $this->success ) {
				$job_id = $this->create_job();

				// Generate and store a unique token for the job, primarily to be used by
				// job posters to close their jobs themselves despite the site's lack of
				// users.
				$_POST['job_token'] = $this->generate_job_token( $job_id );
				add_post_meta( $job_id, 'job_token', $_POST['job_token'], true );

				if ( is_wp_error( $job_id ) ) {
					$_POST['errors'] = $job_id->get_error_message();
					$this->success = false;
				} else {
					$this->success = $job_id;
				}
			}

		}
	}

	/**
	 * Creates job based on POSTed data.
	 *
	 * Should only get called via save_job() since that function contains necessary
	 * user capability and data validation and verification checks.
	 *
	 * @return int|WP_Error The new job's ID or a WP_Error
	 */
	private function create_job() {
		$jobposter_username = apply_filters( 'jobswp_jobposter_username', 'jobposter' );
		if ( ! $user = get_user_by( 'login', $jobposter_username ) )
			return new WP_Error( 'jobswp_missing_user', __( 'The username configured for posting jobs does not exist.', 'jobswp' ) );

		$args = array(
			'post_author'  => $user->ID,
			'post_content' => $_POST['job_description'],
			'post_status'  => 'draft',
			'post_title'   => $_POST['job_title'],
			'post_type'    => 'job',
		);

		// Filter job posting content as if a comment, not a post
		remove_filter( 'content_save_pre', 'wp_filter_post_kses' );
		add_filter   ( 'content_save_pre', 'wp_filter_kses' );

		$job_id = wp_insert_post( $args );

		// Save post meta
		if ( $job_id ) {

			// Save job_category. Make sure it's one that is actually defined.
			$cats = wp_list_pluck( Jobs_Dot_WP::get_job_categories(), 'slug' );
			if ( in_array( $_POST['category'], $cats ) )
				wp_set_object_terms( $job_id, array( $_POST['category'] ), 'job_category', false );

			// Save each meta field
			foreach( $this->meta_fields as $field ) {
				if ( ! isset( $_POST[ $field ] ) || ! $_POST[ $field ] )
					continue;

				// Massage and sanitize the field value depending on field
				$val = self::validate_job_field( $field, $_POST[ $field ], $_POST );

				add_post_meta( $job_id, $field, $val );
			}
			return $job_id;
		}
	}

	/**
	 * Indicates if the query is an admin search against the job post type.
	 *
	 * @param object $query The query object.
	 * @return bool  True if the query is an admin search against the job post type, false otherwise.
	 */
	protected function is_admin_job_search( $query ) {
		return is_admin() && 'edit.php' === $GLOBALS['pagenow'] && $query->is_search() && 'job' === $query->query['post_type'] && '' !== $query->query_vars['s'];
	}

	/**
	 * Returns the SQL clauses to append to a query in order to query for
	 * job-specific meta query.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 * @return array   Associative array of `JOIN` and `WHERE` SQL.
	 */
	protected function get_admin_search_job_meta_sql( $query ) {
		global $wpdb;
		$fields_to_search = array( 'first_name', 'last_name', 'email', 'company' );

		$meta_query = array( 'relation' => 'OR' );

		foreach( $fields_to_search as $field ) {
			array_push( $meta_query, array(
				'compare' => 'LIKE',
				'key'     => $field,
				'value'   => $query->query_vars['s'],
			) );
		}

		return get_meta_sql( $meta_query, 'post', $wpdb->posts, 'ID', $query );
	}


	/**
	 * Modifies the JOIN clause of the query for searches in the admin for the job
	 * post type to include the postmeta table.
	 *
	 * @param string   $join  JOIN clause of the query.
	 * @param WP_Query $query The WP_Query instance.
	 * @return string
	 */
	public function admin_search_job_posts_join( $join, $query ) {
		// Don't change anything if not an admin job search.
		if ( ! $this->is_admin_job_search( $query ) ) {
			return $join;
		}

		// Get the necessary JOIN clause needed for a meta search.
		$meta_sql = $this->get_admin_search_job_meta_sql( $query );

		return $join . $meta_sql['join'];
	}

	/**
	 * Modifies the search SQL for searches in the admin for the job post type to
	 * also search certain custom fields.
	 *
	 * @param string   $search Search SQL for WHERE clause
	 * @param WP_Query $query  The WP_Query instance.
	 * @return string
	 */
	public function admin_search_job_posts_search( $search, $query ) {
		// Don't change anything if not an admin job search.
		if ( ! $this->is_admin_job_search( $query ) ) {
			return $search;
		}

		// Get the necessary WHERE clause for the meta fields to be searched.
		$meta_sql = $this->get_admin_search_job_meta_sql( $query );
		$where = $meta_sql['where'];

		// Change the default AND to an OR.
		if ( 0 === strpos( $where, ' AND ' ) ) {
			$where = ' OR ' . substr( $where, 5 );
		}

		// Insert the meta query into the search SQL.
		$search = substr( $search, 0, -3 ) . $where . '))';

		return $search;
	}

	/**
	 * Modifies the completed SQL for searches in the admin for the job post type
	 * to ensure only distinct results are returned.
	 *
	 * @param string   $request The complete SQL query.
	 * @param WP_Query $query   The WP_Query instance.
	 * @return string
	 */
	public function admin_search_job_posts_request( $request, $query ) {
		// Don't change anything if not an admin job search.
		if ( ! $this->is_admin_job_search( $query ) ) {
			return $request;
		}

		// Make the query distinct, unless it already is.
		if ( 0 !== strpos( $request, 'SELECT DISTINCT' ) ) {
			$request = 'SELECT DISTINCT' . substr( $request, 6 );
		}

		return $request;
	}

	/**
	 * Schedules job pruning if it isn't already scheduled.
	 *
	 * Jobs are scheduled to be pruned daily.
	 */
	private function schedule_job_pruning() {
		if ( ! wp_next_scheduled( 'jobswp_scheduled_job_pruning' ) && ! defined( 'WP_INSTALLING' ) )
			wp_schedule_event( time(), 'hourly', 'jobswp_scheduled_job_pruning' );
	}

	/**
	 * Prunes old jobs.
	 */
	public function scheduled_job_pruning() {
		global $wpdb;

		$wpdb->query( $wpdb->prepare(
			"UPDATE {$wpdb->prefix}posts
			 SET post_status = 'pruned'
			 WHERE DateDiff(NOW(), post_date) > %d AND post_type = 'job' AND post_status = 'publish'",
			self::get_days_until_pruning()
		) );
	}

	/**
	 * Unschedules job pruning cron.
	 */
	public static function unschedule_job_pruning() {
		wp_clear_scheduled_hook( 'jobswp_scheduled_job_pruning' );
	}

	/**
	 * Sanitizes job meta field data, sometimes based on context.
	 *
	 * Currently:
	 *   for 'email' : sanitizes the value as an email
	 *   for 'howtoapply' : depending on 'howtoapply_method', sanitizes the value as
	 *     email or URL
	 *
	 * @param string $field The field name
	 * @param string $value The field value
	 * @param array $extra_data Associated data to provide context for a field
	 * @return string
	 */
	public static function validate_job_field( $field, $value, $extra_data = array() ) {
		switch ( $field ) {
			case 'email':
				$value = sanitize_email( $value );
				break;
			case 'howtoapply':
				if ( isset( $extra_data['howtoapply_method'] ) ) {
					if ( 'email' == $extra_data['howtoapply_method'] )
						$value = sanitize_email( $value );
					elseif ( 'web' == $extra_data['howtoapply_method'] )
						$value = esc_url_raw( $value );
				}
		}

		return apply_filters( 'jobswp_job_field_validated', strip_tags( $value ), $field, $value, $extra_data );
	}

	/**
	 * Returns the array of default values for obtaining the job_category taxonomy
	 * when using core category functions.
	 *
	 * @return array
	 */
	private static function _job_category_defaults() {
		return array(
			'order'      => 'ASC',
			'orderby'    => 'name',
			'hide_empty' => false,
			'taxonomy'   => 'job_category',
			'title_li'   => '',
			'walker'     => self::$walker,
		);
	}

	/**
	 * Returns a list of existing job categories.
	 *
	 * Uses get_categories().
	 *
	 * @param array $args Additional arguments to pass along.
	 * @return array
	 */
	public static function get_job_categories( $args = array() ) {
		$defaults = self::_job_category_defaults();
		$args = wp_parse_args( $args, $defaults );
		return get_categories( $args );
	}

	/**
	 * Lists existing job categories.
	 *
	 * Uses wp_list_categories().
	 *
	 * @param array $args Additional arguments to pass along.
	 * @return array
	 */
	public static function list_job_categories( $args = array() ) {
		$defaults = self::_job_category_defaults();
		$args = wp_parse_args( $args, $defaults );
		return wp_list_categories( $args );
	}

	/**
	 * Returns the jobs in a given job category.
	 *
	 * @param int|string $category The category ID or slug.
	 * @param array $args Additional arguments
	 * @return array
	 */
	public static function get_jobs_for_category( $category, $args = array() ) {
		if ( is_numeric( $category ) )
			$category = get_term_by( 'id', $category, 'job_category' );
		elseif ( is_string( $category ) )
			$category = get_term( $category, 'job_category' );
	
		if ( ! is_object( $category ) )
			return array();

		$defaults = array(
			'orderby'        => 'post_date',
			'order'          => 'DESC',
			'posts_per_page' => 15,
			'post_type'      => 'job',
			'post_status'    => 'publish',
			'job_category'   => $category->slug,
		);
		$args = wp_parse_args( $args, $defaults );

		$q = new WP_Query( $args );

		return $q->get_posts();
	}
}

Jobs_Dot_WP::get_instance();
