<?php

namespace Dotorg\Slack\Trac;
use Dotorg\Slack\User;

class Trac implements User {
	protected $public = true;
	protected $commits = true;
	protected $tickets = true;

	protected $primary_channel  = false;
	protected $tickets_channel  = false;
	protected $commits_channel  = false;
	protected $firehose_channel = false;
	protected $commit_username;
	protected $ticket_username;

	// 'title', 'fields', 'description'
	protected $primary_channel_ticket_format = 'description';

	protected $bypass_primary_channel_for_commit_filter_matches = false;
	protected $bypass_primary_channel_for_ticket_filter_matches = false;

	protected $commit_path_filters = array();
	protected $ticket_component_filters = array();

	protected $color = '#0073aa';
	protected $icon  = ':wordpress:';

	protected $url_template    = 'https://%s.trac.wordpress.org';
	protected $ticket_template = 'https://%s.trac.wordpress.org/ticket/%s';
	protected $commit_template = 'https://%s.trac.wordpress.org/changeset/%s';
	protected $commit_info_template = 'https://%s.trac.wordpress.org/log/?rev=%s&format=changelog&limit=1&verbose=on';

	const min_digits = 2;
	const max_digits = 5;

	static protected $shorthands = array(
		'wp'        => 'core',
		'wordpress' => 'core',
		'develop'   => 'core',
		'bb'        => 'bbpress',
	);

	protected function __construct() {
		if ( isset( $this->trac, $this->name ) ) {
			return;
		}

		// 'Dotorg\Slack\Trac\Tracs\Core' => 'Core'
		$class = str_replace( __NAMESPACE__ . '\\Tracs\\', '', get_class( $this ) );

		if ( ! isset( $this->trac ) ) {
			$this->trac = strtolower( $class );
		}

		if ( ! isset( $this->name ) ) {
			$this->name = str_replace( '_', ' ', $class );
		}

		if ( ! isset( $this->ticket_username ) ) {
			$this->ticket_username = $this->name . ' Trac';
		}

		if ( ! isset( $this->commit_username ) ) {
			$this->commit_username = $this->name . ' commit';
		}

		foreach ( $this->commit_path_filters as $path => $channel ) {
			if ( is_string( $channel ) ) {
				$this->commit_path_filters[ $path ] = array( $channel => true );
			}
			if ( $this->bypass_primary_channel_for_commit_filter_matches && empty( $this->commit_path_filters[ $path ][ $this->primary_channel ] ) ) {
				$this->commit_path_filters[ $path ][ $this->primary_channel ] = false;
			}
		}

		foreach ( $this->ticket_component_filters as $component => $channel ) {
			if ( is_string( $channel ) ) {
				$this->ticket_component_filters[ $component ] = array( $channel => true );
			}
            if ( $this->bypass_primary_channel_for_ticket_filter_matches && empty( $this->ticket_component_filters[ $component ][ $this->primary_channel ] ) ) {
                $this->ticket_component_filters[ $component ][ $this->primary_channel ] = false;
            }
		}
	}

	static function get( $trac ) {
		require_once __DIR__ . '/config.php';
		if ( $trac instanceof Trac ) {
			return $trac;
		}

		$ns = __NAMESPACE__ . '\\Tracs\\';

		$class = $ns . $trac;
		if ( class_exists( $class ) ) {
			return new $class;
		}

		if ( isset( self::$shorthands[ $trac ] ) ) {
			$class = $ns . self::$shorthands[ $trac ];
			return new $class;
		}

		if ( preg_match( '~([a-z]+).trac.wordpress.org~', $trac, $match ) ) {
			$class = $ns . $match[1];
			if ( class_exists( $class ) ) {
				return new $class;
			}
		}

		return false;
	}

	static function get_regex() {
		return implode( '|', array_merge( self::get_registered_tracs(), array_keys( self::$shorthands ) ) );
	}

	static function get_registered_tracs() {
		require_once __DIR__ . '/config.php';
		$classes = get_declared_classes();
		$tracs = array();

		foreach ( $classes as $key => $class ) {
			if ( 0 !== strpos( $class, __NAMESPACE__ . '\\Tracs\\' ) ) {
				continue;
			}

			$tracs[] = strtolower( str_replace( array( __NAMESPACE__ . '\\Tracs\\', '_' ), array( '', '-' ), $class ) );
		}

		return $tracs;
	}

	function get_slug() {
		return $this->trac;
	}

	function get_url() {
		return sprintf( $this->url_template, $this->get_slug() );
	}

	function get_commit_username() {
		return $this->commit_username;
	}

	function get_ticket_username() {
		return $this->ticket_username;
	}

	function get_color() {
		return $this->color;
	}

	function get_name() {
		return $this->name . ' Trac';
	}

	function get_icon() {
		return $this->icon;
	}

	function get_ticket_url( $ticket ) {
		return $this->get_ticket_template( $ticket );
	}

	function get_ticket_template( $template = '%s' ) {
		return sprintf( $this->ticket_template, $this->trac, $template );
	}

	function get_commit_url( $commit ) {
		return $this->get_commit_template( $commit );
	}

	function get_commit_template( $template = '%s' ) {
		return sprintf( $this->commit_template, $this->trac, $template );
	}

	function get_commit_info_url( $commit ) {
		return sprintf( $this->commit_info_template, $this->trac, $commit );
	}

	function is_public() {
		return (bool) $this->public;
	}

	function has_tickets() {
		return (bool) $this->tickets;
	}

	function has_commits() {
		return (bool) $this->commits;
	}

	static function get_digit_capture() {
		$min = self::min_digits - 1;
		$max = self::max_digits - 1;
		return '[1-9][0-9]{' . $min . ',' . $max . '}';
	}

	function get_log_replacements() {
		$commit_template = $this->get_commit_template( '$1' );
		$digits = self::get_digit_capture();

		return array(
			"/#($digits)\b/"   => $this->get_ticket_template( '$1' ),
			"/\[($digits)\]/"  => $commit_template,
			"/\br($digits)\b/" => $commit_template,
		);
	}

	function get_commit_channels( $changed_files = null ) {
		$channels = array();

		if ( $this->primary_channel ) {
			$channels[ $this->primary_channel ] = true;
		}

		if ( $this->commits_channel ) {
			$channels[ $this->commits_channel ] = true;
		}

		foreach ( $this->commit_path_filters as $needle => $channels_to_add ) {
			if ( $needle[0] === '#' ) {
				// Append PCRE_MULTILINE so ^ and $ refer to individual lines.
				if ( preg_match( $needle . 'm', $changed_files ) ) {
					$channels = array_merge( $channels, (array) $channels_to_add );
				}
			} elseif ( false !== strpos( $changed_files, $needle ) ) {
				$channels = array_merge( $channels, (array) $channels_to_add );
			}
		}

		if ( $this->firehose_channel ) {
			$channels[ $this->firehose_channel ] = true;
		}

		return array_keys( array_filter( $channels ) );
	}

	function get_ticket_channels( $ticket = null ) {
		$channels = array();

		if ( $this->primary_channel ) {
			$channels[ $this->primary_channel ] = true;
		}

		if ( $this->tickets_channel ) {
			$channels[ $this->tickets_channel ] = true;
		}

		// Component and focuses are needed for the component filters.
		if ( $ticket instanceof Ticket  ) {
			$ticket->fetch();
		}

		if ( isset( $ticket->component ) && isset( $this->ticket_component_filters[ $ticket->component ] ) ) {
			$channels = array_merge( $channels, (array) $this->ticket_component_filters[ $ticket->component ] );
		}

		if ( isset( $ticket->focuses ) ) {
			foreach ( explode( ', ', $ticket->focuses ) as $focus ) {
				if ( isset( $this->ticket_component_filters[ $focus ] ) ) {
					$channels = array_merge( $channels, (array) $this->ticket_component_filters[ $focus ] );
				}
			}
		}

		if ( $this->firehose_channel ) {
			$channels[ $this->firehose_channel ] = true;
		}

		return array_keys( array_filter( $channels ) );
	}

	function get_firehose_channel() {
		return $this->firehose_channel;
	}

	function get_ticket_format( $channel ) {
		if ( $channel === $this->primary_channel ) {
			return $this->primary_channel_ticket_format;
		}
		return 'description';
	}

	static function format_for_slack( $text ) {
		$text = str_replace( "\r\n", "\n", $text );
		$text = preg_replace( "~^{{{\n?#!([\w+-/]+)$~m", '{{{' , $text ); // {{{#!php
		$text = trim( str_replace(
			array( "\n{{{\n", "\n}}}\n", '{{{', '}}}' ),
			array( "\n```\n", "\n```\n", '`',   '`' ),
			"\n$text\n"
		), "\n" );

		// TODO: bold, italic, links

		return $text;
	}
}
