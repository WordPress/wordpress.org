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

		$text = $this->get_text();

		return array(
			'text'      => $text,
			'fallback' => $text,
			'mrkdwn_in' => array( 'text', 'fallback' ),
		);
	}

	function fetch() {
		if ( isset( $this->message ) ) {
			return $this->message;
		}

		if ( ! $this->trac->is_public() || ! $this->trac->has_commits() ) {
			$this->message = false;
			return;
		}

		$url = $this->trac->get_commit_info_url( $this->id );
		$contents = @file_get_contents( $url );
		if ( false === $contents ) {
			$this->message = false;
			return;
		}

		// Fragile, but does the trick. First \n\n is the changelog header.
		// Second \n\n separates the metadata (including file changes)
		// from the log message.
		$contents = explode( "\n\n", $contents, 3 );
		// Trim tabs from the start of each line.
		$message = preg_replace( '/^\t+/', '', $contents[2] );

		$this->message = $message;
		$this->fetched = true;
	}

	static function format_commit_for_slack( Trac $trac, $message ) {
		foreach ( $trac->get_log_replacements() as $find => $replace ) {
			$message = preg_replace( $find, '<' . $replace . '|$0>', $message );
		}
		return $message;
	}
}
