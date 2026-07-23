<?php

namespace Wporg\TranslationEvents\Event;

use Wporg\TranslationEvents\Translation_Events;

class Event_End_Date extends Event_Date {
	public function get_prefixed_date( $date ): string {
		if ( $this->is_in_the_past() ) {
			// translators: %s: A date.
			return sprintf( __( 'ended %s', 'gp-translation-events' ), '<span>' . $date . '</span>' );
		}
		// translators: %s: A date.
		return sprintf( __( 'until %s', 'gp-translation-events' ), '<span>' . $date . '</span>' );
	}

	public function get_variable_text(): string {
		if ( $this->is_in_the_past() ) {
			return $this->get_prefixed_date( $this->format( 'D, F j, Y H:i T' ) );
		}

		$interval       = $this->diff( Translation_Events::now() );
		$hours_left     = ( $interval->d * 24 ) + $interval->h;
		$hours_in_a_day = 24;

		if ( 0 === $hours_left ) {
			if ( ! $interval->i ) {
				return __( 'ends in less than a minute', 'gp-translation-events' );
			}
			/* translators: %s: Number of minutes left. */
			return sprintf( _n( 'ends in %s minute', 'ends in %s minutes', $interval->i, 'gp-translation-events' ), $interval->i );
		}

		if ( $hours_left <= $hours_in_a_day ) {
			/* translators: %s: Number of hours left. */
			$out = sprintf( _n( 'ends in %s hour', 'ends in %s hours', $hours_left, 'gp-translation-events' ), $hours_left );
			if ( $interval->i ) {
				/* translators: %s: Number of minutes left. */
				$out .= sprintf( _n( ' and %s minute', ' and %s minutes', $interval->i, 'gp-translation-events' ), $interval->i );
			}
			return $out;
		}

			return $this->get_prefixed_date( $this->format( 'D, F j, Y H:i T' ) );
	}
}
