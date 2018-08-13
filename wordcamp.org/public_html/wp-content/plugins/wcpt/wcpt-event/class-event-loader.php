<?php
/**
 * Implements Event_Loader class
 *
 * @package WordCamp Post Type
 */

/**
 * Class Event_Loader
 */
abstract class Event_Loader {

	/**
	 * Event_Loader constructor. Add common hooks.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'includes' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_post_capabilities' ) );
		add_action( 'init', array( $this, 'register_post_statuses' ) );
		add_filter( 'pre_get_posts', array( $this, 'query_public_statuses_on_archives' ) );
	}

	/**
	 * Include all event specific dependent files.
	 *
	 * @return void
	 */
	abstract protected function includes();

	/**
	 * Register event custom post type.
	 *
	 * @return void
	 */
	abstract protected function register_post_types();

	/**
	 * Add capabilities to roles for post type.
	 *
	 * @return void
	 */
	abstract protected function register_post_capabilities();

	/**
	 * Return list of available post statuses with their labels.
	 *
	 * @return array
	 */
	abstract public static function get_post_statuses();

	/**
	 * Register post statuses for this event type.
	 */
	public function register_post_statuses() {
		foreach ( $this->get_post_statuses() as $key => $label ) {
			register_post_status(
				$key, array(
					'label'       => $label,
					'public'      => true,
					'label_count' => _nx_noop(
						sprintf( '%s <span class="count">(%s)</span>', $label, '%s' ),
						sprintf( '%s <span class="count">(%s)</span>', $label, '%s' ),
						'wordcamporg'
					),
				)
			);
		}
	}

	/**
	 * List of statuses when an Event can be tracked in any public facing widget.
	 *
	 * @return array
	 */
	abstract public static function get_public_post_statuses();

	/**
	 * Only query the public post statuses on WordCamp archives and feeds
	 *
	 * By default, any public post statuses are queried when the `post_status` parameter is not explicitly passed
	 * to WP_Query. This causes central.wordcamp.org/wordcamps/ and central.wordcamp.org/wordcamps/feed/ to display
	 * camps that are `needs-vetting`, etc, which is not desired.
	 *
	 * Another way to fix this would have been to register some of the posts statuses as `private`, but they're not
	 * consistently used in a public or private way, so that would have had more side effects.
	 *
	 * @param WP_Query $query
	 */
	public function query_public_statuses_on_archives( $query ) {
		if ( ! $query->is_post_type_archive( WCPT_POST_TYPE_ID ) ) {
			return;
		}

		if ( is_admin() ) {
			return;
		}

		// Sort by the date it was added to the schedule. See WordCamp_Loader::set_scheduled_date() for details.
		if ( '' === $query->get( 'orderby' ) ) {
			$query->set( 'orderby', 'menu_order date' );
		}

		if ( ! empty( $query->query_vars['post_status'] ) ) {
			return;
		}

		$query->query_vars['post_status'] = $this->get_public_post_statuses();
	}

}
