<?php

namespace Wporg\TranslationEvents\Routes\Event;

use DateTimeImmutable;
use DateTimeInterface;
use WP_Post;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\Event_Repository;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;
use Wporg\TranslationEvents\Urls;

/**
 * Displays the RSS page.
 */
class Rss_Route extends Route {
	private Event_Repository $event_repository;

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
		$args                = array(
			'posts_per_page'      => 20,
			'post_type'           => Translation_Events::CPT,
			'post_status'         => 'publish',
			'post_parent__not_in' => array( 0 ),
		);
		$last_20_events_post = get_posts( $args );

		$this->send_headers( $this->document_pub_and_build_date( $last_20_events_post, 'Y-m-d H:i:s' ) );
		$rss_feed = $this->get_rss_20_header( $last_20_events_post );
		foreach ( $last_20_events_post as $event_post ) {
			$event = $this->event_repository->get_event( $event_post->ID );
			if ( $event ) {
				$rss_feed .= $this->get_item( $event );
			}
		}
		$rss_feed .= $this->get_rss_20_footer();

		header( 'Content-Type: application/xml; charset=UTF-8' );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $rss_feed;
		exit();
	}

	/**
	 * Sends headers, Based on send_headers() in the WP Class.
	 *
	 * @param string $post_modified_gmt   The post modified GMT date.
	 */
	private function send_headers( string $post_modified_gmt ) {
		$headers       = array();
		$status        = null;
		$exit_required = false;
		$date_format   = 'D, d M Y H:i:s';

		$wp_last_modified = mysql2date( $date_format, $post_modified_gmt, false ) . ' GMT';
		$wp_etag          = '"' . md5( $wp_last_modified ) . '"';

		$headers['Last-Modified'] = $wp_last_modified;
		$headers['ETag']          = $wp_etag;

		// Support for conditional GET.
		if ( isset( $_SERVER['HTTP_IF_NONE_MATCH'] ) ) {
			$client_etag = wp_unslash( $_SERVER['HTTP_IF_NONE_MATCH'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			// Remove the "W/" if the client has sent it (weak validation).
			if ( 0 === strpos( $client_etag, 'W/' ) ) {
				$client_etag = substr( $client_etag, 2 );
			}
		} else {
			$client_etag = '';
		}

		if ( isset( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ) {
			$client_last_modified = trim( wp_unslash( $_SERVER['HTTP_IF_MODIFIED_SINCE'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			$client_last_modified = '';
		}

		// If string is empty, return 0. If not, attempt to parse into a timestamp.
		$client_modified_timestamp = $client_last_modified ? strtotime( $client_last_modified ) : 0;

		// Make a timestamp for our most recent modification.
		$wp_modified_timestamp = strtotime( $wp_last_modified );

		if ( ( $client_last_modified && $client_etag )
			? ( ( $client_modified_timestamp >= $wp_modified_timestamp ) && ( $client_etag === $wp_etag ) )
			: ( ( $client_modified_timestamp >= $wp_modified_timestamp ) || ( $client_etag === $wp_etag ) )
		) {
			$status        = 304;
			$exit_required = true;
		}

		if ( ! empty( $status ) ) {
			status_header( $status );
		}

		// If Last-Modified is set to false, it should not be sent (no-cache situation).
		if ( isset( $headers['Last-Modified'] ) && false === $headers['Last-Modified'] ) {
			unset( $headers['Last-Modified'] );

			if ( ! headers_sent() ) {
				header_remove( 'Last-Modified' );
			}
		}

		if ( ! headers_sent() ) {
			foreach ( (array) $headers as $name => $field_value ) {
				header( "{$name}: {$field_value}" );
			}
		}

		if ( $exit_required ) {
			exit;
		}
	}

	/**
	 * Get the RSS 2.0 header.
	 *
	 * @param WP_Post[] $events Array of last events, as WP_Post objects.
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

	/**
	 * Get a RSS 2.0 item from an event.
	 *
	 * @param Event $event The event.
	 *
	 * @return string The item.
	 */
	private function get_item( Event $event ): string {
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
	 * If there are no events at all, returns the date of the Unix epoch.
	 *
	 * @param WP_Post[] $events_post Array of last events, as WP_Post objects.
	 * @param string    $format      Date format.
	 *
	 * @return string|null
	 */
	private function document_pub_and_build_date( array $events_post, string $format = DATE_RSS ): ?string {
		// Default to the Unix epoch.
		$pub_date = new DateTimeImmutable( '@0' );
		if ( empty( $events_post ) ) {
			return $pub_date->format( DATE_RSS );
		}
		$first_event = $this->event_repository->get_event( $events_post[0]->ID );
		if ( $first_event ) {
			$pub_date = $first_event->updated_at();
		}
		foreach ( $events_post as $event_post ) {
			$event = $this->event_repository->get_event( $event_post->ID );
			if ( $event && ( $event->updated_at() > $pub_date ) ) {
				$pub_date = $event->updated_at();
			}
		}

		return $pub_date->format( $format );
	}
}
