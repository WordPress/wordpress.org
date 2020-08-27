<?php

/**
 * WordPress.tv Functions
 */
class WordPressTV_Theme {
	function __construct() {
		if ( apply_filters( 'wptv_setup_theme', true ) ) {
			// Load plugins and setup theme
			require_once get_template_directory() . '/plugins/rewrite.php';
			require_once get_template_directory() . '/plugins/wordpresstv-oembed/wordpresstv-oembed.php';
			require_once get_template_directory() . '/plugins/wordpresstv-unisubs/wordpresstv-unisubs.php';
			require_once get_template_directory() . '/plugins/wordpresstv-rest/wordpresstv-rest.php';
			require_once get_template_directory() . '/plugins/wordpresstv-anon-upload/anon-upload.php';
			require_once get_template_directory() . '/plugins/wordpresstv-upload-subtitles/wordpresstv-upload-subtitles.php';
			require_once get_template_directory() . '/plugins/wordpresstv-open-graph/wordpresstv-open-graph.php';
			require_once get_template_directory() . '/plugins/wordpresstv-event-meta/wordpresstv-event-meta.php';

			add_action( 'after_setup_theme', array( $this, 'setup' ) );
		}

		add_action( 'after_setup_theme', array( $this, 'setup_always' ) );
	}

	/**
	 * Runs during after_setup_theme on WordPress.TV
	 */
	function setup() {
		add_action( 'widgets_init', array( $this, 'widgets_init' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ), 0 );
		add_action( 'pre_get_posts', array( $this, 'posts_per_page' ) );
		add_action( 'init', array( $this, 'improve_search' ) );
		add_action( 'publish_post', array( $this, 'publish_post' ), 10, 1 );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
		add_action( 'save_post', array( $this, 'save_meta_box_fields' ), 10, 2);
		add_action( 'wp_footer', array( $this, 'videopress_flash_params' ) );
		add_action( 'transition_post_status', array( $this, 'transition_post_status' ), 10, 2 );

		add_filter( 'pre_option_blog_upload_space', array( $this, 'blog_upload_space' ) );

		add_filter( 'document_title_parts', array( $this, 'document_title_parts' ) );

		register_nav_menus( array(
			'primary'            => __( 'Primary Menu', 'wptv' ),
			'footer'             => __( 'Footer Menu', 'wptv' ),
			'featured_wordcamps' => __( 'Featured WordCamps', 'wptv' ),
		) );
	}

	/**
	 * Runs during after_setup_theme on WordPress.TV & blog.WordPress.TV
	 */
	function setup_always() {
		add_theme_support( 'title-tag' );

		add_action( 'wp_head', array( $this, 'rel_canonical' ) );
		add_action( 'wp_head', array( $this, 'archive_link_rel_prev_next' ) );
	}

	/**
	 * Output `<link rel="canonical">` links where needed.
	 */
	function rel_canonical() {
		$canonical = false;

		if ( is_front_page() ) {
			$canonical = home_url('/');

		// Speakers, Events, Producers, Tags, Years/WordCamp.TV,
		} elseif ( is_tax() || is_tag() || is_category() ) {
			$canonical = get_term_link( get_queried_object() );
			if ( is_wp_error( $canonical ) ) {
				$canonical = false;
			}

		}

		if ( $canonical && get_query_var( 'paged' ) > 1 ) {
			$canonical .= 'page/' . (int) get_query_var( 'paged' ) . '/';
		}

		if ( $canonical ) {
			printf( '<link rel="canonical" href="%s">', esc_url( $canonical ) );
		}
	}

	/**
	 * Filter archive titles where needed.
	 */
	function document_title_parts( $title ) {
		$sep = apply_filters( 'document_title_separator', '-' );

		if ( is_tax( 'language' ) ) {
			$title['title'] = sprintf(
				/* translators: %s: The language name. */
				__( 'Videos in %s', 'wptv' ),
				get_queried_object()->name
			);

		} elseif ( is_tax( 'event' ) ) {
			$title['title'] = sprintf(
				/* translators: %s: The event name. */
				__( 'Videos from %s', 'wptv' ),
				get_queried_object()->name
			);

		} elseif ( is_tax( 'speakers' ) ) {
			$title['title'] = sprintf(
				/* translators: %s: The event name. */
				__( 'Videos of %s', 'wptv' ),
				get_queried_object()->name
			);

		} elseif ( is_category() ) {
			$title['title'] = sprintf(
				/* translators: %s: Category name. */
				__( '%s Videos', 'wptv' ),
				get_queried_object()->name
			);

		} elseif ( is_tag() ) {
			$title['title'] = sprintf(
				/* translators: %s: Tag name. */
				__( 'Videos tagged %s', 'wptv' ),
				get_queried_object()->name
			);

		}

		return $title;
	}

	/**
	 * Outputs <link rel="prev|next"> tags for archives.
	 * @global Pagination $paged
	 * @global WP_Query $wp_query
	 */
	function archive_link_rel_prev_next() {
		global $paged, $wp_query;
		if ( ! is_archive() && ! is_search() ) {
			return;
		}

		$max_page = $wp_query->max_num_pages;
		if ( ! $paged ) {
			$paged = 1;
		}

		$nextpage = intval( $paged ) + 1;
		$prevpage = intval( $paged ) - 1;

		if ( $prevpage >= 1 ) {
			printf(
				'<link rel="prev" href="%s">' . "\n",
				esc_url( get_pagenum_link( $prevpage ) )
			);
		}

		if ( $nextpage <= $max_page ) {
			printf(
				'<link rel="next" href="%s">' . "\n",
				esc_url( get_pagenum_link( $nextpage ) )
			);
		}
	}

	/**
	 * Different posts_per_page settings for different views. Runs during pre_get_posts.
	 *
	 * @param WP_Query $query The WP_Query instance.
	 */
	function posts_per_page( $query ) {
		$posts_per_page = $query->get( 'posts_per_page' );
		if ( ! $query->is_main_query() || ! empty( $posts_per_page ) ) {
			return;
		}

		$queried_object = $query->get_queried_object();

		if ( $query->is_front_page() ) { // category archives
			$query->set( 'posts_per_page', 8 );
		} elseif ( $query->is_category ) { // category archives
			$query->set( 'posts_per_page', 22 );
		} elseif ( $query->is_tax && $queried_object->taxonomy == 'event' ) { // event taxonomy
			$query->set( 'posts_per_page', 22 );
		} elseif ( $query->is_archive || $query->is_search ) {
			$query->set( 'posts_per_page', 10 );
		} else {
			$query->set( 'posts_per_page', 22 );
		}
	}

	/**
	 * Registers taxonomies, runs during init
	 */
	function register_taxonomies() {
		register_taxonomy( 'producer', array( 'post' ), array(
			'label'    => __( 'Producer', 'wptv' ),
			'template' => __( 'Producer: %l.', 'wptv' ),
			'helps'    => __( 'Separate producers with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'producer' ),
		) );

		register_taxonomy( 'producer-username', array( 'post' ), array(
			'label'    => __( 'Producer Username', 'wptv' ),
			'template' => __( 'Producer: %l.', 'wptv' ),
			'helps'    => __( 'Separate producer usernames with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'producer-username' ),
		) );

		register_taxonomy( 'speakers', array( 'post' ), array(
			'label'    => __( 'Speakers', 'wptv' ),
			'template' => __( 'Speakers: %l.', 'wptv' ),
			'helps'    => __( 'Separate speakers with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'speakers' ),
		) );

		register_taxonomy( 'flavor', array( 'post' ), array(
			'label'    => __( 'Flavor', 'wptv' ),
			'template' => __( 'Flavor: %l.', 'wptv' ),
			'helps'    => __( 'Separate flavors with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'flavor' ),
		) );

		register_taxonomy( 'language', array( 'post' ), array(
			'label'    => __( 'Language', 'wptv' ),
			'template' => __( 'Language: %l.', 'wptv' ),
			'helps'    => __( 'Separate languages with commas.', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'language' ),
		) );

		register_taxonomy( 'event', array( 'post' ), array(
			'label'    => __( 'Event', 'wptv' ),
			'template' => __( 'Event: %l.', 'wptv' ),
			'helps'    => __( 'Enter event', 'wptv' ),
			'sort'     => true,
			'args'     => array( 'orderby' => 'term_order' ),
			'rewrite'  => array( 'slug' => 'event' ),
		) );
	}

	/**
	 * Runs during widgets_init, adds some sidebars.
	 */
	function widgets_init() {
		register_sidebars( 1 );
		register_sidebar( array(
			'name'         => __( 'WordCampTV Sidebar', 'wptv' ),
			'id'           => 'wordcamptv-sidebar',
			'description'  => __( 'Widgets in this area will be shown on the WordCampTV landing page.', 'wptv' ),
			'before_title' => '<h3>',
			'after_title'  => '</h3>',
		) );
	}

	/**
	 * Filters the blog_upload_space option
	 */
	function blog_upload_space() {
		return 1024 * 1024 * 15; // 15 terabytes
	}

	/**
	 * Runs during publish_post
	 *
	 * Since a lot of queries depend on the wptv_post_views meta
	 * key, make sure that every published post has one.
	 *
	 * @param int $post_id Post ID.
	 */
	function publish_post( $post_id ) {
		if ( ! get_post_meta( $post_id, 'wptv_post_views', true ) ) {
			update_post_meta( $post_id, 'wptv_post_views', 0 );
		}
	}

	/**
	 * Register meta boxes
	 */
	function add_meta_boxes() {
		add_meta_box( 'video-info', 'Video Info', array( $this, 'render_video_info_metabox' ), 'post', 'normal', 'high' );
	}

	/**
	 * Render the Video Info box
	 *
	 * @global WP_Post $post
	 */
	function render_video_info_metabox() {
		global $post;

		$slides_url = get_post_meta( $post->ID, '_wptv_slides_url', true );
		wp_nonce_field( 'edit-video-info', 'video_info_metabox_nonce' );

		?>

		<p>
			<label for="wptv-slides-url">Slides URL</label>
			<input type="text" class="widefat" id="wptv-slides-url" name="_wptv_slides_url" value="<?php echo esc_url( $slides_url ); ?>" />
		</p>

		<?php
	}

	/**
	 * Save the values of meta box fields
	 *
	 * @param int     $post_id
	 * @param WP_Post $post
	 */
	function save_meta_box_fields( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || defined( 'DOING_AUTOSAVE' ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['video_info_metabox_nonce'] ) || ! wp_verify_nonce( $_POST['video_info_metabox_nonce'], 'edit-video-info' ) ) {
			return;
		}

		$slides_url = esc_url_raw( $_POST['_wptv_slides_url'] );

		if ( $slides_url ) {
			update_post_meta( $post_id, '_wptv_slides_url', $slides_url );
		} else {
			delete_post_meta( $post_id, '_wptv_slides_url' );
		}
	}

	/**
	 * Activates the improved search, but not in admin.
	 */
	function improve_search() {
		if ( is_admin() ) {
			return;
		}

		add_filter( 'posts_search', array( $this, 'search_posts_search' ), 10, 2 );
		add_action( 'pre_get_posts', array( $this, 'search_pre_get_posts' ) );
	}

	/**
	 * Sort the search results by the number of views each post has
	 *
	 * @param WP_Query $query
	 */
	function search_pre_get_posts( $query ) {
		/*
		 * @todo Optimize this before re-enabling
		 *
		 * This method was disabled because it caused 504 errors on large result sets
		 * (e.g., https://wordpress.tv/?s=keynote). Sorting by a meta value is not performant.
		 *
		 * Maybe look at ways to do the sorting in PHP, or just use Elasticsearch instead.
		 */
		return;

		if ( ! $query->is_main_query() || ! $query->is_search ) {
			return;
		}

		// Set custom sorting
		$query->set( 'meta_key', 'wptv_post_views' );
		$query->set( 'orderby', 'meta_value_num' );
		$query->set( 'order', 'DESC' );
	}

	/**
	 * Improved Search: posts_search filter
	 *
	 * Recreates the search SQL by including a taxonomy search.
	 * Relies on various other filters used once.
	 * @todo optimize the get_tax_query part.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string   $search
	 * @param WP_Query $query
	 * @return string
	 */
	function search_posts_search( $search, $query ) {
		global $wpdb;
		if ( ! $query->is_main_query() || ! $query->is_search || is_admin() ) {
			return $search;
		}

		// Get the tax query and replace the leading AND with an OR
		$tax_query = get_tax_sql( $this->get_tax_query( get_query_var( 's' ) ), $wpdb->posts, 'ID' );
		if ( 'and' == substr( trim( strtolower( $tax_query['where'] ) ), 0, 3 ) ) {
			$tax_query['where'] = ' OR ' . substr( trim( $tax_query['where'] ), 3 );
		}

		// Mostly taken from query.php
		if ( isset( $query->query_vars['search_terms'] ) ) {
			$search = $searchand = '';
			$n      = empty( $query->query_vars['exact'] ) ? '%' : '';

			foreach ( (array) $query->query_vars['search_terms'] as $term ) {
				$term = esc_sql( $wpdb->esc_like( $term ) );
				$search .= "{$searchand}(($wpdb->posts.post_title LIKE '{$n}{$term}{$n}') OR ($wpdb->posts.post_content LIKE '{$n}{$term}{$n}'))";
				$searchand = ' AND ';
			}

			// Combine the search and tax queries.
			if ( ! empty( $search ) ) {
				// Add the tax search to the query
				if ( ! empty( $tax_query['where'] ) ) {
					$search .= $tax_query['where'];
				}

				$search = " AND ({$search}) ";
				if ( ! is_user_logged_in() ) {
					$search .= " AND ($wpdb->posts.post_password = '') ";
				}
			}
		}

		// These are single-use filters, they delete themselves right after they're used.
		add_filter( 'posts_join', array( $this, 'search_posts_join' ), 10, 2 );
		add_filter( 'posts_groupby', array( $this, 'search_posts_groupby' ), 10, 2 );

		return $search;
	}

	/**
	 * Improved Search: posts_join filter
	 *
	 * This adds the JOIN clause resulting from the taxonomy
	 * search. Make sure this filter runs only once per WP_Query request.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string   $join
	 * @param WP_Query $query
	 * @return string
	 */
	function search_posts_join( $join, &$query ) {
		// Make sure this filter doesn't run again.
		remove_filter( 'posts_join', array( $this, 'search_posts_join' ), 10, 2 );

		if ( $query->is_main_query() ) {
			global $wpdb;
			$tax_query = get_tax_sql( $this->get_tax_query( get_query_var( 's' ) ), $wpdb->posts, 'ID' );
			$join .= $tax_query['join'];
		}

		return $join;
	}

	/**
	 * Improved Search: posts_groupby filter
	 *
	 * Searching with taxonomies may include duplicates when
	 * search query matches content and one or more taxonomies.
	 * This filter glues all duplicates. Use only once per WP_Query.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @param string   $group_by
	 * @param WP_Query $query
	 * @return string
	 */
	function search_posts_groupby( $group_by, &$query ) {
		// Never run this again
		remove_filter( 'posts_groupby', array( $this, 'search_posts_groupby' ), 10, 2 );

		global $wpdb;
		$group_by = "$wpdb->posts.ID";

		return $group_by;
	}

	/**
	 * Returns a $tax_query array for an improved search.
	 *
	 * @param string $search
	 * @return array
	 */
	function get_tax_query( $search ) {
		$taxonomies = array(
			'producer',
			'speakers', /*'flavor', 'language',*/
			'event'
		);

		$terms    = get_terms( $taxonomies, array(
			'search' => $search,
		) );
		$term_ids = wp_list_pluck( $terms, 'term_id' );

		$tax_query = array();
		foreach ( $taxonomies as $taxonomy ) {
			$tax_query[] = array(
				'taxonomy' => $taxonomy,
				'terms'    => $term_ids,
			);
		}
		$tax_query['relation'] = 'OR';

		return $tax_query;
	}

	/**
	 * Change VideoPress Params, runs during wp_footer
	 */
	function videopress_flash_params() {
		echo '<script type="text/javascript">if(jQuery.VideoPress){jQuery.VideoPress.video.flash.params.wmode="opaque";}</script>';
	}

	/**
	 * List Comments Callback
	 *
	 * Used with wp_list_comments in the theme files,
	 * fired via the wptv_list_comments callback wrapper.
	 *
	 * @param object $comment
	 * @param array  $args
	 * @param int    $depth
	 */
	function list_comments( $comment, $args, $depth ) {
		$GLOBALS['comment'] = $comment;
		if ( $comment->comment_type == 'pingback' ) {
			return;
		}
		?>
		<li <?php comment_class(); ?> id="comment-<?php comment_ID(); ?>">
			<cite class="authorinfo">
				<?php echo get_avatar( $comment, 24 ); ?>
				<?php comment_author_link(); ?>
			</cite>

			<br/>

			<?php if ( $comment->comment_type != 'pingback' ) : ?>

				<small class="commentmetadata">
					<a href="#comment-<?php comment_ID() ?>" title=""><?php printf( __( '%1$s at %2$s', 'wptv' ), get_comment_date(), get_comment_time() ); ?></a>
					<?php
						edit_comment_link( __( 'edit', 'wptv' ), '&nbsp;&nbsp;', '' );
						echo comment_reply_link( array(
							'depth'     => $depth,
							'max_depth' => $args['max_depth'],
							'before'    => ' | ',
						) );
					?>
				</small>

			<?php endif; // comment_type != 'pingback' ?>

			<div class="commenttext">
				<?php if ( $comment->comment_approved == '0' ) : ?>
					<em><?php _e( 'Your comment is awaiting moderation.', 'wptv' ); ?></em>
				<?php endif; // comment_approved == 0 ?>

				<?php comment_text(); ?>
			</div>
			<div class="clear"></div>
		</li>
	<?php
	}

	/**
	 * Renders the video or a video thumbnail
	 *
	 * @global WP_Post $post
	 * @global string  $originalcontent
	 */
	function the_video() {
		$video = '';
		global $post, $originalcontent;
		$originalcontent = $post->post_content;

		remove_filter( 'the_content', array( $this, 'remove_shortcodes' ) );

		// VideoPress
		preg_match_all( '/' . get_shortcode_regex() . '/s', $post->post_content, $shortcodes, PREG_SET_ORDER );
		foreach ( $shortcodes as $shortcode ) {
			if ( 'wpvideo' == $shortcode[2] ) {
				$attributes = shortcode_parse_atts( $shortcode[0] );
				$hd_param   = '';

				// Only set HD param is it won't already be set from `$shortcode[3]`.
				if ( ! array_key_exists( 'hd', $attributes ) ) {
					$hd_param = sprintf( 'hd="%d"', (bool) get_option( 'video_player_high_quality', false ) );
				}

				$video      = sprintf( '[%s %s w="%s" %s]', $shortcode[2], trim( $shortcode[3] ), is_single() ? '940' : '575', $hd_param );
				$video      = apply_filters( 'the_content', $video );
			}
		}

		echo $video;

		add_filter( 'the_content', array( $this, 'remove_shortcodes' ) );
	}

	/**
	 * Outputs the video image
	 *
	 * @global WP_Post $post
	 *
	 * @param int  $h
	 * @param int  $w
	 * @param bool $arrow
	 * @param bool $html_code
	 */
	function the_video_image( $h = 196, $w = 400, $arrow = true, $html_code = true ) {
		$ret = '';
		global $post;
		remove_filter( 'the_content', array( $this, 'remove_shortcodes' ) );

		preg_match_all( '/\[wpvideo +([a-zA-Z0-9,\#,\&,\/,;,",=, ]*?)\]/i', $post->post_content, $matches );
		foreach ( $matches[1] as $key => $code ) {
			preg_match( '/([0-9A-Za-z]+)/i', $code, $m );
			$guid = $m[1];
			$ret = video_image_url_by_guid( $guid, 'fmt_dvd' );
		}

		if ( $arrow ) {
			?><a href="<?php the_permalink() ?>" class="showarrow arrow"><?php the_title(); ?></a><?php
		}
		if ( $html_code ) {
			$ret = '<img src="' . $ret . '" alt="' . esc_attr( $post->post_title ) . '" />';
		}
		echo $ret;

		add_filter( 'the_content', array( $this, 'remove_shortcodes' ) );
	}

	/**
	 * Removes shortcodes from $originalcontent global
	 *
	 * @global string $originalcontent
	 *
	 * @param string $content
	 * @return mixed
	 */
	function remove_shortcodes( $content ) {
		global $originalcontent;

		return preg_replace( '/\[wpvideo +([a-zA-Z0-9,\#,\&,\/,;,",=, ]*?)\]/i', '', $originalcontent );
	}

	/**
	 * Returns the home URL
	 *
	 * @param string $path
	 * @return mixed|void
	 */
	public function home_url( $path = '' ) {
		return apply_filters( 'wptv_home_url', home_url( $path ), $path );
	}

	/**
	 * Prints a single category with custom priorities.
	 *
	 * @param string $before
	 */
	public function the_category( $before = '' ) {
		foreach ( array( 'wordcamptv', 'how-to' ) as $category_slug ) {
			$category = get_category_by_slug( $category_slug );

			if ( in_category( $category ) ) {
				$link = get_category_link( $category );
				echo $before . ' <a href="' . esc_url( $link ) . '">' . esc_html( $category->name ) . '</a>';
				break; // only one category is printed
			}
		}
	}

	/**
	 * Prints a single event.
	 *
	 * @param string $before
	 * @param string $after
	 */
	public function the_event( $before = '', $after = '' ) {
		$terms = get_the_terms( get_post()->ID, 'event' );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return;
		}

		foreach ( $terms as $term ) {
			$link = get_term_link( $term, 'event' );
			echo $before . '<a href="' . esc_url( $link ) . '">' . esc_html( $term->name ) . '</a>' . $after;
			break; // only the first one event is printed
		}
	}

	/**
	 * Display the Terms
	 *
	 * Give this a taxonomy
	 *
	 * @param string $taxonomy
	 * @param string $before
	 * @param string $sep
	 * @param string $after
	 * @param bool   $display_count
	 * @return bool|void False if there are no terms.
	 */
	public function the_terms( $taxonomy = 'post_tag', $before = '', $sep = '', $after = '', $display_count = true ) {
		$terms = get_the_terms( get_post()->ID, $taxonomy );
		if ( ! $terms || is_wp_error( $terms ) ) {
			return false;
		}

		$links = array();
		foreach ( $terms as $term ) {
			$count   = $display_count ? ' <span class="tag-count">' . absint( $term->count ) . '</span>' : '';
			$links[] = '<a href="' . esc_url( get_term_link( $term, $taxonomy ) ) . '">' . esc_html( $term->name ) . $count . '</a>';
		}
		echo $before . join( $sep, $links ) . $after;
	}

	/**
	 * Runs during transition_post_status, bumps some stats.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 */
	function transition_post_status( $new_status, $old_status ) {
		if ( 'publish' != $new_status || 'publish' == $old_status ) {
			return;
		}

		bump_stats_extras( 'wptv-activity', 'publish-video' );
	}
}

global $wptv;
$wptv = new WordPressTV_Theme;

/**
 * WordCampTV wp_nav_menu Walker Class
 *
 * Use this class with wp_nav_menu to output an event
 * together with some videos from the event.
 */
class WordCampTV_Walker_Nav_Menu extends Walker {

	/**
	 * @see Walker
	 */
	var $tree_type = array( 'post_type', 'taxonomy', 'custom' );
	var $db_fields = array( 'parent' => 'menu_item_parent', 'id' => 'db_id' );

	/**
	 * @see Walker::start_el()
	 *
	 * If an item is an event, print the event heading,
	 * followed by a WP_Query that loops through some of the
	 * videos in the event. start_el does all the work and does not need end_el.
	 *
	 * @global WordPressTV_Theme $wptv
	 *
	 * @param string $output
	 * @param object $item
	 * @param int    $depth
	 * @param array  $args
	 * @param int    $id
	 */
	function start_el( &$output, $item, $depth = 0, $args = array(), $id = 0 ) {
		global $wptv;

		// Skip if it's not an event item.
		if ( $item->object != 'event' || $item->type != 'taxonomy' ) {
			return;
		}

		// Use this query to fetch event videos.
		$query = new WP_Query( array(
			'posts_per_page' => 4,
			'tax_query'      => array(
				array(
					'taxonomy' => 'event',
					'field'    => 'id',
					'terms'    => $item->object_id,
				),
			),
		) );

		ob_start();
		?>
		<div>
			<h3>
				<?php echo apply_filters( 'the_title', $item->title ); ?>
				<a href="<?php echo esc_url( $item->url ); ?>" class="view-more"><?php esc_html_e( 'More &rarr;' ); ?></a>
			</h3>
			<ul class="video-list four-col">
				<?php while ( $query->have_posts() ) : $query->the_post(); ?>
				<li>
					<a href="<?php the_permalink(); ?>">
						<span class="video-thumbnail"><?php $wptv->the_video_image( 50, null, false ); ?></span>
						<span class="video-title"><?php the_title(); ?></span>
					</a>
				</li>
				<?php endwhile; ?>
			</ul>
		</div>
		<?php
		$output .= ob_get_contents();
		ob_end_clean();
	}
}


function wptv_enqueue_scripts() {
	wp_enqueue_style( 'wptv-style', get_stylesheet_uri() . '?s' );

	// Load the Internet Explorer specific stylesheet.
	wp_enqueue_style( 'wptv-ie', get_template_directory_uri() . '/ie6.css', array( 'wptv-style' ) );
	wp_style_add_data( 'wptv-ie', 'conditional', 'IE 6' );

	if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
		wp_enqueue_script( 'comment-reply' );
	}
}
add_action( 'wp_enqueue_scripts', 'wptv_enqueue_scripts' );

/**
 * Append the slide URL to the excerpt
 *
 * @param string $excerpt
 * @return string
 */
function wptv_excerpt_slides( $excerpt ) {
	$slides = get_post_meta( get_the_ID(), '_wptv_slides_url', true );

	if ( ! empty( $slides ) ) {
		$excerpt .= '<p><a href="' . esc_url( $slides ) . '">Presentation Slides &raquo;</a></p>';
	}

	return $excerpt;
}
add_filter( 'get_the_excerpt', 'wptv_excerpt_slides' );

/**
 * Checks if the given username exists on WordPress.org
 *
 * grav-redirect.php will redirect to a Gravatar image URL. If the WordPress.org username exists, the `d` parameter
 * will be 'retro', and if it doesn't it'll be 'mm'.
 *
 * @param string $username
 * @return bool
 */
function wporg_username_exists( $username ) {
	$username_exists = false;
	$validator_url   = add_query_arg( 'user', $username, 'https://wordpress.org/grav-redirect.php' );
	$response        = wp_remote_retrieve_headers( wp_remote_get( $validator_url, array( 'redirection' => 0 ) ) );

	if ( !empty( $response['location'] ) ) {
		if ( false === strpos( $response['location'], 'd=mm' ) ) {
			$username_exists = true;
		}
	}

	return $username_exists;
}

if ( ! function_exists( 'bump_stats_extras' ) ) {
	/**
	 * Define a stub for `bump_stats_extras()`
	 *
	 * This function only exists on WordPress.com, so we need a stub to prevent fatal `undefined function` errors
	 * in the Meta Environment and other local dev environments.
	 *
	 * @param string $name
	 * @param string $value
	 * @param int    $num
	 * @param bool   $today
	 * @param bool   $hour
	 */
	function bump_stats_extras( $name, $value, $num = 1, $today = false, $hour = false ) {
		// This is intentionally empty
	}
}

/**
 * Eliminates widows in strings by replace the breaking space that appears before the last word
 * with a non-breaking space.
 *
 * @link https://www.shauninman.com/post/heap/2006/08/22/widont_wordpress_plugin Typesetting widows
 *
 * @param string $str Optional. String to operate on.
 * @return string
 */
function wptv_widont( $str = '' ) {
	// Don't apply on non-tablet mobile devices so the browsers can fit to the viewport properly.
	if (
		function_exists( 'jetpack_is_mobile' ) && jetpack_is_mobile() &&
		class_exists( 'Jetpack_User_Agent_Info' ) && ! Jetpack_User_Agent_Info::is_tablet()
	) {
		return $str;
	}

	// We're dealing with whitespace from here out, let's not have any false positives. :)
	$str = trim( $str );

	// If string contains three or fewer words, don't join.
	if ( count( preg_split( '#\s+#', $str ) ) <= 3 ) {
		return $str;
	}

	// Don't join if words exceed a certain length: minimum 5 characters, default 15 characters, filterable via `widont_max_word_length`.
	$widont_max_word_length = max( 8, absint( apply_filters( 'widont_max_word_length', 8 ) ) );
	$regex = '#\s+([^\s]{1,' . $widont_max_word_length . '})\s+([^\s]{1,' . $widont_max_word_length . '})$#';

	return preg_replace( $regex, ' $1&nbsp;$2', $str );
}
remove_filter( 'the_title', 'widont' );
add_filter( 'the_title', 'wptv_widont' );

/**
 * Update Attachment titles to match the Post Title.
 *
 * This is done as the Attachment Title is shown within the VideoPress embeds.
 * This can't be done through a filter in the themes functions.php, as it appears that's not loaded for the Rest API.
 *
 * @see https://meta.trac.wordpress.org/ticket/4667
 */
function wptv_update_attachment_titles( $id, $post, $post_before ) {
	if ( 'post' !== $post->post_type ) {
		return;
	}

	// Get attachments - Should only be one?
	$attachments = get_posts( array(
		'post_type'   => 'attachment',
		'post_parent' => $id,
	) );
	if ( ! $attachments || count( $attachments ) > 1 ) {
		return;
	}
	$attachment = $attachments[0];

	if ( $post->post_title === $attachment->post_title ) {
		// Titles match, nothing needs updating.
		return;
	} elseif ( sanitize_file_name( $attachment->post_title ) === $attachment->post_title ) {
		// Default title, Update it.
	} elseif ( $attachment->post_title === $post_before->post_title ) {
		// Set to the same as the post, update to the new title.
	} else {
		// It's set to something that isn't the current, previous, or default titles.
		// Assume that the title has been set manually and bail.
		return;
	}

	// Update attachment to share the post title.
	// The attachment title appears in VideoPress thumbnails.
	$attachment->post_title = $post->post_title;
	wp_update_post( $attachment );
}
add_action( 'post_updated', 'wptv_update_attachment_titles', 10, 3 );
