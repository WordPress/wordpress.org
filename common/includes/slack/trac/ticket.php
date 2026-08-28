<?php

namespace Dotorg\Slack\Trac;

class Ticket extends Resource {
	protected $data;
	/**
	 * Whether the Trac query matched no ticket.
	 *
	 * @var bool
	 */
	protected $not_found = false;

	function get_text() {
		$this->fetch();

		if ( false === $this->data ) {
			return $this->get_url();
		}

		return sprintf( '<%s|#%s: %s>', $this->get_url(), $this->id, htmlspecialchars( $this->summary, ENT_NOQUOTES | ENT_SUBSTITUTE ) );
	}

	function get_short_attachment() {
		$this->fetch();

		if ( false === $this->data ) {
			return false;
		}

		$text = $this->get_text();

		return array(
			'text'      => $text,
			'fallback'  => $text,
			'mrkdwn_in' => array( 'text', 'fallback' ),
		);
	}

	function get_attachment() {
		$attachment = $this->get_short_attachment();

		if ( false === $attachment ) {
			return false;
		}

		unset( $attachment['text'] ); // Moved to title and title_link.

		$attachment['title']      = sprintf( '#%s: %s', $this->id, htmlspecialchars( $this->summary, ENT_NOQUOTES | ENT_SUBSTITUTE ) );
		$attachment['title_link'] = $this->get_url();

		$attachment['fields'] = self::get_ticket_fields( $this->data );

		// Trac labels the column 'created' and names it 'time'.
		$ts = strtotime( $this->created ?: $this->time );
		if ( $ts ) {
			$attachment['ts'] = $ts;
		}

		$attachment['footer']      = sprintf( '<%s|%s>', $this->trac->get_url(), $this->trac->get_name() );
		$attachment['footer_icon'] = sprintf( '%s/chrome/common/trac.ico', $this->trac->get_url() );

		return $attachment;
	}

	function fetch() {
		if ( isset( $this->data ) ) {
			return $this->data;
		}

		if ( ! $this->trac->is_public() || ! $this->trac->has_tickets() ) {
			$this->data = false;
			return;
		}

		/*
		 * Don't reorder the columns: slack-trac-hook.php array_shift()s the parsed row
		 * to drop the id, so whichever comes first is the one discarded.
		 */
		$url = sprintf(
			'%s/query?id=%s&col=id&col=summary&col=owner&col=type&col=cc&col=status&col=priority&col=milestone&col=component&col=version&col=severity&col=resolution&col=time&col=changetime&col=focuses&col=reporter&col=keywords&col=description&format=csv',
			$this->trac->get_url(),
			$this->id
		);

		$context = stream_context_create( array(
			'http' => array(
				'user_agent' => 'WordPress.org Trac:Slack Notifications'
			)
		) );

		$contents = @file_get_contents( $url, false, $context );
		if ( $contents === false ) {
			$this->data = false;
			return;
		}

		// The first line are headers. All additional lines are part of
		// of a single CSV row (there can be \n in content).
		$contents = explode( "\n", $contents, 2 );

		// Trac sends a UTF-8 BOM and CRLF line endings, neither belongs in the keys.
		$header_line = str_replace( "\xEF\xBB\xBF", '', $contents[0] );
		$header_line = strtolower( rtrim( $header_line, "\r\n" ) );

		$headers = str_getcsv( $header_line, ',', '"', '"' );

		// A single field is an error page or a bot check, not a row of column names.
		if ( count( $headers ) < 2 ) {
			$this->data = false;
			return;
		}

		/*
		 * A header-only response matched no row we can see: no such ticket, or one hidden
		 * from anonymous requests. Only say so for a response that really is the CSV, or a
		 * bot check that happens to parse would pass for a missing ticket. Stock columns
		 * only, as custom fields like focuses are defined per Trac.
		 */
		if ( ! isset( $contents[1] ) || '' === trim( $contents[1] ) ) {
			$this->not_found = in_array( 'summary', $headers, true ) && in_array( 'status', $headers, true );
			$this->data      = false;
			return;
		}

		$values = str_getcsv( rtrim( $contents[1], "\r\n" ), ',', '"', '"' );

		if ( count( $headers ) !== count( $values ) ) {
			$this->data = false;
			return;
		}

		$ticket_info = array_combine( $headers, $values );

		$this->data = (object) $ticket_info;
		return $this->data;
	}

	/**
	 * Whether the Trac query came back empty. Fetches the ticket.
	 *
	 * @return bool
	 */
	public function is_not_found() {
		$this->fetch();

		return $this->not_found;
	}

	static function get_ticket_fields( $ticket ) {
		$new = false !== strpos( get_called_class(), 'New_Ticket' );

		$ticket_fields = array();

		if ( isset( $ticket->type ) && ! $new ) {
			$ticket_fields[] = array(
				'title' => 'Type',
				'value' => $ticket->type,
				'short' => true,
			);
		}

		if ( isset( $ticket->status, $ticket->resolution ) && ( ! $new || $ticket->status === 'open' ) ) {
			$ticket_fields[] = array(
				'title' => 'Status',
				'value' => $ticket->status === 'closed' ? $ticket->resolution : 'open',
				'short' => true,
			);
		}

		if ( ! empty( $ticket->component ) ) {
			$ticket_fields[] = array(
				'title' => 'Component' . ( ! empty( $ticket->focuses ) ? ' (Focuses)' : '' ),
				'value' => $ticket->component . ( ! empty( $ticket->focuses ) ? ' (' . $ticket->focuses . ')' : '' ),
				'short' => true,
			);
		}

		if ( ! empty( $ticket->version ) ) {
			$ticket_fields[] = array(
				'title' => 'Version',
				'value' => $ticket->version,
				'short' => true,
			);
		}

		if ( ! empty( $ticket->milestone ) && ( ! $new || $ticket->milestone !== 'Awaiting Review' ) ) {
			$ticket_fields[] = array(
				'title' => 'Milestone',
				'value' => $ticket->milestone,
				'short' => true,
			);
		}

		if ( ! empty( $ticket->severity ) && ! empty( $ticket->priority ) && ! ( $ticket->severity === 'normal' && $ticket->priority === 'normal' ) ) {
			$ticket_fields[] = array(
				'title' => 'Severity / Priority',
				'value' => sprintf( '%s/%s', $ticket->severity, $ticket->priority ),
				'short' => true,
			);
		} elseif ( ( ! empty( $ticket->severity ) && $ticket->severity !== 'normal' ) || ( ! empty( $ticket->priority ) && $ticket->priority !== 'normal' ) ) {
			$ticket_fields[] = array(
				'title' => ! empty( $ticket->severity ) ? 'Severity' : 'Priority',
				'value' => ! empty( $ticket->severity ) ? $ticket->severity : $ticket->priority,
				'short' => true,
			);
		}

		if ( ! empty( $ticket->keywords ) ) {
			$ticket_fields[] = array(
				'title' => 'Keywords',
				'value' => $ticket->keywords,
				// Make keywords 'short' if it's in column 2.
				// Otherwise, ensure it doesn't need to wrap if it's in column 1 at the bottom.
				'short' => (bool) ( count( $ticket_fields ) % 2 === 1 ),
			);
		}

		return $ticket_fields;
	}
}
