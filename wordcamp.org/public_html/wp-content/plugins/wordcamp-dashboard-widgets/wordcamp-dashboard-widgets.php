<?php

/*
 * Plugin Name: WordCamp Dashboard Widgets
 * Description: Communicate non-urgent messages from Central to organizers through Dashboard widgets.
 * Version:     0.1
 * Author:      Ian Dunn
 */

class WordCamp_Dashboard_Widgets {
	protected $need_central_about_info, $needed_pages;

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp_dashboard_setup', array( $this, 'create_dashboard_widgets' ) );
	}

	/**
	 * Create dashboard widgets
	 */
	public function create_dashboard_widgets() {
		if ( $this->show_wordcamp_reminders() ) {
			wp_add_dashboard_widget(
				'wordcamp_reminders',
				esc_html__( 'WordCamp Reminders', 'wordcamporg' ),
				array( $this, 'render_wordcamp_reminders' )
			);
		}

		wp_add_dashboard_widget(
			'new_wordcamporg_tools',
			esc_html__( 'New WordCamp.org Tools', 'wordcamporg' ),
			array( $this, 'render_new_wordcamporg_tools' )
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
			'posts_per_page' => -1,
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

		// Move WordCamp Reminders to the top of the primary column.
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['wordcamp_reminders'] ) ) {
			$reminders_temp = $wp_meta_boxes['dashboard']['normal']['core']['wordcamp_reminders'];
			unset( $wp_meta_boxes['dashboard']['normal']['core']['wordcamp_reminders'] );

			$wp_meta_boxes['dashboard']['normal']['core'] = array_merge(
				array( 'wordcamp_reminders' => $reminders_temp ),
				$wp_meta_boxes['dashboard']['normal']['core']
			);
		}

		// Move WordCamp Organizer Survey to the top of the side column.
		if ( isset( $wp_meta_boxes['dashboard']['normal']['core']['new_wordcamporg_tools'] ) ) {
			$wp_meta_boxes['dashboard']['side']['core'] = array_merge(
				array( 'new_wordcamporg_tools' => $wp_meta_boxes['dashboard']['normal']['core']['new_wordcamporg_tools'] ),
				$wp_meta_boxes['dashboard']['side']['core']
			);

			unset( $wp_meta_boxes['dashboard']['normal']['core']['new_wordcamporg_tools'] );
		}
	}

	/**
	 * Render the content for the WordCamp Reminders dashboard widget
	 */
	public function render_wordcamp_reminders() {
		?>

		<ul class="ul-disc">
			<?php if ( $this->need_central_about_info ) : ?>
				<li>
					<?php
						printf(
							wp_kses(
								__( 'Please send us <a href="%s">the "about" text and banner image</a> for your central.wordcamp.org page.', 'wordcamporg' ),
								array( 'a' => array( 'href' => true ) )
							),
							'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/web-presence/your-page-on-central-wordcamp-org/'
						);
					?>
				</li>
			<?php endif; ?>

			<?php if ( in_array( 'attendees', $this->needed_pages ) ) : ?>
				<li>
					<?php
						printf(
							wp_kses(
								__( 'Tickets are on sale now! Don’t forget to <a href="%s">publish an Attendees page</a>, so everyone can see what amazing people are coming to your WordCamp.', 'wordcamporg' ),
								array( 'a' => array( 'href' => true ) )
							),
							'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/web-presence/using-camptix-event-ticketing-plugin/#attendees-list'
						);
					?>
					</li>
			<?php endif; ?>

			<?php if ( in_array( 'schedule', $this->needed_pages ) ) : ?>
				<li>
					<?php
						printf(
							wp_kses(
								__( 'Tickets sell a lot faster when people can see who&#8217;s speaking at your WordCamp. How about <a href="%s">publishing a schedule</a> today?', 'wordcamporg' ),
								array( 'a' => array( 'href' => true ) )
							),
							'https://make.wordpress.org/community/handbook/wordcamp-organizer/first-steps/web-presence/custom-tools-for-building-wordcamp-content/#schedule'
						);
					?>
				</li>
			<?php endif; ?>
		</ul>

		<?php
	}

	/**
	 * Render the content for the Removing WordCamp.org Pain Points dashboard widget
	 */
	public function render_new_wordcamporg_tools() {
		?>

		<p>
			<?php esc_html_e( 'Here are some of the tools we&#8217;ve recently launched to help you organize:', 'wordcamporg' ); ?>
		</p>

		<ul class="ul-disc">
			<li>
				<a href="https://make.wordpress.org/community/2016/04/26/new-tool-for-creating-personalized-wordcamp-badges/">
					<?php esc_html_e( 'Create personalized attendee badges.', 'wordcamporg' ); ?>
				</a>
			</li>
			<li>
				<a href="https://make.wordpress.org/community/2016/03/01/new-automated-payments-and-invoicing/">
					<?php esc_html_e( 'Invoice sponsors, pay vendors, and get reimbursed.', 'wordcamporg' ); ?>
				</a>
			</li>
			<li>
				<?php
					printf(
						wp_kses(
							__( '<a href="%s">Quickly clone another WordCamp site</a> instead of building yours from scratch.', 'wordcamporg' ),
							array( 'a' => array( 'href' => true ) )
						),
						'https://make.wordpress.org/community/2015/07/09/site-cloner-v1-is-now-available/'
					);
				?>
			</li>
			<li>
				<?php
					printf(
						wp_kses(
							__( '<a href="%s">Edit CSS in your local environment</a> and manage it in a GitHub repository.', 'wordcamporg' ),
							array( 'a' => array( 'href' => true ) )
						),
						'https://make.wordpress.org/community/2015/11/24/remote-css-plugin-launched-on-wordcamp-org/'
					);
				?>
			</li>
		</ul>

		<?php
	}
}

$GLOBALS['WordCamp_Dashboard_Widgets'] = new WordCamp_Dashboard_Widgets();
