<?php

/* 
 * Plugin Name: WordCamp Organizer Nags
 * Description: Shows admin notices to organizers when they haven't completed a required action yet.
 * Version:     0.1
 * Author:      Ian Dunn
 */

class WordCampOrganizerNags {
	protected $notices;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->notices = array( 'updated' => array(), 'error' => array() );

		add_action( 'wp_dashboard_setup', array( $this, 'create_dashboard_widgets' ) );
		add_action( 'admin_notices', array( $this, 'print_admin_notices' ) );
		add_action( 'admin_init',    array( $this, 'central_about_info' ) );
		add_action( 'admin_init',    array( $this, 'published_attendees_schedule_pages' ) );
	}

	/**
	 * Create dashboard widgets
	 */
	public function create_dashboard_widgets() {
		// The survey expires 2015-05-23 at 11:45pm EST
		if ( time() < 1432350000 ) {
			wp_add_dashboard_widget(
				'improving_tools_survey',
				'WordCamp Organizer Survey',
				array( $this, 'improving_tools_survey' )
			);
		}
	}

	/**
	 * Render the content for the WordCamp Organizer Survey dashboard widget
	 */
	public function improving_tools_survey() {
		?>

		<p>We need feedback from WordCamp organizers to drive some decisions about improving WordCamp.org tools.</p>

		<p>
			Please <a href="https://make.wordpress.org/community/2015/05/05/wordcamp-organizer-survey/">take the survey</a>
			so we can learn from your experiences.
		</p>

		<?php
	}

	/**
	 * Prints all of the notices and errors
	 */
	public function print_admin_notices() {
		$this->notices = apply_filters( 'wcon_notices', $this->notices );
		
		foreach( array( 'updated', 'error' ) as $type ) :
			if ( $this->notices[ $type ] ) : ?>
				
				<div class="<?php echo $type; ?>">
					<?php foreach( $this->notices[ $type ] as $nag ) : ?>
						<p><?php echo $nag; ?></p>
					<?php endforeach; ?>
				</div>
				
			<?php endif;
		endforeach;
	}

	/**
	 * Check if the organizers have given us their "About" text and banner image for their central.wordcamp.org page
	 */
	public function central_about_info() {
		$site_url = parse_url( site_url() );
		switch_to_blog( BLOG_ID_CURRENT_SITE );	// central.wordcamp.org
		
		$wordcamp = get_posts( array(
			'post_type'      => 'wordcamp',
			'posts_per_page' => 1,
			'meta_query'     => array(
				array(
					'key'     => 'URL',
					'value'   => $site_url['host'],
					'compare' => 'LIKE',
				)
			)
		) );
		
		if ( isset( $wordcamp[0]->ID ) ) {
			if ( ! has_post_thumbnail( $wordcamp[0]->ID ) || empty( $wordcamp[0]->post_content ) ) {
				$this->notices['updated'][] = 'Please send us the <a href="http://plan.wordcamp.org/first-steps/web-presence/your-page-on-central-wordcamp-org/">"about" text and banner image</a> for your central.wordcamp.org page.</a>';
			}
		}

		restore_current_blog();
	}

	/**
	 * Checks if Attendees and Schedule pages have been published along with the Ticket Registration page
	 */
	public function published_attendees_schedule_pages() {
		$found_registration = $found_attendees = $found_schedule = false;
		$published_pages    = get_posts( array(
			'post_type'      => 'page',
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
			$this->notices['updated'][] = 'Tickets are on sale now! Don’t forget to <a href="http://plan.wordcamp.org/using-camptix/#attendees-list">publish an Attendees page</a>, so everyone can see what amazing people are coming to your WordCamp.';
		}

		if ( $found_registration && ! $found_schedule ) {
			$this->notices['updated'][] = 'Tickets sell a lot faster when people can see who\'s speaking at your WordCamp. How about <a href="http://plan.wordcamp.org/first-steps/web-presence/working-with-speakers-sessions-and-sponsors/#schedule">publishing a schedule</a> today?';
		}
	}
}

$GLOBALS['WordCampOrganizerNags'] = new WordCampOrganizerNags();