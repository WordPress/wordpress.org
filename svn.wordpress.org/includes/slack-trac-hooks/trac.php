<?php

namespace Dotorg\SlackTracHooks;

abstract class Trac {
	protected $commit_channels = '#test';
	protected $commit_username = 'Commit';
	protected $ticket_channels = '#test';
	protected $ticket_username;

	protected $firehose_channel = false;

	protected $color    = '#21759b';
	protected $emoji    = ':wordpress:';

	protected $ticket_template = 'https://%s.trac.wordpress.org/ticket/%s';
	protected $commit_template = 'https://%s.trac.wordpress.org/changeset/%s';
	protected $ticket_range = array( 1, 5 );
	protected $commit_range = array( 1, 5 );

	function __construct() {
		// 'Dotorg\SlackTracHooks\Core_Trac' => 'Core_Trac'
		$class = str_replace( __NAMESPACE__ . '\\', '', get_class( $this ) );

		// 'Core_Trac' => 'core'
		$this->trac = strtolower( str_replace( '_Trac', '', $class ) );

		if ( ! isset( $this->ticket_username ) ) {
			// 'Core_Trac' => 'Core Trac'
			$this->ticket_username = str_replace( '_', ' ', $class );
		}
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

	function get_emoji() {
		return $this->emoji;
	}

	function get_ticket_template( $template = '%s' ) {
		return sprintf( $this->ticket_template, $this->trac, $template );
	}

	function get_commit_template( $template = '%s' ) {
		return sprintf( $this->commit_template, $this->trac, $template );
	}

	function get_digit_capture( $range ) {
		$min = $range[0] - 1;
		$max = $range[1] - 1;
		return '[0-9]\d{' . $min . ',' . $max . '}';
	}

	function get_log_replacements() {
		$commit_template = $this->get_commit_template( '$1' );
		$ticket_digits = $this->get_digit_capture( $this->ticket_range );
		$commit_digits = $this->get_digit_capture( $this->commit_range );

		return array(
			"/#($ticket_digits)\b/"   => $this->get_ticket_template( '$1' ),
			"/\[($commit_digits)\]/"  => $commit_template,
			"/\br($commit_digits)\b/" => $commit_template,
		);
	}

	function get_commit_channels( $changed_files = null ) {
		$channels = (array) $this->commit_channels;

		if ( isset( $this->firehose_channel ) ) {
			$channels[] = $this->firehose_channel;
		}

		if ( empty( $this->commit_path_filters ) || empty( $changed_files ) ) {
			return $channels;
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
	
		return $channels;
	}

	function get_ticket_channels( $ticket = null ) {
		$channels = (array) $this->ticket_channels;

		if ( isset( $this->firehose_channel ) ) {
			$channels[] = $this->firehose_channel;
		}

		if ( empty( $this->ticket_component_filters ) || empty( $ticket ) ) {
			return $channels;
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
		return $channels;
	}

	function get_firehose_channel() {
		return $this->firehose_channel;
	}
}

