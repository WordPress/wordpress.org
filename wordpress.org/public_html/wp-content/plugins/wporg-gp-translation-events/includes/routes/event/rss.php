<?php

namespace Wporg\TranslationEvents\Routes\Event;

use DateTimeInterface;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

/**
 * Displays the RSS page.
 */
class Rss_Route extends Route {
	private Event_Repository_Interface $event_repository;

	/**
	 * Rss_Route constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	/**
	 * Handle the request.
	 *
	 * @return void
	 */
	public function handle(): void {
		$current_events_query = $this->event_repository->get_current_and_upcoming_events( 1, 20 );
		$rss_feed             = $this->get_rss_20_header( $current_events_query->events );

		foreach ( $current_events_query->events as $event ) {
			$rss_feed .= $this->get_item( $event );
		}
		$rss_feed .= $this->get_rss_20_footer();

		header( 'Content-Type: application/xml; charset=UTF-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $rss_feed;
		exit();
	}

	/**
	 * Get the RSS 2.0 header.
	 *
	 * @return string
	 */
	private function get_rss_20_header( array $events ): string {
		$header  = '<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:ev="http://purl.org/rss/1.0/modules/event/">';
		$header .= '    <channel>';
		$header .= '        <title>' . esc_html__( 'WordPress.org Global Translation Events', 'gp-translation-events' ) . '</title>';
		$header .= '        <link>' . esc_url( home_url( gp_url( '/events' ) ) ) . '</link>';
		$header .= '        <description>' . esc_html__( 'WordPress.org Global Translation Events', 'gp-translation-events' ) . '</description>';
		$header .= '        <language>en-us</language>';
		$header .= '        <pubDate>' . esc_html( $this->document_pub_and_build_date( $events ) ) . '</pubDate>';
		$header .= '        <lastBuildDate>' . esc_html( $this->document_pub_and_build_date( $events ) ) . '</lastBuildDate>';
		$header .= '        <docs>https://www.rssboard.org/rss-specification</docs>';
		$header .= '        <generator>' . esc_html__( 'Translation Events', 'gp-translation-events' ) . '</generator>';
		$header .= '        <atom:link href="' . esc_url( home_url( gp_url( '/events/rss' ) ) ) . '" rel="self" type="application/rss+xml"/>';
		return $header;
	}

	/**
	 * Get the RSS 2.0 footer.
	 *
	 * @return string
	 */
	private function get_rss_20_footer(): string {
		$footer  = '    </channel>';
		$footer .= '</rss>';
		return $footer;
	}

	private function get_item( Event $event ) {
		$item  = '      <item>';
		$item .= '          <title>' . esc_html( $event->title() ) . '</title>';
		$item .= '          <link>' . esc_url( home_url( gp_url( gp_url_join( 'events', $event->slug() ) ) ) ) . '</link>';
		$item .= '          <description>' . esc_html( $event->description() ) . '</description>';
		$item .= '          <enclosure url="' . esc_url( Urls::event_image( $event->id() ) ) . '" type="image/png" length="1200" />';
		$item .= '          <pubDate>' . esc_html( $event->updated_at()->format( DATE_RSS ) ) . '</pubDate>';
		$item .= '          <ev:startdate>' . esc_html( $event->start()->format( DateTimeInterface::ATOM ) ) . '</ev:startdate>';
		$item .= '          <ev:enddate>' . esc_html( $event->end()->format( DateTimeInterface::ATOM ) ) . '</ev:enddate>';
		$item .= '          <guid>' . esc_url( home_url( gp_url( gp_url_join( 'events', $event->slug() ) ) ) ) . '</guid>';
		$item .= '      </item>';
		return $item;
	}

	/**
	 * Get the most recent event's pub date.
	 *
	 * @param Event[] $events Array of events to use for the pub date.
	 *
	 * @return string|null
	 */
	private function document_pub_and_build_date( array $events ): ?string {
		if ( empty( $events ) ) {
			return null;
		}

		$pub_date = $events[0]->updated_at();
		foreach ( $events as $event ) {
			if ( $event->updated_at() > $pub_date ) {
				$pub_date = $event->updated_at();
			}
		}

		return $pub_date->format( DATE_RSS );
	}
}
