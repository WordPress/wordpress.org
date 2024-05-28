<?php

namespace Wporg\TranslationEvents\Event;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use WP_Error;
use WP_Post;
use WP_Query;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Translation_Events;

class Event_Repository implements Event_Repository_Interface {
	private const POST_TYPE = Translation_Events::CPT;

	private Attendee_Repository $attendee_repository;

	public function __construct( Attendee_Repository $attendee_repository ) {
		$this->attendee_repository = $attendee_repository;
	}

	/**
	 * Get or create the parent post for the year.
	 *
	 * @return int|WP_Error
	 */
	private function get_year_post_id( string $year ) {
		$year_post = get_page_by_path( $year, OBJECT, self::POST_TYPE );
		if ( ! $year_post ) {
			return wp_insert_post(
				array(
					'post_type'    => self::POST_TYPE,
					'post_title'   => $year,
					'post_name'    => $year,
					'post_status'  => 'publish',
					'post_content' => '',
				)
			);
		}
		return $year_post->ID;
	}

	public function insert_event( Event $event ) {
		$post_parent = $this->get_year_post_id( $event->start()->utc()->format( 'Y' ) );
		if ( is_wp_error( $post_parent ) ) {
			return $post_parent;
		}

		$event_id_or_error = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_name'    => $event->slug(),
				'post_title'   => $event->title(),
				'post_content' => $event->description(),
				'post_status'  => $event->status(),
				'post_parent'  => $post_parent,
			)
		);
		if ( $event_id_or_error instanceof WP_Error ) {
			return $event_id_or_error;
		}

		$event->set_id( $event_id_or_error );
		$this->update_event_meta( $event );
		return $event->id();
	}

	public function update_event( Event $event ) {
		$post_parent = $this->get_year_post_id( $event->start()->utc()->format( 'Y' ) );
		if ( is_wp_error( $post_parent ) ) {
			return $post_parent;
		}
		$event_id_or_error = wp_update_post(
			array(
				'ID'           => $event->id(),
				'post_name'    => $event->slug(),
				'post_title'   => $event->title(),
				'post_content' => $event->description(),
				'post_status'  => $event->status(),
				'post_parent'  => $post_parent,
			)
		);
		if ( $event_id_or_error instanceof WP_Error ) {
			return $event_id_or_error;
		}

		$this->update_event_meta( $event );
		return $event->id();
	}

	public function trash_event( Event $event ) {
		$result = wp_trash_post( $event->id() );
		if ( ! $result ) {
			return false;
		}
		return $event;
	}

	public function delete_event( Event $event ) {
		$result = wp_delete_post( $event->id(), true );
		if ( ! $result ) {
			return false;
		}

		// Delete attendees.
		$attendees = $this->attendee_repository->get_attendees( $event->id() );
		foreach ( $attendees as $attendee ) {
			$this->attendee_repository->remove_attendee( $event->id(), $attendee->user_id() );
		}

		// Delete stats.
		global $wpdb, $gp_table_prefix;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->delete(
			"{$gp_table_prefix}event_actions",
			array( 'event_id' => $event->id() ),
			array( '%d' ),
		);
		// phpcs:enable

		return $event;
	}

	public function get_event( int $id ): ?Event {
		$post = $this->get_event_post( $id );
		if ( ! $post ) {
			return null;
		}

		try {
			$meta = $this->get_event_meta( $id );
			if ( ! $meta ) {
				return null;
			}
			$event = new Event(
				intval( $post->post_author ),
				$meta['start'],
				$meta['end'],
				$meta['timezone'],
				$post->post_status,
				$post->post_title,
				$post->post_content,
			);
			$event->set_id( $post->ID );
			$event->set_slug( $post->post_name );
			return $event;
		} catch ( Exception $e ) {
			// This should not be possible as it means data in the database is invalid.
			// So we consider an invalid event to be not found.
			return null;
		}
	}

	public function get_current_events( int $page = -1, int $page_size = -1 ): Events_Query_Result {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		return $this->get_events_active_between(
			$now,
			$now,
			array(),
			$page,
			$page_size
		);
	}

	public function get_upcoming_events( int $page = - 1, int $page_size = - 1 ): Events_Query_Result {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'meta_query' => array(
					array(
						'key'     => '_event_start',
						'value'   => $now->format( 'Y-m-d H:i:s' ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
				'orderby'    => array( 'meta_value', 'ID' ),
				'order'      => 'ASC',
			)
		);
		// phpcs:enable
	}

	public function get_past_events( int $page = - 1, int $page_size = - 1 ): Events_Query_Result {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'meta_query' => array(
					array(
						'key'     => '_event_end',
						'value'   => $now->format( 'Y-m-d H:i:s' ),
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
				'orderby'    => array( 'meta_value', 'ID' ),
				'order'      => 'DESC',
			)
		);
		// phpcs:enable
	}

	public function get_trashed_events( int $page = - 1, int $page_size = - 1 ): Events_Query_Result {
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'post_status' => 'trash',
			)
		);
	}

	public function get_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'post_status' => array( 'publish', 'draft' ),
				'meta_key'    => '_event_start',
				'orderby'     => 'meta_value',
				'order'       => 'DESC',
			),
			array(),
			$user_id,
			true,
		);
		// phpcs:enable
	}

	public function get_current_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'meta_query' => array(
					array(
						'key'     => '_event_start',
						'value'   => $now->format( 'Y-m-d H:i:s' ),
						'compare' => '<=',
						'type'    => 'DATETIME',
					),
					array(
						'key'     => '_event_end',
						'value'   => $now->format( 'Y-m-d H:i:s' ),
						'compare' => '>=',
						'type'    => 'DATETIME',
					),
				),
				'meta_key'   => '_event_start',
				'orderby'    => 'meta_value',
				'order'      => 'ASC',
			),
			array(),
			$user_id,
		);
		// phpcs:enable
	}

	public function get_current_and_upcoming_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'meta_query' => array(
					array(
						'key'     => '_event_end',
						'value'   => $now->format( 'Y-m-d H:i:s' ),
						'compare' => '>',
						'type'    => 'DATETIME',
					),
				),
				'meta_key'   => '_event_start',
				'orderby'    => 'meta_value',
				'order'      => 'ASC',
			),
			array(),
			$user_id,
		);
		// phpcs:enable
	}

	public function get_past_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result {
		$now = new DateTimeImmutable( 'now', new DateTimeZone( 'UTC' ) );

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'meta_query' => array(
					array(
						'key'     => '_event_end',
						'value'   => $now->format( 'Y-m-d H:i:s' ),
						'compare' => '<',
						'type'    => 'DATETIME',
					),
				),
				'meta_key'   => '_event_start',
				'meta_type'  => 'DATETIME',
				'orderby'    => 'meta_value',
				'order'      => 'DESC',
			),
			array(),
			$user_id,
		);
		// phpcs:enable
	}

	public function get_events_created_by_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result {
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'post_status' => array( 'publish', 'draft' ),
				'author'      => $user_id,
				'meta_key'    => '_event_start',
				'orderby'     => 'meta_value',
				'order'       => 'DESC',
			)
		);
		// phpcs:enable
	}

	public function get_events_hosted_by_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result {
		global $wpdb, $gp_table_prefix;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching
		$events_user_is_hosting_ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				select distinct event_id
				from {$gp_table_prefix}event_attendees
				where user_id = %d
				and is_host = 1
			",
				array(
					$user_id,
				)
			),
		);

		if ( empty( $events_user_is_hosting_ids ) ) {
			return new Events_Query_Result( array(), 1, 1 );
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		return $this->execute_events_query(
			$page,
			$page_size,
			array(
				'post_status' => array( 'publish', 'draft' ),
				'meta_key'    => '_event_start',
				'orderby'     => 'meta_value',
				'order'       => 'DESC',
			),
			$events_user_is_hosting_ids
		);
		// phpcs:enable
	}

	/**
	 * @throws Exception
	 */
	protected function get_events_active_between(
		DateTimeImmutable $boundary_start,
		DateTimeImmutable $boundary_end,
		array $filter_by_ids = array(),
		int $page = -1,
		int $page_size = -1
	): Events_Query_Result {
		if ( $boundary_end < $boundary_start ) {
			throw new Exception( 'boundary end must not be before boundary start' );
		}

		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		$query_args = array(
			'meta_query' => array(
				array(
					'key'     => '_event_start',
					'value'   => $boundary_end->format( 'Y-m-d H:i:s' ),
					'compare' => '<',
					'type'    => 'DATETIME',
				),
				array(
					'key'     => '_event_end',
					'value'   => $boundary_start->format( 'Y-m-d H:i:s' ),
					'compare' => '>',
					'type'    => 'DATETIME',
				),
			),
			'meta_key'   => '_event_start',
			'meta_type'  => 'DATETIME',
			'orderby'    => array( 'meta_value', 'ID' ),
		);
		// phpcs:enable

		return $this->execute_events_query( $page, $page_size, $query_args, $filter_by_ids );
	}

	/**
	 * @throws Exception
	 */
	protected function assert_pagination_arguments( int $page, int $page_size ) {
		if ( -1 !== $page && $page <= 0 ) {
			throw new Exception( 'page must be greater than 0' );
		}
		if ( -1 !== $page_size && $page_size <= 0 ) {
			throw new Exception( 'page size must be greater than 0' );
		}
		if ( $page > 0 && -1 === $page_size ) {
			throw new Exception( 'if page is specified, page size must also be' );
		}
		if ( $page_size > 0 && -1 === $page ) {
			throw new Exception( 'if page size is specified, page must also be' );
		}
	}

	/**
	 * @throws InvalidStart
	 * @throws InvalidEnd
	 * @throws InvalidStatus
	 * @throws Exception
	 */
	private function execute_events_query(
		int $page,
		int $page_size,
		array $args,
		array $filter_by_ids = array(),
		int $user_id = null,
		bool $include_created_by_user = false
	): Events_Query_Result {
		$this->assert_pagination_arguments( $page, $page_size );

		$args = array_replace_recursive(
			$args,
			array(
				'post_type'      => self::POST_TYPE,
				'paged'          => $page,
				'posts_per_page' => $page_size,
			),
		);

		if ( ! isset( $args['post_status'] ) ) {
			$args['post_status'] = 'publish';
		}

		if ( ! empty( $filter_by_ids ) ) {
			$args['post__in'] = $filter_by_ids;
		}

		if ( null !== $user_id ) {
			$user_id_filter_callback = 'Wporg\TranslationEvents\Event\add_user_id_where_clause_to_events_query';
			$user_id_filter_priority = 10;
			// Only return events for which this user is an attendee, or (optionally) the event author.
			// We use a filter to modify the where clause of the query.
			// The filter removes itself, so it will only apply to the next query.
			add_filter( 'posts_where', $user_id_filter_callback, $user_id_filter_priority, 2 );
			$args['translation_events_user_id']                 = $user_id;
			$args['translation_events_include_created_by_user'] = $include_created_by_user;
		}

		$query = new WP_Query( $args );
		$posts = $query->get_posts();

		if ( isset( $user_id_filter_callback ) ) {
			// Remove the filter, so it only applies to this query.
			remove_filter( 'posts_where', $user_id_filter_callback, $user_id_filter_priority );
		}

		$events = array();
		foreach ( $posts as $post ) {
			$meta = $this->get_event_meta( $post->ID );

			$title = $post->post_title;
			if ( empty( $title ) ) {
				// Previously, it was possible for events to not have a title, so there can be events in the database
				// that do not have a title. To work around that, we set the title of those events to a single space.
				$title = ' ';
			}

			$event = new Event(
				intval( $post->post_author ),
				$meta['start'],
				$meta['end'],
				$meta['timezone'],
				$post->post_status,
				$title,
				$post->post_content,
			);
			$event->set_id( $post->ID );
			$event->set_slug( $post->post_name );
			$events[] = $event;
		}

		return new Events_Query_Result( $events, $page, $query->max_num_pages );
	}

	private function get_event_post( int $event_id ): ?WP_Post {
		if ( 0 === $event_id ) {
			return null;
		}
		$post = get_post( $event_id );
		if ( ! ( $post instanceof WP_Post ) ) {
			return null;
		}
		if ( self::POST_TYPE !== $post->post_type ) {
			return null;
		}

		return $post;
	}

	/**
	 * @throws Exception
	 */
	private function get_event_meta( int $event_id ): ?array {
		$meta = get_post_meta( $event_id );
		$utc  = new DateTimeZone( 'UTC' );

		if ( ! isset( $meta['_event_start'][0], $meta['_event_end'][0], $meta['_event_timezone'][0] ) ) {
			return null;
		}

		return array(
			'start'    => new Event_Start_Date( $meta['_event_start'][0], $utc ),
			'end'      => new Event_End_Date( $meta['_event_end'][0], $utc ),
			'timezone' => new DateTimeZone( $meta['_event_timezone'][0] ),
		);
	}

	private function update_event_meta( Event $event ) {
		$hosts     = $this->attendee_repository->get_hosts( $event->id() );
		$hosts_ids = array_map(
			function ( $host ) {
				return $host->user_id();
			},
			$hosts
		);
		$hosts_ids = implode( ', ', $hosts_ids );
		update_post_meta( $event->id(), '_event_start', $event->start()->utc()->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $event->id(), '_event_end', $event->end()->utc()->format( 'Y-m-d H:i:s' ) );
		update_post_meta( $event->id(), '_event_timezone', $event->timezone()->getName() );
		update_post_meta( $event->id(), '_hosts', $hosts_ids );
	}
}

// phpcs:ignore Universal.Files.SeparateFunctionsFromOO.Mixed
function add_user_id_where_clause_to_events_query( string $where, WP_Query $query ): string {
	$user_id = $query->get( 'translation_events_user_id' );
	if ( ! $user_id || ! is_int( $user_id ) ) {
		return $where;
	}

	$include_created_by_user = $query->get( 'translation_events_include_created_by_user' ) ?? false;

	global $wpdb, $gp_table_prefix;
	$posts_table     = "{$wpdb->prefix}posts";
	$attendees_table = "{$gp_table_prefix}event_attendees";

	$posts_where = array();
	if ( $include_created_by_user ) {
		$posts_where[] = "$posts_table.post_author = $user_id";
	}

	$event_ids = wp_cache_get( 'events_for_user_' . $user_id );
	if ( false === $event_ids ) {
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$event_ids = $wpdb->get_col( $wpdb->prepare( "SELECT event_id FROM $attendees_table WHERE user_id = %d", $user_id ) );
		// phpcs:enable
		wp_cache_set( 'events_for_user_' . $user_id, $event_ids );
	}

	if ( ! empty( $event_ids ) ) {
		$posts_where[] = "$posts_table.ID IN ( " . implode( ', ', array_map( 'intval', $event_ids ) ) . ' )';
	}
	if ( empty( $posts_where ) ) {
		// If there are no events for this user, we want to return an empty result set.
		return $where . ' AND 0 = 1';
	}

	return $where . ' AND ( ' . implode( ' OR ', $posts_where ) . ' )';
}
