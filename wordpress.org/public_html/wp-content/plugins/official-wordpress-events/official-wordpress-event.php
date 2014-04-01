<?php

/*
 * An official WordPress event
 * 
 * This doesn't have any real functionality, but it exists to provide a standard data structure
 * for events across various event types.
 */
class Official_WordPress_Event {
	public $type, $title, $url, $start_timestamp, $end_timestamp, $location;

	/**
	 * Constructor
	 * 
	 * @param array $members
	 */
	public function __construct( $members ) {
		$valid_members = get_object_vars( $this );

		foreach ( $members as $member => $value ) {
			if ( array_key_exists( $member, $valid_members ) ) {
				$this->$member = $value;
			}
		}
	}
}
