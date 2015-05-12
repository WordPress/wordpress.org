<?php
/**
 * WordCamp Central Functions
 *
 * (Almost) everything in this file works around the base class called WordCamp_Central_Theme,
 * which is a static class, and should never have an instance (hence the trigger_error trick
 * in the class constructor.)
 *
 */

/**
 * WordCamp_Central_Theme Class
 *
 * Static class, used a lot throughout the WordCamp Central theme,
 * so please be careful when changing names, extending, etc. Everything
 * starts from the on_load method. The __construct method triggers an error.
 */
class WordCamp_Central_Theme {

	/**
	 * Constructor, triggers an error message.
	 * Please use the class directly, without creating an instance.
	 */
	function __construct() {
		trigger_error( 'Please use class, not instance! ' . __CLASS__ );
	}

	/**
	 * Use this class method instead of the usual constructor.
	 * Add more actions and filters from within this method.
	 */
	public static function on_load() {
		add_action( 'after_setup_theme', array( __CLASS__, 'after_setup_theme' ), 11 );
		add_action( 'widgets_init', array( __CLASS__, 'widgets_init' ), 11 );
		add_action( 'pre_get_posts', array( __CLASS__, 'pre_get_posts' ) );
		add_action( 'init', array( __CLASS__, 'process_forms' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
		add_action( 'wp_ajax_get_latest_wordcamp_tweets', array( __CLASS__, 'get_latest_tweets' ) );
		add_action( 'wp_ajax_nopriv_get_latest_wordcamp_tweets', array( __CLASS__, 'get_latest_tweets' ) );

		add_filter( 'excerpt_more', array( __CLASS__, 'excerpt_more' ), 11 );
		// add_filter( 'wcpt_register_post_type', array( __CLASS__, 'wcpt_register_post_type' ) ); // set to public in wcpt plugin
		add_filter( 'nav_menu_css_class', array( __CLASS__, 'nav_menu_css_class' ), 10, 3 );
		add_filter( 'wp_nav_menu_items', array( __CLASS__, 'add_rss_links_to_footer_menu' ), 10, 2 );

		add_shortcode( 'wcc_map',         array( __CLASS__, 'shortcode_map'         ) );
		add_shortcode( 'wcc_about_stats', array( __CLASS__, 'shortcode_about_stats' ) );
	}

	/**
	 * Fired during after_setup_theme.
	 */
	static function after_setup_theme() {
		add_theme_support( 'post-formats', array( 'link', 'image' ) );
		$GLOBALS['custom_background'] = 'WordCamp_Central_Theme_Kill_Features';
		$GLOBALS['custom_image_header'] = 'WordCamp_Central_Theme_Kill_Features';

		// Add some new image sizes, also site shot is 205x148, minimap is 130x70
		add_image_size( 'wccentral-thumbnail-small', 82, 37, true );
		add_image_size( 'wccentral-thumbnail-large', 926, 160, true );
		add_image_size( 'wccentral-thumbnail-past', 130, 60, true );
		add_image_size( 'wccentral-thumbnail-hero', 493, 315, true );

		// Schedule for cache busting
		if ( ! wp_next_scheduled( 'wccentral_cache_busters' ) ) {
			wp_schedule_event( time(), 'hourly', 'wccentral_cache_busters' );
		}
		add_action( 'wccentral_cache_busters', array( __CLASS__, 'cache_busters' ) );

		// Uncomment for debugging
		// wp_clear_scheduled_hook( 'wccentral_cache_busters' );

		// Can I haz editor style?
		add_editor_style();
	}

	/**
	 * Fired during widgets_init, removes some Twenty Ten sidebars.
	 */
	static function widgets_init() {
		unregister_sidebar( 'fourth-footer-widget-area' );
		unregister_sidebar( 'secondary-widget-area' );

		register_sidebar( array(
			'name' => __( 'Pages Widget Area', 'twentyten' ),
			'id' => 'pages-widget-area',
			'description' => __( 'Widgets displayed on pages.', 'twentyten' ),
			'before_widget' => '<li id="%1$s" class="widget-container %2$s">',
			'after_widget' => '</li>',
			'before_title' => '<h3 class="widget-title">',
			'after_title' => '</h3>',
		) );
		register_sidebar( array(
			'name' => __( 'Blog Widget Area', 'twentyten' ),
			'id' => 'blog-widget-area',
			'description' => __( 'Widgets displayed on the blog.', 'twentyten' ),
			'before_widget' => '<li id="%1$s" class="widget-container %2$s">',
			'after_widget' => '</li>',
			'before_title' => '<h3 class="widget-title">',
			'after_title' => '</h3>',
		) );
	}

	/**
	 * Fired during pre_get_posts, $query is passed by reference.
	 * Removes pages and WordCamps from search results.
	 */
	static function pre_get_posts( $query ) {
		if ( $query->is_search && $query->is_main_query() && ! is_admin() )
			$query->set( 'post_type', 'post' );
	}

	/**
	 * Fired during wccentral_cache_busters, typically during a Cron API request.
	 * @todo maybe use self:: (php 5.3) instead of call_user_func
	 */
	static function cache_busters() {
		$busters = array( 'get_photos', 'get_videos' );
		foreach ( $busters as $method )
			call_user_func( array( __CLASS__, $method ) );
	}

	/**
	 * Forms Processing
	 *
	 * Fired during init, checks REQUEST data for any submitted forms,
	 * does the whole form processing and redirects if necessary.
	 */
	static function process_forms() {
		$available_actions = array( 'subscribe' );
		if ( ! isset( $_REQUEST['wccentral-form-action'] ) || ! in_array( $_REQUEST['wccentral-form-action'], $available_actions ) )
			return;

		$action = $_REQUEST['wccentral-form-action'];
		switch ( $action ) {

			// Subscribe to mailing list
			case 'subscribe':
				if ( ! call_user_func( array( __CLASS__, 'can_subscribe' ) ) )
					return;

				// Jetpack will do the is_email check for us
				$email = $_REQUEST['wccentral-subscribe-email'];
				$subscribe = Jetpack_Subscriptions::subscribe( $email, 0, false );

				// The following part is taken from the Jetpack subscribe widget (subscriptions.php)
				if ( is_wp_error( $subscribe ) ) {
					$error = $subscribe->get_error_code();
				} else {
					$error = false;
					foreach ( $subscribe as $response ) {
						if ( is_wp_error( $response ) ) {
							$error = $response->get_error_code();
							break;
						}
					}
				}

				if ( $error ) {
					switch( $error ) {
						case 'invalid_email':
							$redirect = add_query_arg( 'subscribe', 'invalid_email' );
							break;
						case 'active': case 'pending':
							$redirect = add_query_arg( 'subscribe', 'already' );
							break;
						default:
							$redirect = add_query_arg( 'subscribe', 'error' );
							break;
					}
				} else {
					$redirect = add_query_arg( 'subscribe', 'success' );
				}

				wp_safe_redirect( esc_url_raw( $redirect ) );
				exit;
				break;
		}

		return;
	}

	/**
	 * Enqueue scripts and styles.
	 */
	static function enqueue_scripts() {
		wp_enqueue_style( 'central', get_stylesheet_uri(), array(), 6 );
		wp_enqueue_script( 'wordcamp-central', get_stylesheet_directory_uri() . '/js/central.js', array( 'jquery', 'underscore' ), 2, true );

		wp_localize_script( 'wordcamp-central', 'wordcampCentralOptions', self::get_javascript_options() );

		/* We add some JavaScript to pages with the comment form
		 * to support sites with threaded comments (when in use).
		 */
		if ( is_singular() && get_option( 'thread_comments' ) ) {
			wp_enqueue_script( 'comment-reply' );
		}

		if ( is_front_page() || is_page( 'about' ) ) {
			wp_enqueue_script( 'jquery-cycle', get_stylesheet_directory_uri() . '/js/jquery.cycle.min.js', array( 'jquery' ) );
		}

		if ( is_page( 'about' ) || is_page( 'schedule' ) ) {
			wp_enqueue_script( 'google-maps', 'https://maps.googleapis.com/maps/api/js', array(), false, true );
		}
	}

	/**
	 * Build the array of options to pass to the client side
	 *
	 * @return array
	 */
	protected static function get_javascript_options() {
		global $post;

		$options = array( 'ajaxURL' => admin_url( 'admin-ajax.php' ) );

		if ( $map_id = self::get_map_id( $post->post_content ) ) {
			$options['mapContainer']            = "wcc-map-$map_id";
			$options['markerIconBaseURL']       = get_stylesheet_directory_uri() . '/images/';
			$options['markerClusterIcon']       = 'icon-marker-clustered.png';
			$options['markerIconAnchorXOffset'] = 24;
			$options['markerIconHeight']        = 94;
			$options['markerIconWidth']         = 122;

			if ( $map_markers = self::get_map_markers( $map_id ) ) {
				$options['mapMarkers'] = $map_markers;
			}
		}

		return $options;
	}

	/**
	 * Get the ID of the map called in the given page
	 *
	 * @param string $post_content
	 *
	 * @return mixed A string of the map name on success, or false on failure
	 */
	protected static function get_map_id( $post_content ) {
		$map_id = false;

		if ( has_shortcode( $post_content, 'wcc_map' ) ) {
			preg_match_all( '/' . get_shortcode_regex() . '/s', $post_content, $shortcodes, PREG_SET_ORDER );

			foreach ( $shortcodes as $shortcode ) {
				if ( 'wcc_map' == $shortcode[2] ) {
					$attributes = shortcode_parse_atts( $shortcode[3] );
					$map_id     = sanitize_text_field( $attributes['id'] );
					break;
				}
			}
		}

		return $map_id;
	}

	/**
	 * Get the markers assigned to the given map
	 *
	 * @param string $map_id
	 *
	 * @return array
	 */
	protected static function get_map_markers( $map_id ) {
		$transient_key = "wcc_map_markers_$map_id";

		if ( $markers = get_transient( $transient_key ) ) {
			return $markers;
		} else {
			$markers = array();
		}

		// Get the raw marker posts for the given map
		$parameters = array(
			'post_type'      => 'wordcamp',
			'posts_per_page' => -1,
		);

		switch( $map_id ) {
			case 'schedule':
				$parameters['post_status'][] = array( 'publish', 'pending' );
				$parameters['meta_query'][] = array(
					'key'     => 'Start Date (YYYY-mm-dd)',
					'value'   => strtotime( '-2 days' ),
					'compare' => '>',
				);
				break;
		}

		$raw_markers = get_posts( $parameters );

		// Convert the raw markers into prepared objects that are ready to be used on the JavaScript side
		foreach ( $raw_markers as $marker ) {
			if ( 'schedule' == $map_id ) {
				$marker_type = 'upcoming';
			} else {
				$marker_type = get_post_meta( $marker->ID, 'Start Date (YYYY-mm-dd)', true ) > strtotime( '-2 days' ) ? 'upcoming' : 'past';
			}

			if ( ! $coordinates = get_post_meta( $marker->ID, '_venue_coordinates', true ) ) {
				continue;
			}

			$markers[ $marker->ID ] = array(
				'id'          => $marker->ID,
				'name'        => wcpt_get_wordcamp_title( $marker->ID ),
				'dates'       => wcpt_get_wordcamp_start_date( $marker->ID ),
				'location'    => get_post_meta( $marker->ID, 'Location', true ),
				'venueName'   => get_post_meta( $marker->ID, 'Venue Name', true ),
				'url'         => self::get_best_wordcamp_url( $marker->ID ),
				'latitude'    => $coordinates['latitude'],
				'longitude'   => $coordinates['longitude'],
				'iconURL'     => "icon-marker-{$marker_type}-2x.png",
			);
		}

		$markers = apply_filters( 'wcc_get_map_markers', $markers );

		set_transient( $transient_key, $markers, WEEK_IN_SECONDS );

		return $markers;
	}

	/**
	 * Filters excerpt_more.
	 */
	static function excerpt_more( $more ) {
		return '&nbsp;&hellip;';
	}

	/**
	 * Filters wcpt_register_post_type, sets post type to public.
	 * @todo move to wcpt_register_post_types when ready.
	 */
	static function wcpt_register_post_type( $args ) {
		$args['public'] = true;
		return $args;
	}

	/**
	 * Filters nav_menu_css_class.
	 * Make sure Schedule is current-menu-item when viewing WordCamps.
	 */
	static function nav_menu_css_class( $classes, $item, $args ) {
		if ( 'wordcamp' == get_post_type() ) {
			if ( home_url( '/schedule/' ) == trailingslashit( $item->url ) ) {
				$classes[] = 'current-menu-item';
			} else {
				$remove = array( 'current-menu-item', 'current_page_parent', 'current_page_ancestor' );
				foreach ( $remove as $class )
					$classes = array_splice( $classes, array_search( $class, $classes ), 1 );
			}
		}
		return $classes;
	}

	public static function add_rss_links_to_footer_menu( $items, $args ) {
		if ( 'menu-footer' == $args->container_class ) {
			ob_start();

			?>

			<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="<?php echo esc_url( get_feed_link() ); ?>">RSS (posts)</a></li>
			<li class="menu-item menu-item-type-custom menu-item-object-custom"><a href="<?php echo esc_url( get_post_type_archive_feed_link( 'wordcamp' ) ); ?>">RSS (WordCamps)</a></li>

			<?php
			$items .= ob_get_clean();
		}

		return $items;
	}

	/**
	 * Get Session List
	 *
	 * Uses the WordCamp post type to loop through the latest
	 * WordCamps, if WordCamp URLs are valid network blogs, switches
	 * to blog and queries for Session.
	 *
	 * @uses switch_to_blog, get_blog_details, wp_object_cache
	 * @return assoc array with session and WC info
	 */
	public static function get_sessions( $count = 4 ) {
		if ( ! function_exists( 'wcpt_has_wordcamps' ) )
			return false;

		// Check cache
		if ( (bool) $sessions = wp_cache_get( 'wccentral_sessions_' . $count ) )
			return $sessions;

		// Take latest WordCamps
		$args = array(
			'posts_per_page' => $count + 10,
			'meta_key'       => 'Start Date (YYYY-mm-dd)',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( array(
				'key'        => 'Start Date (YYYY-mm-dd)',
				'value'      => strtotime( '-2 days' ),
				'compare'    => '>'
			) )
		);

		if ( ! wcpt_has_wordcamps( $args ) )
			return false;

		// We'll hold the sessions here
		$sessions = array();

		// Loop through the latest WCs
		while ( wcpt_wordcamps() ) {
			wcpt_the_wordcamp();

			// Store WC data (will be unavailable after switch_to_blog)
			$domain = parse_url( wcpt_get_wordcamp_url(), PHP_URL_HOST );
			$blog_details = get_blog_details( array( 'domain' => $domain ), false );

			$wordcamp_date = wcpt_get_wordcamp_start_date( 0, 'F ' );
			$wordcamp_date .= wcpt_get_wordcamp_start_date( 0, 'j' );
			if ( wcpt_get_wordcamp_end_date( 0, 'j' ) )
				$wordcamp_date .= '-' . wcpt_get_wordcamp_end_date( 0, 'j' );

			// Valid for all sessions in this WC
			$session = array(
				'wordcamp_title' => wcpt_get_wordcamp_title(),
				'wordcamp_permalink' => wcpt_get_wordcamp_permalink(),
				'wordcamp_date' => $wordcamp_date,
				'wordcamp_thumb' => get_the_post_thumbnail( get_the_ID(), 'wccentral-thumbnail-small' ),
			);

			if ( isset( $blog_details->blog_id ) && $blog_details->blog_id ) {
				$my_blog_id = (int) $blog_details->blog_id;

				switch_to_blog( $my_blog_id );

					// Look through 5 sessions, store in $sessions array
					$sessions_query = new WP_Query( array( 'post_type' => 'wcb_session', 'posts_per_page' => 5, 'post_status' => 'publish' ) );
					while ( $sessions_query->have_posts() ) {
						$sessions_query->the_post();

						// Add the extra fields to $session and push to $sessions
						$sessions[] = array_merge( $session, array(
							'name' => apply_filters( 'the_title', get_the_title() ),
							'speakers' => get_post_meta( get_the_ID(), '_wcb_session_speakers', true ),
							'permalink' => get_permalink( get_the_ID() ),
						) );
					}

				restore_current_blog();
			}
		}

		// Randomize and pick $count
		shuffle( $sessions );
		$sessions = array_slice( $sessions, 0, $count );

		// Cache in transients
		wp_cache_set( 'wccentral_sessions_' . $count, $sessions );
		return $sessions;
	}

	/**
	 * Get WordCamp Photos
	 *
	 * Uses the Flickr API to fetch photos tagged wordcamp sf,
	 * caches data in options. Cached data is busted with the Cron API.
	 * @uses wp_remote_get
	 * @return array of photos or an empty array
	 */
	public static function get_photos() {

		// Always serve cached data
		$photos = get_option( 'wccentral_photos', array() );
		if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON )
			return $photos;

		// Attempt to update the cache if it was a cron request.
		$feed_url = 'http://api.flickr.com/services/feeds/photos_public.gne?format=php_serial&tagmode=any&tags=wcsf,wordcamp%20san%20francisco,wordcampsf,wcsf2011,wordcamp%20sf';

		$response = wp_remote_get( $feed_url );
		if ( is_wp_error( $response ) )
			return array();

		$feed = unserialize( wp_remote_retrieve_body( $response ) );
		$photos = $feed['items'];

		if ( ! empty( $photos ) ) {
			update_option( 'wccentral_photos', $photos );
		}

		return $photos;
	}

	/**
	 * Get WordCamp Videos
	 *
	 * Reads the WordPress.tv WordCamp category feed for the
	 * latest WordCamp videos. Caches in options, busts via Cron API.
	 *
	 * @uses fetch_feed
	 * @return assoc array of videos or empty array
	 */
	public static function get_videos( $count = 4 ) {

		// Always serve cached data
		$videos = get_option( 'wccentral_videos', array() );
		if ( ! defined( 'DOING_CRON' ) || ! DOING_CRON )
			return array_slice( $videos, 0, $count );

		$videos = array();
		$feed_url = 'http://wordpress.tv/category/wordcamptv/feed/?x=2';
		$feed = fetch_feed( $feed_url );

		if ( is_wp_error( $feed ) )
			return $videos;

		$maxitems = $feed->get_item_quantity( 10 );
		$items = $feed->get_items( 0, $maxitems );
		foreach ( $items as $item ) {

			// Media feed
			$enclosure = $item->get_enclosure();

			$videos[] = array(
				'thumbnail' => $enclosure->get_thumbnail(),
				'title' => $item->get_title(),
				'permalink' => $item->get_permalink(),
			);
		}

		if ( ! empty( $videos ) ) {
			update_option( 'wccentral_videos', $videos );
		}


		$videos = array_slice( $videos, 0, $count );
		return $videos;
	}

	/**
	 * Retrieve Subscription Status from $_REQUEST
	 */
	public static function get_subscription_status() {
		return isset( $_REQUEST['subscribe'] ) ? strtolower( $_REQUEST['subscribe'] ) : false;
	}

	/**
	 * Subscription Check
	 * Returns true if subscriptions are available
	 */
	public static function can_subscribe() {
		return class_exists( 'Jetpack_Subscriptions' ) && is_callable( array( 'Jetpack_Subscriptions', 'subscribe' ) );
	}

	/**
	 * Fetch the latest tweets from the @WordCamp account
	 *
	 * This is an AJAX callback returning JSON-formatted data.
	 *
	 * We're manually expiring/refreshing the transient to ensure that we only ever update it when we have a
	 * valid response from the API. If there is a problem retrieving new data from the API, then we want to
	 * continue displaying the valid cached data until we can successfully retrieve new data. The data is still
	 * stored in a transient instead of an option, though, so that it can be cached in memory.
	 *
	 * Under certain unlikely conditions, this could cause an API rate limit violation. If the data is expired
	 * and we can connect to the API at the network level, but then the request fails at the application level
	 * (invalid credentials, etc), then we'll be hitting the API every time this function is called. If that
	 * does ever happen, it could be fixed by setting the timestamp of the last attempt in a transient and only
	 * issuing another attempt if ~2 minutes have passed.
	 */
	public static function get_latest_tweets() {
		$transient_key = 'wcc_latest_tweets';
		$tweets        = get_transient( $transient_key );
		$expired       = $tweets['last_update'] < strtotime( 'now - 15 minutes' );

		if ( ! $tweets || $expired ) {
			$response = wp_remote_get(
				'https://api.twitter.com/1.1/statuses/user_timeline.json?count=6&trim_user=true&exclude_replies=true&include_rts=false&screen_name=wordcamp',
				array(
					'headers' => array( 'Authorization' => 'Bearer ' . TWITTER_BEARER_TOKEN_WORDCAMP_CENTRAL ),
				)
			);

			if ( ! is_wp_error( $response ) ) {
				$tweets['tweets'] = json_decode( wp_remote_retrieve_body( $response ) );

				/*
				 * Remove all but the first 3 tweets
				 *
				 * The Twitter API includes retweets in the `count` parameter, even if include_rts=false is passed,
				 * so we have to request more tweets than we actually want and then cut it down here.
				 */
				if ( is_array( $tweets['tweets'] ) ) {
					$tweets['tweets']      = array_slice( $tweets['tweets'], 0, 3 );
					$tweets['tweets']      = self::sanitize_format_tweets( $tweets['tweets'] );
					$tweets['last_update'] = time();

					set_transient( $transient_key, $tweets );
				}
			}
		}

		wp_send_json_success( $tweets );
	}

	/**
	 * Sanitize and format the tweet objects
	 *
	 * Whitelist the fields to cut down on how much data we're storing/transmitting, but also to force
	 * future devs to manually enable/sanitize any new fields that are used, which avoids the risk of
	 * accidentally using an unsafe value.
	 *
	 * @param array $tweets
	 *
	 * @return array
	 */
	protected static function sanitize_format_tweets( $tweets ) {
		$whitelisted_fields = array( 'id_str' => '', 'text' => '', 'created_at' => '' );

		foreach ( $tweets as & $tweet ) {
			$tweet           = (object) shortcode_atts( $whitelisted_fields, $tweet );
			$tweet->id_str   = sanitize_text_field( $tweet->id_str );
			$tweet->text     = wp_kses( $tweet->text, wp_kses_allowed_html( 'data' ), array( 'http', 'https', 'mailto' ) );
			$tweet->text     = make_clickable( $tweet->text );
			$tweet->text     = self::link_hashtags_and_usernames( $tweet->text );
			$tweet->time_ago = human_time_diff( strtotime( $tweet->created_at ) );
		}

		return $tweets;
	}

	/**
	 * Convert usernames and hashtags to links
	 *
	 * Based on Tagregator's TGGRSourceTwitter::link_hashtags_and_usernames().
	 *
	 * @param string $text
	 *
	 * @return string
	 */
	protected static function link_hashtags_and_usernames( $content ) {
		$content = preg_replace( '/@(\w+)/',       '<a href="https://twitter.com/\\1"          class="wc-tweets-username">@\\1</a>', $content );
		$content = preg_replace( '/(?<!&)#(\w+)/', '<a href="https://twitter.com/search?q=\\1" class="wc-tweets-tag"     >#\\1</a>', $content );

		return $content;
	}

	/**
	 * Twenty Ten Comment
	 * Overrides the twentyten_comment function in the parent theme.
	 */
	public static function twentyten_comment( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		switch ( $comment->comment_type ) :
			case '' :
		?>
		<li <?php comment_class(); ?> id="li-comment-<?php comment_ID(); ?>">
			<div id="comment-<?php comment_ID(); ?>" class="comment-container">
			<div class="comment-author vcard">
				<?php echo get_avatar( $comment, 60 ); ?>
				<?php printf( __( '%s <span class="says">says:</span>', 'twentyten' ), sprintf( '<cite class="fn">%s</cite>', get_comment_author_link() ) ); ?>
			</div><!-- .comment-author .vcard -->
			<?php if ( $comment->comment_approved == '0' ) : ?>
				<em class="comment-awaiting-moderation"><?php _e( 'Your comment is awaiting moderation.', 'twentyten' ); ?></em>
				<br />
			<?php endif; ?>

			<div class="comment-meta commentmetadata"><a href="<?php echo esc_url( get_comment_link( $comment->comment_ID ) ); ?>">
				<?php
					/* translators: 1: date, 2: time */
					printf( __( '%1$s at %2$s', 'twentyten' ), get_comment_date(),  get_comment_time() ); ?></a><?php edit_comment_link( __( '(Edit)', 'twentyten' ), ' ' );
				?>
			</div><!-- .comment-meta .commentmetadata -->

			<div class="comment-body"><?php comment_text(); ?></div>

			<div class="reply">
				<?php comment_reply_link( array_merge( $args,
					array(
						'depth' => $depth,
						'max_depth' => $args['max_depth'],
						'reply_text' => '&#10149; Reply'
					)
				) ); ?>
			</div><!-- .reply -->
		</div><!-- #comment-##  -->

		<?php
				break;
			case 'pingback'  :
			case 'trackback' :
		?>
		<li class="post pingback">
			<p><?php _e( 'Pingback:', 'twentyten' ); ?> <?php comment_author_link(); ?><?php edit_comment_link( __( '(Edit)', 'twentyten' ), ' ' ); ?></p>
		<?php
				break;
		endswitch;
	}

	public static function get_upcoming_wordcamps_query( $count = 10 ) {
		$query = new WP_Query( array(
			'post_type'		 => WCPT_POST_TYPE_ID,
			'posts_per_page' => $count,
			'meta_key'       => 'Start Date (YYYY-mm-dd)',
			'orderby'        => 'meta_value',
			'order'          => 'ASC',
			'meta_query'     => array( array(
				'key'        => 'Start Date (YYYY-mm-dd)',
				'value'      => strtotime( '-2 days' ),
				'compare'    => '>'
			) )
		) );
		return $query;
	}

	public static function the_wordcamp_date( $wordcamp_id = 0 ) {
		$start_day = wcpt_get_wordcamp_start_date( $wordcamp_id, 'j' );
		$start_month = wcpt_get_wordcamp_start_date( $wordcamp_id, 'F' );
		$end_day = wcpt_get_wordcamp_end_date( $wordcamp_id, 'j' );
		$end_month = wcpt_get_wordcamp_end_date( $wordcamp_id, 'F' );

		echo "$start_month $start_day";
		if ( $end_day ) {
			echo '-';
			if ( $start_month != $end_month )
				echo "$end_month ";

			echo $end_day;
		}
	}

	/**
	 * Group an array of WordCamps by year
	 *
	 * @param array $wordcamps
	 *
	 * @return array
	 */
	public static function group_wordcamps_by_year( $wordcamps ) {
		$grouped_wordcamps = array();

		foreach ( $wordcamps as $wordcamp ) {
			$date = get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true );

			if ( $date && $year = date( 'Y', (int) $date ) ) {
				$grouped_wordcamps[ $year ][] = $wordcamp;
			}
		}

		return $grouped_wordcamps;
	}

	/**
	 * Returns a WordCamp's website URL if it's available, or their Central page if is isn't.
	 *
	 * @param int $post_id
	 *
	 * @return string
	 */
	public static function get_best_wordcamp_url( $post_id = 0 ) {
		$url = wcpt_get_wordcamp_url( $post_id );

		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			$url = wcpt_get_wordcamp_permalink( $post_id );
		}

		return $url;
	}

	/**
	 * Render the [wcc_map] shortcode
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	public static function shortcode_map( $attributes ) {
		$attributes = shortcode_atts( array( 'id' => '' ), $attributes );

		ob_start();
		require( __DIR__ . '/shortcode-about-map.php' );
		return ob_get_clean();
	}

	/**
	 * Render the [wcc_about_stats] shortcode
	 *
	 * @param array $attributes
	 *
	 * @return string
	 */
	public static function shortcode_about_stats( $attributes ) {
		$map_stats = self::get_map_stats();

		ob_start();
		require( __DIR__ . '/shortcode-about-stats.php' );
		return ob_get_clean();
	}

	/**
	 * Gather the stats for the [wcc_about_stats] shortcode
	 *
	 * There isn't an easy way to collect the country stats programmatically, but it just takes a minute to
	 * manually count the number countries on the map that have pins.
	 *
	 * @return array
	 */
	protected static function get_map_stats() {
		$transient_key = 'wcc_about_map_stats';

		if ( ! $map_stats = get_transient( $transient_key ) ) {
			$cities    = array();
			$wordcamps = new WP_Query( array(
				'post_type'      => 'wordcamp',
				'posts_per_page' => -1,
			) );

			// Count the number of cities
			foreach ( $wordcamps->posts as $wordcamp ) {
				$url = get_post_meta( $wordcamp->ID, 'URL', true );

				if ( $hostname = parse_url( $url, PHP_URL_HOST ) ) {
					$city = explode( '.', $hostname );
					$cities[ $city[0] ] = true;
				}
			}

			// Compile the results
			$map_stats = array(
				'wordcamps'  => $wordcamps->found_posts,
				'cities'     => count( $cities ),
				'countries'  => 48,
				'continents' => 6,
			);

			set_transient( $transient_key, $map_stats, 2 * WEEK_IN_SECONDS );
		}

		return $map_stats;
	}
}

// Load the theme class, this is where it all starts.
WordCamp_Central_Theme::on_load();

// Override the parent's comment function with ours.
function twentyten_comment( $comment, $args, $depth ) {
	return WordCamp_Central_Theme::twentyten_comment( $comment, $args, $depth );
}

// This class is used to kill header images and custom background added by 2010.
class WordCamp_Central_Theme_Kill_Features { function init() { return false; } }
