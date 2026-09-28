<?php
/**
 * Value object for the result of an event query.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Event;

/**
 * Result of an Event_Repository query, one page of events plus paging information.
 */
class Events_Query_Result {
	/**
	 * The events in the current page.
	 *
	 * @var Event[]
	 */
	public array $events;

	/**
	 * Total number of pages.
	 *
	 * @var int
	 */
	public int $page_count;

	/**
	 * The current page (starts at 1).
	 *
	 * @var int
	 */
	public int $current_page;

	/**
	 * The post IDs of the events in the current page.
	 *
	 * @var int[]
	 */
	public array $event_ids;

	/**
	 * Events_Query_Result constructor.
	 *
	 * @param Event[] $events       The events in the current page.
	 * @param int     $current_page The current page (starts at 1).
	 * @param int     $page_count   Total number of pages.
	 */
	public function __construct( array $events, int $current_page, int $page_count ) {
		$this->events    = $events;
		$this->event_ids = array_map(
			function ( $event ) {
				return $event->id();
			},
			$events,
		);
		// The call to intval() is required because WP_Query::max_num_pages is sometimes a float, despite being type-hinted as int.
		$this->page_count   = intval( $page_count );
		$this->current_page = intval( $current_page );
	}
}
