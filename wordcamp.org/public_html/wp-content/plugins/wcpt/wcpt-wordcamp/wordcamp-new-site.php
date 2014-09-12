<?php

class WordCamp_New_Site {
	protected $new_site_id;

	/*
	 * Constructor
	 */
	public function __construct() {
		$this->new_site_id = false;
		
		add_action( 'wcpt_metabox_value', array( $this, 'render_site_url_field' ), 10, 3 );
		add_action( 'wcpt_metabox_save',  array( $this, 'save_site_url_field' ), 10, 3 );
	}

	/**
	 * Render the URL field
	 *
	 * @action wcpt_metabox_value
	 *
	 * @param $key
	 * @param $field_type
	 * @param $object_name
	 */
	public function render_site_url_field( $key, $field_type, $object_name ) {
		global $post_id;

		if ( 'URL' == $key && 'wc-url' == $field_type ) : ?>

			<input type="text" size="36" name="<?php echo esc_attr( $object_name ); ?>" id="<?php echo esc_attr( $object_name ); ?>" value="<?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?>" />

			<?php if ( current_user_can( 'manage_sites' ) ) : ?>
				<?php $url = parse_url( trailingslashit( get_post_meta( $post_id, $key, true ) ) ); ?>
				<?php if ( isset( $url['host'], $url['path'] ) && domain_exists( $url['host'], $url['path'], 1 ) ) : ?>
					<?php $blog_details = get_blog_details( array( 'domain' => $url['host'], 'path' => $url['path'] ), true ); ?>

					<a target="_blank" href="<?php echo add_query_arg( 's', $blog_details->blog_id, network_admin_url( 'sites.php' ) ); ?>">Edit</a> |
					<a target="_blank" href="<?php echo $blog_details->siteurl; ?>/wp-admin/">Dashboard</a> |
					<a target="_blank" href="<?php echo $blog_details->siteurl; ?>">Visit</a>

				<?php else : ?>
					<?php $checkbox_id = wcpt_key_to_str( 'create-site-in-network', 'wcpt_' ); ?>

					<label for="<?php echo esc_attr( $checkbox_id ); ?>">
						<input id="<?php echo esc_attr( $checkbox_id ); ?>" type="checkbox" name="<?php echo esc_attr( $checkbox_id ); ?>" />
						Create site in network
					</label>

					<span class="description">(e.g., http://<?php echo esc_html( date( 'Y' ) ); ?>.city.wordcamp.org)</span>
				<?php endif; // domain_exists ?>
			<?php endif; // current_user_can ?>

		<?php endif;
	}

	/**
	 * Save the URL field value
	 *
	 * @param $key
	 * @param $field_type
	 * @param $wordcamp_id
	 */
	public function save_site_url_field( $key, $field_type, $wordcamp_id ) {
		global $switched;

		// No updating if the blog has been switched
		if ( $switched ) {
			return;
		}

		$field_name = wcpt_key_to_str( $key, 'wcpt_' );

		if ( 'URL' == $key && 'wc-url' == $field_type && isset( $_POST[ $field_name ] ) ) {
			$url = strtolower( substr( $_POST[ $field_name ], 0, 4 ) ) == 'http' ? $_POST[ $field_name ] : 'http://' . $_POST[ $field_name ];
			update_post_meta( $wordcamp_id, $key, esc_url( $url ) );

			if ( isset( $_POST[ wcpt_key_to_str( 'create-site-in-network', 'wcpt_' ) ] ) && ! empty( $url ) ) {
				$this->create_new_site( $wordcamp_id, $url );
			}
		}
	}


	/**
	 * Create a new site in the network
	 *
	 * @param int    $wordcamp_id
	 * @param string $url
	 */
	protected function create_new_site( $wordcamp_id, $url ) {
		if ( ! current_user_can( 'manage_sites' ) ) {
			return;
		}

		$url = parse_url( $url );
		if ( ! $url || empty( $url['scheme'] ) || empty( $url['host'] ) ) {
			return;
		}
		$path = isset( $url['path'] ) ? $url['path'] : '';

		$this->new_site_id = create_empty_blog( $url['host'], $path, 'WordCamp Event', 1 );
		if ( is_int( $this->new_site_id ) ) {
			update_post_meta( $wordcamp_id, '_site_id', $this->new_site_id );    // this is used in other plugins to map the `wordcamp` post to it's corresponding site
			do_action( 'wcor_wordcamp_site_created', $wordcamp_id );

			// Configure the new site at priority 11, after all the custom fields on the `wordcamp` post have been saved, so that we don't use outdated values
			add_action( 'save_post', array( $this, 'configure_new_site' ), 11, 2 );
		}
	}

	/**
	 * Configure a new site and populate it with default content
	 *
	 * @todo Can probably just network-activate plugins instead, but need to test that they work fine in network-activated mode.
	 *
	 * @action save_post
	 *
	 * @param int     $wordcamp_id
	 * @param WP_Post $wordcamp
	 */
	public function configure_new_site( $wordcamp_id, $wordcamp ) {
		if ( ! defined( 'WCPT_POST_TYPE_ID' ) || WCPT_POST_TYPE_ID != $wordcamp->post_type || ! is_numeric( $this->new_site_id ) ) {
			return;
		}

		$meta = get_post_custom( $wordcamp_id );

		switch_to_blog( $this->new_site_id );

		$lead_organizer = get_user_by( 'login', $meta['WordPress.org Username'][0] );
		if ( ! $lead_organizer ) {
			$lead_organizer = wp_get_current_user();
		}
		add_user_to_blog( get_current_blog_id(), $lead_organizer->ID, 'administrator' );

		activate_plugins( array(
			'camptix/camptix.php',
			'wc-fonts/wc-fonts.php'
		) ); // Note: this may not be safe to do with every plugin, especially if it has activation callbacks. Make sure you test any new ones that are added.

		switch_theme( 'twentythirteen' );

		$this->set_default_options( $wordcamp, $meta );
		$this->create_post_stubs( $wordcamp, $meta, $lead_organizer );

		restore_current_blog();
	}

	/**
	 * Set the default options
	 *
	 * @param WP_Post $wordcamp
	 * @param array $meta
	 */
	protected function set_default_options( $wordcamp, $meta ) {
		/** @var $WCCSP_Settings WCCSP_Settings */
		global $WCCSP_Settings;

		$admin_email                     = is_email( $meta['E-mail Address'][0] ) ? $meta['E-mail Address'][0] : get_site_option( 'admin_email' );
		$coming_soon_settings            = $WCCSP_Settings->get_settings();
		$coming_soon_settings['enabled'] = 'on';

		$blog_name = apply_filters( 'the_title', $wordcamp->post_title );
		if ( isset( $meta['Start Date (YYYY-mm-dd)'] ) && $meta['Start Date (YYYY-mm-dd)'][0] > 0 ) {
			$blog_name .= date( ' Y', $meta['Start Date (YYYY-mm-dd)'][0] );
		}

		update_option( 'admin_email',                  $admin_email );
		update_option( 'blogname',                     $blog_name );
		update_option( 'blogdescription',              __( 'Just another WordCamp', 'wordcamporg' ) );
		update_option( 'close_comments_for_old_posts', 1 );
		update_option( 'close_comments_days_old',      30 );
		update_option( 'wccsp_settings',               $coming_soon_settings );
	}

	/**
	 * Create stubs for commonly-used posts
	 *
	 * @todo Create sponsorship levels based on new mes-sponsor-level taxonomy instead of hardcoding
	 *
	 * @param WP_Post $wordcamp
	 * @param array   $meta
	 * @param WP_User $lead_organizer
	 */
	protected function create_post_stubs( $wordcamp, $meta, $lead_organizer ) {
		// Get stub content
		$stubs = array_merge(
			$this->get_stub_posts( $wordcamp, $meta ),
			$this->get_stub_pages( $wordcamp, $meta ),
			$this->get_stub_me_sponsors( $wordcamp, $meta ),
			$this->get_stub_me_sponsor_thank_yous( $wordcamp, $meta )
		);

		// Create sponsorship levels
		$sponsor_level_ids['champion']   = wp_create_term( 'WordCamp Champion',   'wcb_sponsor_level' );
		$sponsor_level_ids['accomplice'] = wp_create_term( 'WordCamp Accomplice', 'wcb_sponsor_level' );
		$sponsor_level_ids['pillar']     = wp_create_term( 'WordCamp Pillar',     'wcb_sponsor_level' );

		// Create actual posts from stubs
		remove_action( 'save_post', array( $GLOBALS['wordcamp_admin'], 'metabox_save' ) ); // prevent this callback from adding all the meta fields from the corresponding wordcamp post to new posts we create

		foreach ( $stubs as $page ) {
			$page_id = wp_insert_post( array(
				'post_type'    => $page['type'],
				'post_status'  => $page['status'],
				'post_author'  => $lead_organizer->ID,
				'post_title'   => $page['title'],
				'post_content' => $page['content']
			) );

			if ( $page_id ) {
				// Set featured image
				if ( isset( $page['featured_image'] ) ) {
					$results = media_sideload_image( $page['featured_image'], $page_id );

					if ( ! is_wp_error( $results ) ) {
						$attachment_id = get_posts( array(
							'posts_per_page' => 1,
							'post_type'      => 'attachment',
							'post_parent'    => $page_id
						) );

						if ( isset( $attachment_id[0]->ID ) ) {
							set_post_thumbnail( $page_id, $attachment_id[0]->ID );
						}
					}
				}

				// Assign sponsorship level
				if ( isset( $page['term'] ) ) {
					wp_set_post_terms( $page_id, $sponsor_level_ids[ $page['term'] ], 'wcb_sponsor_level', true );
				}
			}
		}

		add_action( 'save_post', array( $GLOBALS['wordcamp_admin'], 'metabox_save' ) ); // restore wordcamp meta callback
	}

	/**
	 * Get the content for page stubs
	 *
	 * @param WP_Post $wordcamp
	 * @param array   $meta
	 *
	 * @return array
	 */
	protected function get_stub_pages( $wordcamp, $meta ) {
		$pages = array(
			array(
				'title'   => __( 'Schedule', 'wordcamporg' ),
				'content' =>
					'<p>'  . __( '<em>Organizers note:</em> You can enter content for this page in the Sessions menu item in the sidebar.', 'wordcamporg' ) . '</p> ' .
					'<h1>' . __( 'Saturday, January 1st', 'wordcamporg' ) . '</h1> ' .
					'<p>[schedule date="YYYY-MM-DD" tracks="example-track,another-example-track,yet-another-example-track"]</p>',
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Speakers', 'wordcamporg' ),
				'content' =>
					'<p>' . __( '<em>Organizers note:</em> You can enter content for this page in the Speakers menu item in the sidebar.', 'wordcamporg' ) . '</p> ' .
					'<p>[speakers]</p>',
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Sessions', 'wordcamporg' ),
				'content' =>
					'<p>' . __( '<em>Organizers note:</em> You can enter content for this page in the Sessions menu item in the sidebar.', 'wordcamporg' ) . '</p> ' .
					'<p>[sessions]</p>',
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Sponsors', 'wordcamporg' ),
				'content' =>
					'<p>'  . __( "<em>Organizers note:</em> Multi-event sponsors have been automatically created in the Sponsors menu, but you'll need to remove the ones that don't apply to your specific event. To find out which ones apply, please visit http://central.wordcamp.org/multi-event-sponsorship-packages/. After that, you should add the sponsors that are specific to your event. For non-English sites, make sure the URL below matches Call for Sponsors page.", 'wordcamporg' ) . '</p> ' .
					'<h3>' . __( 'Our Sponsors', 'wordcamporg' ) . '</h3> ' .
					'<p>'  . __( 'Blurb thanking sponsors', 'wordcamporg' ) . '</p> ' .
					'<p>[sponsors]</p> ' .
					'<h3>' . __( 'Interested in sponsoring WordCamp this year?</h3>', 'wordcamporg' ) .
					'<p>'  . __( 'Check out our <a href="/call-for-sponsors">Call for Sponsors</a> post for details on how you can help make this year\'s WordCamp the best it can be!</p>', 'wordcamporg' ),
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Location', 'wordcamporg' ),
				'content' => '',
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Organizers', 'wordcamporg' ),
				'content' =>
					'<p>' . __( '<em>Organizers note:</em> You can enter content for this page in the Organizers menu item in the sidebar.', 'wordcamporg' ) . '</p> ' .
					'<p>[organizers]</p>',
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Tickets', 'wordcamporg' ),
				'content' =>
					'<p>' . __( "<em>Organizers note:</em> If you'd like to change the slug for this page, please make sure you do that before opening ticket sales. Changing the page slug after tickets have started selling will break the link that users receive in their receipt e-mail.", 'wordcamporg' ) . '</p> ' .
					'<p>[camptix]</p>',
				'status'  => 'draft',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Attendees', 'wordcamporg' ),
				'content' => '[camptix_attendees columns="3"]',
				'status'  => 'draft',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Videos', 'wordcamporg' ),
				'content' =>
					'<p>' . __( '<em>Organizers note:</em> After your WordCamp is over and the sessions are published to WordPress.tv, you can embed them here. Just enter the event slug into the shortcode below, and hit the <em>Publish</em> button.', 'wordcamporg' ) . '</p> ' .
					 '<p>[wptv event="enter-event-slug-here"]</p>',
				'status'  => 'draft',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Slideshow', 'wordcamporg' ),
				'content' =>
					'<p>' . __( "<em>Organizers note:</em> Upload photos to this page and they'll automagically appear in a slideshow!", 'wordcamporg' ) . '</p> ' .
				    '<p>[slideshow]</p>',
				'status'  => 'draft',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Contact', 'wordcamporg' ),
				'content' => sprintf(
					'<p>' .
						'[contact-form to="%s" subject="%s"]' .
							'[contact-field label="%s" type="name"     required="1" /]' .
							'[contact-field label="%s" type="email"    required="1" /]' .
							'[contact-field label="%s" type="textarea" required="1" /]' .
						'[/contact-form]' .
					'</p>',
					get_option( 'admin_email' ),
					__( 'WordCamp Contact Request', 'wordcamporg' ),
					__( 'Name', 'wordcamporg' ),
					__( 'Email', 'wordcamporg' ),
					__( 'Message', 'wordcamporg' )
				),
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Social Media Stream', 'wordcamporg' ),
				'content' =>
					'<p>' . __( '<em>Organizers note:</em> The [[tagregator]] shortcode will pull in a stream of social media posts and display them. In order to use it, you\'ll need to follow the setup instructions at http://wordpress.org/plugins/tagregator/installation, and then update "#wcxyz" below with your hashtag.', 'wordcamporg' ) . '</p> ' .
					'<p>[tagregator hashtag="#wcxzy"]</p>',
				'status'  => 'publish',
				'type'    => 'page',
			),

			array(
				'title'   => __( 'Code of Conduct', 'wordcamporg' ),
				'content' =>
					'<p>' .
						sprintf(
							__( '<em>Organizers note:</em> Below is a boilerplate code of conduct that you can customize; another great example is the Ada Initiative <a href="%s">anti-harassment policy.</a>', 'wordcamporg' ),
							'http://geekfeminism.wikia.com/wiki/Conference_anti-harassment/Policy'
						) .
					'</p> ' .

					'<p>' .
						sprintf(
							__( 'We also recommend the organizing team read this article on <a href="%s">how to take a harassment report</a>', 'wordcamporg' ),
							'http://geekfeminism.wikia.com/wiki/Conference_anti-harassment/Responding_to_reports'
						) .
					'</p> ' .

					'<p>' . __( 'Please update the portions <span style="color: red; text-decoration: underline;">with red text</span>. You can use the "Remove Formatting" button on the toolbar (the eraser icon on the second line) to remove the color and underline.', 'wordcamporg' ) .
					$this->get_code_of_conduct(),
				'status'  => 'publish',
				'type'    => 'page',
			),
		);

		return $pages;
	}

	/**
	 * Get the content for post stubs
	 *
	 * @param WP_Post $wordcamp
	 * @param array   $meta
	 *
	 * @return array
	 */
	protected function get_stub_posts( $wordcamp, $meta ) {
		$posts = array(
			array(
				'title'   => __( 'Welcome to ', 'wordcamporg' ) . get_option( 'blogname' ),
				'content' =>
					'<p>' . __( '<em>Organizers note:</em> Please update the portions <span style="color: red; text-decoration: underline;">with red text</span>.', 'wordcamporg' ) . '</p> ' .
					'<p>' . __( 'We\'re happy to announce that <span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> is officially on the calendar!', 'wordcamporg' ) . '</p> ' .
					'<p>' . __( '<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> will be <span style="color: red; text-decoration: underline;">DATE(S)</span> at <span style="color: red; text-decoration: underline;">LOCATION</span>.', 'wordcamporg' ) . '</p> ' .
					'<p>' . __( '<span style="color: red; text-decoration: underline;">Subscribe using the form in the sidebar</span> to stay up to date on the most recent news. We’ll be keeping you posted on all the details over the coming months, including speaker submissions, ticket sales and more!', 'wordcamporg' ) . '</p> ',
				'status'  => 'publish',
				'type'    => 'post',
			),

			array(
				'title'   => __( 'Call for Sponsors', 'wordcamporg' ),
				'content' => 
					'<p>' . __( '<em>Organizers note:</em> Make sure you update the "to" address and other fields before publishing this page!', 'wordcamporg' ) . '</p> ' .
					'<p>' . __( 'Blurb with information for potential sponsors.', 'wordcamporg' ) . '</p> ' .
					'<p>' .
						sprintf( '
							[contact-form to="enter-your-address-here@example.net" subject="%s"]
							[contact-field label="%s" type="text"     required="1" /]
							[contact-field label="%s" type="name"     required="1" /]
							[contact-field label="%s" type="email"    required="1" /]
							[contact-field label="%s" type="text"                  /]
							[contact-field label="%s" type="text"                  /]
							[contact-field label="%s" type="textarea" required="1" /]
							[contact-field label="%s" type="textarea"              /]
							[/contact-form]',
							__( 'WordCamp Sponsor Request', 'wordcamporg' ),
							__( 'Contact Name', 'wordcamporg' ),
							__( 'Company Name', 'wordcamporg' ),
							__( 'Email', 'wordcamporg' ),
							__( 'Phone Number', 'wordcamporg' ),
							__( 'Sponsorship Level', 'wordcamporg' ),
							__( 'Why Would you Like to Sponsor WordCamp?', 'wordcamporg' ),
							__( 'Questions / Comments', 'wordcamporg' )
						) .
					'</p>',
				'status'  => 'draft',
				'type'    => 'post',
			),

			array(
				'title'   => __( 'Call for Speakers', 'wordcamporg' ),
				'content' => 
					'<p>' . __( '<em>Organizers note:</em> Make sure you update the "to" address and other fields before publishing this page!', 'wordcamporg' ) . '</p> ' .    
					'<p>' . __( 'Blurb with information for potential speakers.', 'wordcamporg' ) . '</p> ' . 
					'<p>' .
						sprintf( '
							[contact-form to="enter-your-address-here@example.net" subject="%s"]
								[contact-field label="%s" type="text"     required="1" /]
								[contact-field label="%s" type="email"    required="1" /]
								[contact-field label="%s" type="textarea" required="1" /]
								[contact-field label="%s" type="text"     required="1" /]
								[contact-field label="%s" type="textarea"              /]
							[/contact-form]',
							__( 'WordCamp Speaker Request', 'wordcamporg' ),
							__( 'Name', 'wordcamporg' ),
							__( 'Email', 'wordcamporg' ),
							__( 'Topic(s) You would Like to Present On', 'wordcamporg' ),
							__( 'Intended Audience', 'wordcamporg' ),
							__( 'Past Speaking Experience (not necessary to apply)', 'wordcamporg' )
						) .
					'</p>',
				'status'  => 'draft',
				'type'    => 'post',
			),

			array(
				'title'   => __( 'Call for Volunteers', 'wordcamporg' ),
				'content' => 
					'<p>' . __( '<em>Organizers note:</em> Make sure you update the "to" address and other fields before publishing this page!', 'wordcamporg' ) . '</p> ' .
					'<p>' . __( 'Blurb with information for potential volunteers.', 'wordcamporg' ) . '</p> ' .
					'<p>' .
						sprintf( '
							[contact-form to="enter-your-address-here@example.net" subject="%s"]
								[contact-field label="%s" type="text"     required="1" /]
								[contact-field label="%s" type="email"    required="1" /]
								[contact-field label="%s" type="textarea" required="1" /]
								[contact-field label="%s" type="text"     required="1" /]
								[contact-field label="%s" type="textarea"              /]
							[/contact-form]',
							__( 'WordCamp Speaker Request', 'wordcamporg' ),
							__( 'Name', 'wordcamporg' ),
							__( 'Email', 'wordcamporg' ),
							__( 'Skills / Interests / Experience (not necessary to volunteer)', 'wordcamporg' ),
							__( 'Number of Hours Available', 'wordcamporg' ),
							__( 'Questions / Comments', 'wordcamporg' )
						) .
					'</p>',
				'status'  => 'draft',
				'type'    => 'post',
			),
		);

		return $posts;
	}

	/**
	 * Get the content for sponsor stubs
	 *
	 * These are just the multi-event sponsors. Each camp will also have local sponsors, but they'll add those manually.
	 *
	 * @todo - load these from the new Multi_Event_Sponsors post type instead of hardcoding.
	 *
	 * @param WP_Post $wordcamp
	 * @param array   $meta
	 *
	 * @return array
	 */
	protected function get_stub_me_sponsors( $wordcamp, $meta ) {
		$me_sponsors = array(
			array(
				'title'          => 'Code Poet',
				'content'        => "<p>If you use WordPress to build things for other people, Code Poet wants to make your life easier. No matter whether you freelance on a solo basis, lead a small web shop, make plugins in a dark closet, or crack the whip at a large design firm, Code Poet’s aim is to become your go-to source of information and resources to help you expand your WordPress skills and know-how. To make you better at what you do. To make it easier to make your living and look great doing it.</p> <p>You’re part of a tribe of WordPress designers and developers over 10,000 strong, spanning the entire globe. <a href='http://codepoet.com'>codepoet.com</a> aims to bring the working knowledge and real world strategies of those people into one place, for you to tap into.</p>",
				'status'         => 'publish',
				'type'           => 'wcb_sponsor',
				'featured_image' => 'http://central.wordcamp.org/files/2013/09/Code-Poet-2.png',
				'term'           => 'champion'
			),

			array(
				'title'          => 'WPML',
				'content'        => "<p>WPML turns WordPress websites multilingual. It works with caching, SEO and E-Commerce plugins, and allows the building of complete multilingual sites. WPML powers simple blogs as well as corporate and enterprise sites.</p> <p>WPML allows users to translate everything in the site, including content, menus, widgets and even theme and plugin texts. WPML powers over 400,000 commercial websites from all over the world.</p> <p>More information about going multilingual can be found at <a href='http://wpml.org'>WPML.org</a></p>",
				'status'         => 'publish',
				'type'           => 'wcb_sponsor',
				'featured_image' => 'http://central.wordcamp.org/files/2013/09/wpml-web.png',
				'term'           => 'accomplice'
			),

			array(
				'title'          => 'Bluehost',
				'content'        => "<p><a href='http://bluehost.com'>Bluehost</a> has been a WordPress partner since 2005 and powers over one million WordPress sites. Their goal is to provide outstanding hosting services and customer support for the best possible price. Bluehost is also constantly innovating and upgrading their services and infrastructure at no additional cost to their customers. Join the millions of other website owners that have already chosen Bluehost and see how they can help you with your site.</p>",
				'status'         => 'publish',
				'type'           => 'wcb_sponsor',
				'featured_image' => 'http://central.wordcamp.org/files/2013/09/bluehost-logo13.png',
				'term'           => 'pillar'
			),

			array(
				'title'          => 'DreamHost',
				'content'        => "<p>DreamHost is a global Web hosting and cloud services provider with over 350,000 customers and 1.2 million blogs, websites and apps hosted. The company offers a wide spectrum of Web hosting and cloud services including Shared Hosting, Virtual Private Servers (VPS), Dedicated Server Hosting, Domain Name Registration, the cloud storage service, DreamObjects, and the cloud computing service DreamCompute. More information can be found at <a href='http://dreamhost.com'>http://dreamhost.com</a>.</p>",
				'status'         => 'publish',
				'type'           => 'wcb_sponsor',
				'featured_image' => 'http://central.wordcamp.org/files/2013/09/dreamhost_logo-cmyk-no_tag-2012.jpg',
				'term'           => 'accomplice'
			),

			array(
				'title'          => 'Media Temple',
				'content'        => "<p>From its inception in 1998, (mt) Media Temple has been on a mission to help people and businesses succeed online.</p> <p>Over 125,000 customers in 100 countries now rely on Media Temple’s tools for domain registration, web hosting, business applications, virtual servers, and other cloud services to power more than 1.5 million websites. With 200 dedicated, U.S.-based employees, Media Temple takes pride in our 24/7 customer support. Our customers range from everyday people to top bloggers, creative professionals, and small businesses, as well as large enterprises like Starbucks, Adidas, Samsung, and Toyota.</p> <p>More information can be found anytime on the web at <a href='http://mediatemple.com'>http://mediatemple.com</a> or on Twitter <a href='https://twitter.com/mediatemple'>@MediaTemple</a>.</p>",
				'status'         => 'publish',
				'type'           => 'wcb_sponsor',
				'featured_image' => 'http://central.wordcamp.org/files/2013/12/mt-250x140-dk.png',
				'term'           => 'pillar'
			),
		);

		return $me_sponsors;
	}

	/**
	 * Generate stub posts for thanking Multi-Event Sponsors
	 *
	 * The MES_Sponsorship_Level post excerpts contain the intro text for these messages, and the MES_Sponsor
	 * post excerpts contain the blurb for each sponsor.
	 *
	 * @param WP_Post $wordcamp
	 * @param array   $meta
	 *
	 * @return array
	 */
	protected function get_stub_me_sponsor_thank_yous( $wordcamp, $meta ) {
		/** @var $multi_event_sponsors Multi_Event_Sponsors */
		global $multi_event_sponsors;
		$pages = array();

		switch_to_blog( BLOG_ID_CURRENT_SITE ); // central.wordcamp.org
		$assigned_me_sponsors = $multi_event_sponsors->get_wordcamp_me_sponsors( $wordcamp->ID, 'sponsor_level' );
		$sponsorship_levels   = get_posts( array(
			'post_type'   => MES_Sponsorship_Level::POST_TYPE_SLUG,
			'numberposts' => -1
		) );
		restore_current_blog();

		foreach ( $sponsorship_levels as $sponsorship_level ) {
			if ( ! empty( $assigned_me_sponsors[ $sponsorship_level->ID ] ) ) {
				$pages[] = array(
					'title'   => sprintf( __( 'Thank you to our %s sponsors', 'wordcamporg' ), $sponsorship_level->post_title ),
					'content' => sprintf(
						'%s %s',
						str_replace(
							'[sponsor_names]',
							$multi_event_sponsors->get_sponsor_names( $assigned_me_sponsors[ $sponsorship_level->ID ] ),
							$sponsorship_level->post_excerpt
						),
						$multi_event_sponsors->get_sponsor_excerpts( $assigned_me_sponsors[ $sponsorship_level->ID ] )
					),
					'status'  => 'draft',
					'type'    => 'post',
				);
			}
		}

		return $pages;
	}

	/**
	 * Get the default code of conduct
	 *
	 * @return string
	 */
	protected function get_code_of_conduct() {
		ob_start();
		?>

		<ol>
			<li>
				<h3>Purpose</h3>

				<p>
					<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> believes our community should be truly open for everyone. As such, we are committed to providing a friendly, safe and welcoming environment for all, regardless of gender, sexual orientation, disability, ethnicity, religion, preferred operating system, programming language, or text editor.
				</p>

				<p>This code of conduct outlines our expectations for participant behavior as well as the consequences for unacceptable behavior.</p>

				<p>We invite all sponsors, volunteers, speakers, attendees, and other participants to help us realize a safe and positive conference experience for everyone.</p>
			</li>

			<li>
				<h3>Open Source Citizenship</h3>

				<p>A supplemental goal of this code of conduct is to increase open source citizenship by encouraging participants to recognize and strengthen the relationships between what we do and the community at large.</p>

				<p>In service of this goal,
					<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> organizers will be taking nominations for exemplary citizens throughout the event and will recognize select participants after the conference on the website.
				</p>

				<p>If you see someone who is making an extra effort to ensure our community is welcoming, friendly, and encourages all participants to contribute to the fullest extent, we want to know.
					<span style="color: red; text-decoration: underline;">You can nominate someone at the Registration table or online at URL HERE.</span>
				</p>
			</li>

			<li>
				<h3>Expected Behavior</h3>

				<ul>
					<li>Be considerate, respectful, and collaborative.</li>
					<li>Refrain from demeaning, discriminatory or harassing behavior and speech.</li>
					<li>Be mindful of your surroundings and of your fellow participants. Alert conference organizers if you notice a dangerous situation or someone in distress.</li>
					<li>Participate in an authentic and active way. In doing so, you help to create
						<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> and make it your own.
					</li>
				</ul>
			</li>

			<li>
				<h3>Unacceptable Behavior</h3>

				<p>Unacceptable behaviors include: intimidating, harassing, abusive, discriminatory, derogatory or demeaning conduct by any attendees of
					<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> and related events. All
					<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> venues may be shared with members of the public; please be respectful to all patrons of these locations.
				</p>

				<p>Harassment includes: offensive verbal comments related to gender, sexual orientation, race, religion, disability; inappropriate use of nudity and/or sexual images in public spaces (including presentation slides); deliberate intimidation, stalking or following; harassing photography or recording; sustained disruption of talks or other events; inappropriate physical contact, and unwelcome sexual attention.</p>
			</li>

			<li>
				<h3>Consequences Of Unacceptable Behavior</h3>

				<p>Unacceptable behavior will not be tolerated whether by other attendees, organizers, venue staff, sponsors, or other patrons of
					<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> venues.</p>

				<p>Anyone asked to stop unacceptable behavior is expected to comply immediately.</p>

				<p>If a participant engages in unacceptable behavior, the conference organizers may take any action they deem appropriate, up to and including expulsion from the conference without warning or refund.</p>
			</li>

			<li>
				<h3>What To Do If You Witness Or Are Subject To Unacceptable Behavior</h3>

				<p>If you are subject to unacceptable behavior, notice that someone else is being subject to unacceptable behavior, or have any other concerns, please notify a conference organizer as soon as possible.</p>

				<p>The
					<span style="color: red; text-decoration: underline;">WordCamp YourCityName</span> team will be available to help participants contact venue security or local law enforcement, to provide escorts, or to otherwise assist those experiencing unacceptable behavior to feel safe for the duration of the conference.
					<span style="color: red; text-decoration: underline;">Volunteers will be wearing XXXXXXXXXXXXXXXXXXXXXXXX.</span> Any volunteer can connect you with a conference organizer. You can also come to the special registration desk in the lobby and ask for the organizers.
				</p>
			</li>

			<li>
				<h3>Scope</h3>

				<p>We expect all conference participants (sponsors, volunteers, speakers, attendees, and other guests) to abide by this code of conduct at all conference venues and conference-related social events.</p>
			</li>

			<li>
				<h3>Contact Information</h3>

				<p>
					<span style="color: red; text-decoration: underline;">Contact info here! Make sure this includes a way to access the organizers during the event.</span>
				</p>
			</li>

			<li>
				<h3>License And Attribution</h3>

				<p>This Code of Conduct is a direct swipe from the awesome work of Open Source Bridge, but with our event information substituted. The original is available at
					<a href="http://opensourcebridge.org/about/code-of-conduct/">http://opensourcebridge.org/about/code-of-conduct/</a> and is released under a
					<a href="http://creativecommons.org/licenses/by-sa/3.0/">Creative Commons Attribution-ShareAlike</a> license.
				</p>
			</li>
		</ol>

		<?php
		return ob_get_clean();
	}
} // WordCamp_New_Site
