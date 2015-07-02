<?php

/* 
 * Plugin Name: WordCamp Organizer Nags
 * Description: Shows admin notices to organizers when they haven't completed a required action yet.
 * Version:     0.1
 * Author:      Ian Dunn
 */

class WordCampOrganizerNags {
	protected $need_central_about_info, $needed_pages;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup',           array( $this, 'create_dashboard_widgets' ) );
	}

	/**
	 * Create dashboard widgets
	 */
	public function create_dashboard_widgets() {
		if ( $this->show_wordcamp_reminders() ) {
			wp_add_dashboard_widget(
				'wordcamp_reminders',
				'WordCamp Reminders',
				array( $this, 'render_wordcamp_reminders' )
			);
		}

		wp_add_dashboard_widget(
			'removing_pain_points',
			'Removing WordCamp.org Pain Points',
			array( $this, 'render_removing_pain_points' )
		);

		$this->prioritize_wordcamp_widgets();
	}

	/**
	 * Determine if we need to show the WordCamp Reminders widget
	 *
	 * @return bool
	 */
	protected function show_wordcamp_reminders() {
		$this->need_central_about_info  = $this->check_central_about_info();
		$this->needed_pages             = $this->check_needed_pages();

		return $this->need_central_about_info || $this->needed_pages;
	}

	/**
	 * Check if the organizers have given us their "About" text and banner image for their central.wordcamp.org page
	 *
	 * @return bool
	 */
	protected function check_central_about_info() {
		$transient_key = 'wcorg_need_central_info';

		if ( $need_info = get_transient( $transient_key ) ) {
			return 'yes' == $need_info;
		}

		$need_info  = 'yes';
		$wordcamp   = get_wordcamp_post();

		if ( isset( $wordcamp->ID ) ) {
			switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org

			if ( has_post_thumbnail( $wordcamp->ID ) && $wordcamp->post_content ) {
				$need_info = 'no';
			}

			restore_current_blog();
		}

		set_transient( $transient_key, $need_info, HOUR_IN_SECONDS );

		return 'yes' == $need_info;
	}

	/**
	 * Checks if Attendees and Schedule pages have been published along with the Ticket Registration page
	 *
	 * @todo When publishing an attendee or schedule page, delete the transient to trigger a refresh
	 *
	 * @return array
	 */
	protected function check_needed_pages() {
		$transient_key  = 'wcorg_needed_pages';
		$needed_pages   = get_transient( $transient_key );

		if ( false !== $needed_pages ) {
			return $needed_pages;
		}

		$needed_pages       = array();
		$found_registration = $found_attendees = $found_schedule = false;
		$published_pages    = get_posts( array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => -1
		) );

		foreach ( $published_pages as $page ) {
			if ( has_shortcode( $page->post_content, 'camptix' ) ) {
				$found_registration = true;
			}

			if ( has_shortcode( $page->post_content, 'camptix_attendees' ) ) {
				$found_attendees = true;
			}

			if ( has_shortcode( $page->post_content, 'schedule' ) ) {
				$found_schedule = true;
			}

			if ( $found_registration && $found_attendees && $found_schedule ) {
				break;
			}
		}

		if ( $found_registration && ! $found_attendees ) {
			$needed_pages[] = 'attendees';
		}

		if ( $found_registration && ! $found_schedule ) {
			$needed_pages[] = 'schedule';
		}

		set_transient( $transient_key, $needed_pages, HOUR_IN_SECONDS );

		return $needed_pages;
	}

	/**
	 * Make our custom dashboard widgets more visible
	 */
	protected function prioritize_wordcamp_widgets() {
		global $wp_meta_boxes;

		// Move WordCamp Reminders to the top of the primary column
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['wordcamp_reminders'] ) ) {
			$reminders_temp = $wp_meta_boxes['dashboard']['normal']['core']['wordcamp_reminders'];
			unset( $wp_meta_boxes['dashboard']['normal']['core']['wordcamp_reminders'] );

			$wp_meta_boxes['dashboard']['normal']['core'] = array_merge(
				array( 'wordcamp_reminders' => $reminders_temp ),
				$wp_meta_boxes['dashboard']['normal']['core']
			);
		}

		// Move WordCamp Organizer Survey to the top of the side column
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['improving_tools_survey'] ) ) {
			$wp_meta_boxes['dashboard']['side']['core'] = array_merge(
				array( 'improving_tools_survey' => $wp_meta_boxes['dashboard']['normal']['core']['improving_tools_survey'] ),
				$wp_meta_boxes['dashboard']['side']['core']
			);

			unset( $wp_meta_boxes['dashboard']['normal']['core']['improving_tools_survey'] );
		}
	}

	/**
	 * Render the content for the WordCamp Reminders dashboard widget
	 */
	public function render_wordcamp_reminders() {
		?>

		<ul class="ul-disc">
			<?php if ( $this->need_central_about_info ) : ?>
				<li>Please send us <a href="http://plan.wordcamp.org/first-steps/web-presence/your-page-on-central-wordcamp-org/">the "about" text and banner image</a> for your central.wordcamp.org page.</a></li>
			<?php endif; ?>

			<?php if ( in_array( 'attendees', $this->needed_pages ) ) : ?>
				<li>Tickets are on sale now! Don’t forget to <a href="http://plan.wordcamp.org/using-camptix/#attendees-list">publish an Attendees page</a>, so everyone can see what amazing people are coming to your WordCamp.</li>
			<?php endif; ?>

			<?php if ( in_array( 'schedule', $this->needed_pages ) ) : ?>
				<li>Tickets sell a lot faster when people can see who's speaking at your WordCamp. How about <a href="http://plan.wordcamp.org/first-steps/web-presence/working-with-speakers-sessions-and-sponsors/#schedule">publishing a schedule</a> today?</li>
			<?php endif; ?>
		</ul>

		<?php
	}

	/**
	 * Render the content for the Removing WordCamp.org Pain Points dashboard widget
	 */
	public function render_removing_pain_points() {
		?>

		<p>There are several projects underway to eliminate the worst pain points that WordCamp organizers have reported. Check them out if you'd like to get involved!</p>

		<ul class="ul-disc">
			<li><a href="https://make.wordpress.org/community/2015/06/10/wordcamp-org-tools-survey-results/">Quickly clone another WordCamp site</a> instead of building yours from scratch.</li>
			<li><a href="https://make.wordpress.org/community/2015/06/16/editing-wordcamp-css-locally-with-git/">Edit CSS in your local environment</a> and manage it in a Git(Hub) repository.</li>
			<li><a href="https://make.wordpress.org/community/2015/07/02/results-from-the-wordcamp-org-tools-follow-up-survey/">Build a new theme</a> for WordCamp sites.</li>
			<li><a href="https://make.wordpress.org/community/2015/06/10/wordcamp-org-tools-survey-results/">Improve documentation</a>.</li>
		</ul>

		<?php
	}
}

$GLOBALS['WordCampOrganizerNags'] = new WordCampOrganizerNags();
