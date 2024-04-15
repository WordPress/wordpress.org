<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event_Repository_Interface;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Stats_Calculator;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event edit page.
 */
class Edit_Route extends Route {
	private Event_Repository_Interface $event_repository;
	private Attendee_Repository $attendee_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository    = Translation_Events::get_event_repository();
		$this->attendee_repository = Translation_Events::get_attendee_repository();
	}

	public function handle( int $event_id ): void {
		global $wp;
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( home_url( $wp->request ) ) );
			exit;
		}
		$event    = $this->event_repository->get_event( $event_id );
		$attendee = $this->attendee_repository->get_attendee( $event->id(), get_current_user_id() );

		if ( ! $event || ! ( ( $attendee instanceof Attendee && $attendee->is_host() ) || current_user_can( 'edit_post', $event->id() ) || $event->author_id() === get_current_user_id() ) ) {
			$this->die_with_error( esc_html__( 'Event does not exist, or you do not have permission to edit it.', 'gp-translation-events' ), 403 );
		}
		if ( 'trash' === $event->status() ) {
			$this->die_with_error( esc_html__( 'You cannot edit a trashed event', 'gp-translation-events' ), 403 );
		}

		include ABSPATH . 'wp-admin/includes/post.php';
		$event_page_title              = 'Edit Event';
		$event_form_name               = 'edit_event';
		$css_show_url                  = '';
		$event_title                   = $event->title();
		$event_description             = $event->description();
		$event_status                  = $event->status();
		list( $permalink, $post_name ) = get_sample_permalink( $event->id() );
		$permalink                     = str_replace( '%pagename%', $post_name, $permalink );
		$event_url                     = get_site_url() . gp_url( wp_make_link_relative( $permalink ) );
		$event_timezone                = $event->timezone();
		$event_start                   = $event->start();
		$event_end                     = $event->end();
		$create_delete_button          = false;
		$visibility_delete_button      = 'inline-flex';

		if ( $event->end()->is_in_the_past() ) {
			$this->die_with_error( esc_html__( 'You cannot edit a past event.', 'gp-translation-events' ), 403 );
		}

		$stats_calculator = new Stats_Calculator();

		if ( $stats_calculator->event_has_stats( $event->id() ) ) {
			$this->die_with_error( esc_html__( 'You cannot edit an event with translations.', 'gp-translation-events' ), 403 );
		}

		if ( ! $stats_calculator->event_has_stats( $event->id() ) ) {
			$current_user = wp_get_current_user();
			if ( ( $current_user->ID === $event->author_id() || ( $attendee instanceof Attendee && $attendee->is_host() ) || current_user_can( 'manage_options' ) ) && ! $event->end()->is_in_the_past() ) {
				$create_delete_button = true;
			}
		}

		$this->tmpl( 'events-form', get_defined_vars() );
	}
}
