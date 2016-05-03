<?php

namespace Dotorg\Slack\Trac;

class Comment_Handler {

	function __construct( \Dotorg\Slack\Send $send, array $email_message ) {
		$this->send  = $send;
		$this->lines = $email_message;
	}

	function run() {
		$this->process_message();

		// Don't post auto-comments for commits.
		if ( false !== strpos( $this->comment, '#!CommitTicketReference' ) ) {
			return;
		}

		$this->generate_payload();
		$firehose = $this->trac->get_firehose_channel();

		// We still want to process this payload even if no firehose channel
		// is specified, as we may use it to process mentions and such.
		if ( $firehose ) {
			$this->send->send( $firehose );
		}
	}

	function process_message() {
		$lines = array_map( 'rtrim', $this->lines );
		$base64 = false;

		// Trim off headers.
		while ( $lines && '' !== current( $lines ) ) {
			$line = array_shift( $lines );
			if ( 0 === strpos( $line, 'X-Trac-Ticket-URL:' ) ) {
				// X-Trac-Ticket-URL: https://core.trac.wordpress.org/ticket/12345#comment:1
				list( , $comment_url ) = explode( ': ', $line );
				list( $ticket_url, $comment_id ) = explode( '#comment:', $comment_url );
				list( $trac_url, $ticket_id ) = explode( '/ticket/', $ticket_url );

				$trac = Trac::get( $trac_url );
				if ( ! $trac ) {
					return false;
				}
			} elseif ( 0 === strpos( $line, 'Content-Transfer-Encoding: base64' ) ) {
				$base64 = true;
			}
		}

		if ( $base64 ) {
			$lines = explode( "\n", base64_decode( implode( "\n", $lines ) ) );
		}

		// Remove empty line between headers and body if not base64.
		if ( ! $base64 ) {
			array_shift( $lines );
		}

		$title = '';
		while ( $lines && 0 !== strpos( current( $lines ), '------' ) ) {
			if ( '' !== $title ) {
				$last = substr( $title, -1 );
				if ( $last !== '-' && $last !== '_' ) {
					$title .= ' ';
				}
			}
			$title .= array_shift( $lines );
		}
		$title = substr( $title, strpos( $title, ': ' ) + 2 );

		// Remove up to top of ticket properties table.
		while ( $lines && 0 !== strpos( current( $lines ), '------' ) ) {
			array_shift( $lines );
		}
		// Remove top border of table.
		array_shift( $lines );
		// Remove ticket properties table.
		while ( $lines && 0 !== strpos( current( $lines ), '------' ) ) {
			array_shift( $lines );
		}
		// Remove bottom border of table.
		array_shift( $lines );

		// Remove empty line if present. (It is when it's a comment without changes.)
		if ( current( $lines ) === '' ) {
			array_shift( $lines );
		}

		// Remove Trac email footer.
		while ( $lines && end( $lines ) !== '--' ) {
			array_pop( $lines );
		}
		// Remove -- which starts footer.
		array_pop( $lines );
		// Remove empty line before footer.
		array_pop( $lines );

		preg_match( '/^(Comment|Changes|Description changed) \(?by (.*[^\)])\)?:$/', array_shift( $lines ), $matches );
		$has_changes = $matches[1] === 'Changes';
		$author = $matches[2];

		// Remove blank line after 'Comment|Changes (by author):' or 'Description changed by author:'.
		array_shift( $lines );

		$changes = $comment = array();
		if ( $has_changes ) {
			while ( $lines && '' !== current( $lines ) ) {
				$changes[] = preg_replace( '~^ \* (.*?):  ~', '_*$1:*_ ', array_shift( $lines ) );
			}
		}

		// Remove blank lines (should be two if it had changes).
		while ( $lines && '' === current( $lines ) ) {
			array_shift( $lines );
		}

		// Next line should start with 'Comment' if there is one.
		if ( $has_changes && 0 === strpos( current( $lines ), 'Comment' ) ) {
			array_shift( $lines ); // Remove 'Comment'
			array_shift( $lines ); // Remove blank line
		}

		// Everything left is the comment. Remove leading space.
		$comment = implode( "\n", array_map( 'ltrim', $lines ) );

		$this->trac    = $trac;
		$this->title   = $title;
		$this->author  = $author;
		$this->comment = $comment;
		$this->changes = $changes;
		$this->ticket_id   = $ticket_id;
		$this->ticket_url  = $ticket_url;
		$this->comment_id  = $comment_id;
		$this->comment_url = $comment_url;
	}

	function format_comment_for_slack() {
		// Link 'Replying to [comment:1 user]:'
		$ticket_url = $this->ticket_url;
		$comment = preg_replace_callback( '/Replying to \[comment:(\d+) (.*)\]/m',
			function ( $matches ) use ( $ticket_url ) {
				$comment_url = $ticket_url . '#comment:' . $matches[1];
				$text = 'Replying to ' . $matches[2];
				return "<$comment_url|$text>";
			}, $this->comment );

		$comment = Trac::format_for_slack( $comment );
		return $comment;
	}

	function generate_payload() {
		$this->send->set_icon( $this->trac->get_icon() );
		$this->send->set_username( $this->trac->get_ticket_username() );

		$comment         = $this->format_comment_for_slack();
		$main_attachment = $this->changes ? implode( "\n", $this->changes ) : $comment;
		$pretext         = sprintf( '*%s updated <%s|#%s %s>*', $this->author, $this->comment_url, $this->ticket_id, htmlspecialchars( $this->title, ENT_NOQUOTES ) );
		$fallback        = trim( $pretext, '*' ) . "\n" . $main_attachment;

		$attachment = array(
			'pretext'   => $pretext,
			'fallback'  => $fallback,
			'text'      => $main_attachment,
			'mrkdwn_in' => array( 'pretext', 'fallback', 'text' ),
		);

		// Ensure the comment uses a darker gray color, even when alone.
		if ( ! $this->changes ) {
			$attachment['color'] = '#999';
		}

		$this->send->add_attachment( $attachment );

		// If we have both changes and a comment, append the comment.
		if ( $this->changes && $comment ) {
			$this->send->add_attachment( array(
				'fallback'  => $comment,
				'text'      => $comment,
				'mrkdwn_in' => array( 'fallback', 'text' ),
				'color'     => '#999',
			) );
		}
	}
}
