<?php

namespace Dotorg\Slack\Trac;

class Ticket extends Resource {
	protected $data;

	function get_text() {
		$this->fetch();

		if ( false === $this->data ) {
			return $this->get_url();
		}

		return sprintf( "<%s|#%s: %s>", $this->get_url(), $this->id, htmlspecialchars( $this->summary, ENT_NOQUOTES ) );
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

		$attachment['title']      = sprintf( '#%s: %s', $this->id, htmlspecialchars( $this->summary, ENT_NOQUOTES ) );
		$attachment['title_link'] = $this->get_url();

		$attachment['fields'] = self::get_ticket_fields( $this->data );
		$attachment['ts']     = strtotime( $this->data->created );

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
		$ticket_info = array_combine(
			str_getcsv( strtolower( $contents[0] ), ',', '"', '"' ),
			str_getcsv( $contents[1], ',', '"', '"' )
		);

		$this->data = (object) $ticket_info;
		return $this->data;
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

		if ( $ticket->keywords ) {
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
