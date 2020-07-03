<?php

namespace Dotorg\Slack\Trac;

class Commit extends Resource {
	protected $data;

	function get_text() {
		$this->fetch();

		if ( false === $this->data ) {
			return $this->get_url();
		}

		return sprintf( '<%s|r%s>: %s', $this->get_url(), $this->id, self::format_commit_for_slack( $this->trac, $this->message ) );
	}

	function get_short_attachment() {
		return $this->get_attachment();
	}

	function get_attachment() {
		$this->fetch();

		if ( false === $this->data ) {
			return false;
		}

		$username = $this->trac->get_commit_username();
		$revision = 'r' . $this->id;
		$author   = $this->author;
		$message  = self::format_commit_for_slack( $this->trac, $this->message );

		$attachment = [];

		$attachment['title']      = "$username $revision";
		$attachment['title_link'] = $this->get_url();

		$attachment['author_name'] = $author;
		$attachment['author_icon'] = sprintf( 'https://wordpress.org/grav-redirect.php?user=%s&s=32', $author );

		$attachment['text']      = $message;
		$attachment['fallback']  = "$username $revision by $author";
		$attachment['mrkdwn_in'] = [ 'text' ];

		$attachment['ts']          = $this->created;
		$attachment['footer']      = sprintf( '<%s|%s>', $this->trac->get_url(), $this->trac->get_name() );
		$attachment['footer_icon'] = sprintf( '%s/chrome/common/trac.ico', $this->trac->get_url() );

		return $attachment;
	}

	function fetch() {
		if ( isset( $this->data ) ) {
			return $this->data;
		}

		if ( ! $this->trac->is_public() || ! $this->trac->has_commits() ) {
			$this->data = false;
			return;
		}

		$url = $this->trac->get_commit_info_url( $this->id );

		$context = stream_context_create( array(
			'http' => array(
				'user_agent' => 'WordPress.org Trac:Slack Notifications'
			)
		) );

		$contents = @file_get_contents( $url, false, $context );
		if ( false === $contents ) {
			$this->data = false;
			return;
		}

		// Fragile, but does the trick. First \n\n is the changelog header.
		// Second \n\n separates the metadata (including file changes)
		// from the log message.
		$contents = explode( "\n\n", $contents, 3 );

		// Get author and timestamp.
		$metadata = strtok( $contents[1], "\n" ); // <date> GMT <committer> [<rev>]
		preg_match( '/^(?<date>(?:.+)GMT) (?<author>.*) \[/', $metadata, $matches );
		$created = strtotime( $matches['date'] );
		$author  = $matches['author'];

		// Trim tabs from the start of each line.
		$message = preg_replace( '/^\t+/m', '', $contents[2] );

		$this->data = (object) compact( 'created', 'author', 'message' );
		return $this->data;
	}

	static function format_commit_for_slack( Trac $trac, $message ) {
		// Convert ASCII numbers to an UTF-8 character, like ?\226?\128?\148 => — (m-dash).
		$message = preg_replace_callback( '/(?:\?\\\\(\d{1,3}))/', function( $matches ) {
			return chr( $matches[1] );
		}, $message );

		// Converts {U+201C} to an UTF-8 character.
		$message = preg_replace_callback( '/\{U\+([0-9A-F]{4,6})\}/', function( $matches ) {
			return html_entity_decode( "&#x{$matches[1]};", ENT_NOQUOTES, 'UTF-8' );
		}, $message );

		foreach ( $trac->get_log_replacements() as $find => $replace ) {
			$message = preg_replace( $find, '<' . $replace . '|$0>', $message );
		}
		return $message;
	}
}
