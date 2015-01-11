<?php

namespace Dotorg\Slack\Trac;

class New_Ticket extends Ticket {
	static protected function get_type( $type ) {
		return strtr( $type, array( 'defect (bug)' => 'bug', 'task (blessed)' => 'task' ) );
	}

	function get_text() {
		$text = parent::get_text();
		if ( false === $this->data ) {
			return sprintf( 'New ticket: *%s*', $text );
		}

		return sprintf( 'New %s opened by %s: *%s*', self::get_type( $this->type ), $this->reporter, $text );
	}

	function get_attachment() {
		$attachment = parent::get_attachment();
		if ( false === $attachment ) {
			return $attachment;
		}

		$attachment['pretext'] = preg_replace( '/: /', ": \n", $attachment['text'], 1 );
		$attachment['mrkdwn_in'][] = 'pretext';
		$attachment['text'] = Trac::format_for_slack( $this->description );
		$attachment['color'] = $this->trac->get_color();
		return $attachment;
	}

	static function get_triaging_notes( $ticket ) {
		$attachments = array();

		if ( $ticket->component === 'General' ) {
			$attachments[] = array( 'This ticket is filed in the *General* component. Can you help triage it?', '#ffba00' );
		}

		if ( in_array( $ticket->severity, array( 'major', 'critical', 'blocker' ) ) ) {
			$severity = $ticket->severity === 'blocker' ? $ticket->severity = 'a blocker' : $ticket->severity;
			$attachments[] = array( sprintf( "%s marked this ticket as *%s*. Could you take a look?", $ticket->reporter, $severity ), '#dd3d36' );
		}

		if ( false !== strpos( $ticket->keywords, 'has-patch' ) ) {
			$attachments[] = array( sprintf( "%s uploaded a *patch*! Could you review it?", $ticket->reporter ), '#7ad03a' );
		}

		foreach ( $attachments as &$attachment ) {
			$attachment = array(
				'text'      => $attachment[0],
				'fallback'  => $attachment[0],
				'color'     => $attachment[1],
				'mrkdwn_in' => array( 'text', 'fallback' ),
			);
		}

		return $attachments;
	}	
}
