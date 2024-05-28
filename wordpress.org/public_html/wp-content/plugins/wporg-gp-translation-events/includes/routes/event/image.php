<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the image for the event.
 */
class Image_Route extends Route {

	/**
	 * @var Event_Repository_Interface
	 */
	private Event_Repository_Interface $event_repository;

	/**
	 * Image_Route constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	/**
	 * Handles the request.
	 *
	 * Generates an image with the event title.
	 *
	 * @param int $event_id The event ID.
	 */
	public function handle( int $event_id ): void {
		if ( ! extension_loaded( 'gd' ) ) {
			$this->die_with_error( esc_html__( 'The image cannot be generated because GD extension is not installed.', 'gp-translation-events' ) );
		}

		$event = $this->event_repository->get_event( $event_id );
		$text  = ! $event ? esc_html__( 'Translation events', 'gp-translation-events' ) : $event->title();
		$text  = '' === $text ? esc_html__( 'Translation events', 'gp-translation-events' ) : $text;
		$text  = substr( $text, 0, 44 ); // Limit the text to 44 characters.

		$lines = $this->split_text( $text, 22 ); // Limit each line to 22 characters.
		$text1 = $lines[0];
		$text2 = $lines[1] ?? '';

		$image    = imagecreatetruecolor( 1200, 675 );
		$bg_color = imagecolorallocate( $image, 35, 40, 45 );
		imagefill( $image, 0, 0, $bg_color );
		$text_color = imagecolorallocate( $image, 255, 255, 255 );
		$font       = trailingslashit( dirname( __DIR__, 3 ) ) . 'assets/fonts/eb-garamond/EBGaramond-Regular.ttf';
		$text_size  = 70;
		$text_angle = 0;

		$text_box1   = imagettfbbox( $text_size, $text_angle, $font, $text1 );
		$text_width1 = $text_box1[4] - $text_box1[0];
		$text_x1     = ( 1200 - $text_width1 ) / 2;
		$text_y1     = 350;
		if ( '' !== $text2 ) {
			$text_y1 -= 50;
		}

		if ( '' !== $text2 ) {
			$text_box2   = imagettfbbox( $text_size, $text_angle, $font, $text2 );
			$text_width2 = $text_box2[4] - $text_box2[0];
			$text_x2     = ( 1200 - $text_width2 ) / 2;
			$text_y2     = $text_y1 + 110;
			imagettftext( $image, $text_size, $text_angle, $text_x2, $text_y2, $text_color, $font, $text2 );
		}

		imagettftext( $image, $text_size, $text_angle, $text_x1, $text_y1, $text_color, $font, $text1 );

		header( 'Content-type: image/png' );
		imagepng( $image );
		imagedestroy( $image );
	}

	/**
	 * Splits a string into two lines based on the maximum line length.
	 *
	 * @param string $text       The text to split.
	 * @param int    $max_length The maximum length of each line.
	 *
	 * @return string[]
	 */
	private function split_text( string $text, int $max_length ): array {
		if ( strlen( $text ) <= $max_length ) {
			return array( $text );
		}

		$words = explode( ' ', $text );

		$line1 = '';
		$line2 = '';

		foreach ( $words as $word ) {
			if ( strlen( $line1 . ' ' . $word ) <= $max_length ) {
				$line1 .= ( '' === $line1 ? '' : ' ' ) . $word;
			} else {
				$line2 .= ( '' === $line2 ? '' : ' ' ) . $word;
			}
		}

		return array( $line1, $line2 );
	}
}
