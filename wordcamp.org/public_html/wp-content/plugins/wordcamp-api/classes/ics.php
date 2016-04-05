<?php

class WordCamp_API_ICS {
	public $ttl = 1; // seconds to live
	const CLRF = "\r\n";

	function __construct() {
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'parse_request', array( $this, 'parse_request' ) );
	}

	function init() {
		add_rewrite_rule( '^calendar\.ics$', 'index.php?wcorg_wordcamps_ical=1', 'top' );
	}

	function query_vars( $query_vars ) {
		array_push( $query_vars, 'wcorg_wordcamps_ical' );
		return $query_vars;
	}

	function parse_request( $request ) {
		if ( empty( $request->query_vars[ 'wcorg_wordcamps_ical' ] ) )
			return;

		header( 'Content-type: text/calendar; charset=utf-8' );
		header( 'Content-Disposition: inline; filename=calendar.ics' );
		echo $this->get_ical_contents();
		exit;
	}

	function get_ical_contents() {
		$cache_key = 'wcorg_wordcamps_ical';

		$cache = get_option( $cache_key, false );
		if ( is_array( $cache ) && $cache['timestamp'] > time() - $this->ttl )
			return $cache['contents'];

		$cache = array( 'contents' => $this->generate_ical_contents(), 'timestamp' => time() );
		delete_option( $cache_key );
		add_option( $cache_key, $cache, false, 'no' );

		return $cache['contents'];
	}

	function generate_ical_contents() {
		if ( ! defined( 'WPCT_POST_TYPE_ID' ) )
			define( 'WPCT_POST_TYPE_ID', 'wordcamp' );

		$ical = 'BEGIN:VCALENDAR' . self::CLRF;
		$ical .= 'VERSION:2.0'  . self::CLRF;
		$ical .= 'PRODID:-//hacksw/handcal//NONSGML v1.0//EN' . self::CLRF;

		$query = new WP_Query( array(
			'post_type'      => WCPT_POST_TYPE_ID,
			'post_status'    => array(
				'wcpt-scheduled',
				'wcpt-needs-debrief',
				'wcpt-debrief-schedul',
				'wcpt-closed',

				// back-compat
				'publish',
			),
			'posts_per_page' => 50,
			'meta_key'       => 'Start Date (YYYY-mm-dd)',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( array(
				'key'        => 'Start Date (YYYY-mm-dd)',
				'value'      => strtotime( '-15 days' ),
				'compare'    => '>'
			) )
		) );

		while ( $query->have_posts() ) {
			$query->the_post();

			$uid = get_permalink();
			$start = get_post_meta( get_the_ID(), 'Start Date (YYYY-mm-dd)', true );
			$end = get_post_meta( get_the_ID(), 'End Date (YYYY-mm-dd)', true );
			if ( ! $end )
				$end = strtotime( '+1 day', $start );

			$uid = get_the_ID();
			$title = get_the_title();
			$start = date( 'Ymd', $start );
			$end = date( 'Ymd', $end );

			/*
			 * Specifying the start time as 000000 is a workaround for a bug in Google Calendar
			 * @see https://productforums.google.com/forum/#!category-topic/calendar/report-an-issue/importing-and-exporting/google-chrome/UQ6P1Im8eY8
			 */
			$ical .= "BEGIN:VEVENT" . self::CLRF;
			$ical .= "UID:$uid" . self::CLRF;
			$ical .= "DTSTAMP:$start" . 'T000000Z' . self::CLRF;
			$ical .= "DTSTART;VALUE=DATE:$start" . self::CLRF;
			$ical .= "DTEND;VALUE=DATE:$end" . self::CLRF;
			$ical .= "SUMMARY:$title" . self::CLRF;
			$ical .= "END:VEVENT" . self::CLRF;
		}

		$ical .= 'END:VCALENDAR';
		return $ical;
	}
}
