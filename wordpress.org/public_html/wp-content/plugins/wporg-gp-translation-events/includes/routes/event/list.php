<?php

namespace Wporg\TranslationEvents\Routes\Event;

use Wporg\TranslationEvents\Event\Event_Repository;
use Wporg\TranslationEvents\Routes\Route;
use Wporg\TranslationEvents\Translation_Events;

/**
 * Displays the event list page.
 */
class List_Route extends Route {
	private Event_Repository $event_repository;

	public function __construct() {
		parent::__construct();
		$this->event_repository = Translation_Events::get_event_repository();
	}

	public function handle(): void {
		$_current_events_paged        = 1;
		$_upcoming_events_paged       = 1;
		$_past_events_paged           = 1;
		$_user_attending_events_paged = 1;
		$filter_key                   = '';

		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['current_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['current_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_current_events_paged = (int) $value;
				$filter_key            = 'current_events_query';
			}
		}
		if ( isset( $_GET['upcoming_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['upcoming_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_upcoming_events_paged = (int) $value;
				$filter_key             = 'upcoming_events_query';
			}
		}
		if ( isset( $_GET['past_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['past_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_past_events_paged = (int) $value;
				$filter_key         = 'past_events_query';
			}
		}
		if ( isset( $_GET['user_attending_events_paged'] ) ) {
			$value = sanitize_text_field( wp_unslash( $_GET['user_attending_events_paged'] ) );
			if ( is_numeric( $value ) ) {
				$_user_attending_events_paged = (int) $value;
				$filter_key                   = 'user_attending_events_query';
			}
		}
		// phpcs:enable
		$tmpl_args = array(
			'current_events_query'        => $this->event_repository->get_current_events( $_current_events_paged, 10 ),
			'upcoming_events_query'       => $this->event_repository->get_upcoming_events( $_upcoming_events_paged, 10 ),
			'past_events_query'           => $this->event_repository->get_past_events( $_past_events_paged, 10 ),
			'user_attending_events_query' => $this->event_repository->get_current_and_upcoming_events_for_user( get_current_user_id(), $_user_attending_events_paged, 10 ),
		);

		$this->use_theme();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['format'] ) ) {

			$format = sanitize_text_field( wp_unslash( $_GET['format'] ) );

			if ( 'html' !== $format || empty( $filter_key ) ) {
				return;
			}

			if ( ! empty( $tmpl_args[ $filter_key ]->event_ids ) ) {
				$this->handle_ajax( $filter_key, $tmpl_args );
			}
		}

		$this->tmpl(
			'home',
			$tmpl_args,
		);
	}

	public function handle_ajax( $filter_key, $tmpl_args ) {
		$event_ids    = $tmpl_args[ $filter_key ]->event_ids;
		$current_page = $tmpl_args[ $filter_key ]->current_page;
		$page_count   = $tmpl_args[ $filter_key ]->page_count;
		$next_page    = ( ( $current_page + 1 ) <= $page_count ) ? $current_page + 1 : 0;

		$list_block_markup = '<!-- wp:wporg-translate-events-2024/event-list ' . wp_json_encode(
			array(
				'event_ids' => $event_ids,
				'next_page' => $next_page,
				'filter_by' => $filter_key,
			)
		) . ' /-->';

		$rendered_html = '';
		$parsed_blocks = parse_blocks( do_blocks( $list_block_markup ) );
		foreach ( $parsed_blocks as $block ) {
			$rendered_html .= render_block( $block );
		}

		wp_send_json_success(
			array(
				'nextPage' => $next_page,
				'html'     => $rendered_html,
			)
		);
	}
}
