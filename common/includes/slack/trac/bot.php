<?php

namespace Dotorg\Slack\Trac;

class Bot {
	const default_trac = 'core';

	static private $default_tracs = array(
		'#themereview'  => 'themes',
		'#bbpress'      => 'bbpress',
		'#buddypress'   => 'buddypress',
		'#glotpress'    => 'glotpress',
		'#meta'         => 'meta',
		'#core-restapi' => false, // ideally: GH WP-API/WP-API
		'#outreach'     => false,
	);

	protected $parsed = array();

	// We want to post to Trac no more than once per two hours, and to Slack no more than once every 10 minutes.
	// After 5 minutes for Slack, show a link only and extend it the redundancy.
	private $redundant_time = array( 'slack' => 600, 'trac' => 7200 );
	// After 5 minutes for Slack, show a link only. Extend the redundany check.
	const slack_repost_link = 300;

	private $avoid_redundancy = false;

	function __construct( $post_data ) {
		$this->post_data = $post_data;
	}

	function get_channel() {
		return '#' . $this->post_data['channel_name'];
	}

	function parse() {
		$this->parse_tickets( $this->post_data['text'] );
		$this->parse_commits( $this->post_data['text'] );
		return $this->parsed;
	}

	function parse_tickets( $text ) {
		$digits = Trac::get_digit_capture();
		$ticket_tracs = '?<trac>' . Trac::get_regex();
		preg_match_all( "/(?:\s|^|\()#(?<id>$digits)(?:\-($ticket_tracs))?\b/", $text, $tickets, PREG_SET_ORDER );
		preg_match_all( "/(?:\s|^|\()#($ticket_tracs)(?<id>$digits)\b/", $text, $tickets_alt, PREG_SET_ORDER );
		preg_match_all( "~https?://($ticket_tracs).trac.wordpress.org/ticket/(?<id>$digits)~", $text, $tickets_url, PREG_SET_ORDER );
		foreach ( $tickets_url as &$ticket ) {
			$ticket['url'] = true;
		}
		$tickets = array_merge( $tickets, $tickets_alt, $tickets_url );
		$this->finish_parsing( 'ticket', $tickets );
	}

	function parse_commits( $text ) {
		$digits = '[1-9][0-9]{1,4}'; // two digits minimum, five digits maximum (avoid clashing with colors), cannot start with 0.
		$commit_tracs = '?<trac>' . Trac::get_regex();
		preg_match_all( "/\br(?<id>$digits)(?:\-($commit_tracs))?\b/", $text, $revisions, PREG_SET_ORDER );
		preg_match_all( "/\[($commit_tracs)?(?<id>$digits)\]/", $text, $commits, PREG_SET_ORDER );
		preg_match_all( "/\[(?<id>$digits)-($commit_tracs)\]/", $text, $commits_alt, PREG_SET_ORDER );
		// Edge case: Doesn't handle the design changesets. I don't care.
		preg_match_all( "~https?://($commit_tracs).trac.wordpress.org/changeset/(?<id>$digits)~", $text, $changesets_url, PREG_SET_ORDER );

		foreach ( $changesets_url as &$changeset ) {
			$changeset['url'] = true;
		}

		$commits = array_merge( $commits, $commits_alt, $revisions, $changesets_url );
		$this->finish_parsing( 'commit', $commits );
	}

	function finish_parsing( $type, $items ) {
		foreach ( $items as $item ) {
			$trac = $this->parse_trac( $item );
			if ( ! $trac ) {
				continue;
			}

			if ( $type === 'ticket' && ! $trac->has_tickets() ) {
				continue;
			} elseif ( $type === 'commit' && ! $trac->has_commits() ) {
				continue;
			}

			unset( $item[0], $item[1], $item[2] );
			$trac = $trac->get_slug();

			if ( ! isset( $this->parsed[ $trac ][ $type ] ) ) {
				$this->parsed[ $trac ][ $type ] = array( $item );
			} elseif ( ! in_array( $item['id'], self::array_column( $this->parsed[ $trac ][ $type ], 'id' ) ) ) {
				$this->parsed[ $trac ][ $type ][] = $item;
			}
		}
	}

	static function array_column( $array, $column ) {
		$results = array();
		foreach ( $array as $item ) {
			$results[] = $item[ $column ];
		}
		return $results;
	}

	function parse_trac( $item ) {
		if ( ! empty( $item['trac'] ) ) {
			return Trac::get( $item['trac'] );
		}

		$channel = $this->get_channel();
		if ( ! empty( self::$default_tracs[ $channel ] ) ) {
			return Trac::get( self::$default_tracs[ $channel ] );
		} elseif ( isset( self::$default_tracs[ $channel ] ) ) {
			return false;
		}

		list( $channel_namespace ) = explode( '-', $channel, 2 );
		if ( ! empty( self::$default_tracs[ $channel_namespace ] ) ) {
			return Trac::get( self::$default_tracs[ $channel_namespace ] );
		} elseif ( isset( self::$default_tracs[ $channel_namespace ] ) ) {
			return false;
		}

		return Trac::get( self::default_trac );
	}

	// Redundancy functions

	function avoid_redundancy() {
		$this->avoid_redundancy = true;
		wp_cache_init();
	}

	function is_redundant( $realm, $trac, $type, $id ) {
		if ( ! $this->avoid_redundancy ) {
			return false;
		}
		return wp_cache_get( $this->redundancy_key( $realm, $trac, $type, $id ), 'tracslack' );
	}

	function set_redundancy( $realm, $trac, $type, $id ) {
		if ( ! $this->avoid_redundancy ) {
			return;
		}
		wp_cache_set( $this->redundancy_key( $realm, $trac, $type, $id ), time(), 'tracslack', $this->redundant_time[ $realm ] );
	}

	function redundancy_key( $realm, $trac, $type, $id ) {
		return sprintf( 'chan:%s:%s:%s:%s:%d', $this->get_channel(), $realm, $trac, $type, $id );
	}
}
