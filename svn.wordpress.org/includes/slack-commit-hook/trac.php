<?php

namespace SlackCommitHook;

abstract class Trac {
	protected $channels = '#test';
	protected $username = 'Commit';
	protected $color    = '#21759b';
	protected $emoji    = ':wordpress:';

	protected $ticket_template = 'https://%s.trac.wordpress.org/ticket/%s';
	protected $commit_template = 'https://%s.trac.wordpress.org/changeset/%s';
	protected $ticket_range = array( 1, 5 );
	protected $commit_range = array( 1, 5 );

	function __construct() {
		$this->trac = str_replace( '_trac', '', strtolower( get_class( $this ) ) );
	}

	function get_username() {
		return $this->username;
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

	function get_channels( $changed_files = null ) {
		$channels = (array) $this->channels;

		if ( empty( $this->channel_matcher ) || empty( $changed_files ) ) {
			return $channels;
		}

		foreach ( $this->channel_matcher as $needle => $channels_to_add ) {
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
}
