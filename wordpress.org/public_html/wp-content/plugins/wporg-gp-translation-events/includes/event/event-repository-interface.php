<?php

namespace Wporg\TranslationEvents\Event;

use Exception;
use WP_Error;

interface Event_Repository_Interface {
	/**
	 * Insert a new Event.
	 *
	 * @param Event $event Event to insert.
	 *
	 * @return int|WP_Error The id of the inserted event, or an error.
	 */
	public function insert_event( Event $event );

	/**
	 * Update an Event.
	 *
	 * @param Event $event Event to update.
	 *
	 * @return int|WP_Error The id of the updated event, or an error.
	 */
	public function update_event( Event $event );

	/**
	 * Trash an Event.
	 *
	 * @param Event $event Event to trash.
	 *
	 * @return Event|false Trashed event or false on error.
	 */
	public function trash_event( Event $event );

	/**
	 * Permanently delete an Event.
	 *
	 * @param Event $event Event to permanently delete.
	 *
	 * @return Event|false Deleted event or false on error.
	 */
	public function delete_event( Event $event );

	/**
	 * Get an Event.
	 *
	 * @param int $id Event id.
	 *
	 * @return Event|null
	 */
	public function get_event( int $id ): ?Event;

	/**
	 * @throws Exception
	 */

	/**
	 * Get events that are currently active.
	 *
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_current_events( int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events that will be active in the future.
	 *
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_upcoming_events( int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events that were active in the past.
	 *
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_past_events( int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events that are trashed.
	 *
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_trashed_events( int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events for a given user. Includes events created by the user.
	 *
	 * @param int $user_id   Id of the user.
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events that are currently active for a given user.
	 *
	 * @param int $user_id   Id of the user.
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_current_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events that are currently active or happening in the future, for a given user.
	 *
	 * @param int $user_id   Id of the user.
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_current_and_upcoming_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events that are no longer active for a given user.
	 *
	 * @param int $user_id   Id of the user.
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_past_events_for_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events created by a given user.
	 *
	 * @param int $user_id   Id of the user.
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_events_created_by_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result;

	/**
	 * Get events hosted by a given user.
	 *
	 * @param int $user_id   Id of the user.
	 * @param int $page      Index of the page to return.
	 * @param int $page_size Page size.
	 *
	 * @return Events_Query_Result
	 * @throws Exception
	 */
	public function get_events_hosted_by_user( int $user_id, int $page = -1, int $page_size = -1 ): Events_Query_Result;
}

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

	public function __construct( array $events, int $current_page, int $page_count ) {
		$this->events = $events;

		// The call to intval() is required because WP_Query::max_num_pages is sometimes a float, despite being type-hinted as int.
		$this->page_count   = intval( $page_count );
		$this->current_page = intval( $current_page );
	}
}
