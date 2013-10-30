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
		
		add_action( 'admin_notices', array( $this, 'print_admin_notices' ) );
		add_action( 'admin_init',    array( $this, 'central_about_info' ) );
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
}

$GLOBALS['WordCampOrganizerNags'] = new WordCampOrganizerNags();