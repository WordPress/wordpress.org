<?php
namespace Wporg\TranslationEvents\Event;

class Events_Query_Result {
	/**
	 * @var Event[]
	 */
	public array $events;

	public int $page_count;

	/**
	 * @var int The current page (starts at 1).
	 */
	public int $current_page;

	public array $event_ids;

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
