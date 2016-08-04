<?php
/**
 * Plugin Name: WordCamp.org Post Types
 * Plugin Description: Sessions, Speakers, Sponsors and much more.
 */

require( 'inc/back-compat.php' );

class WordCamp_Post_Types_Plugin {
	protected $wcpt_permalinks;

	/**
	 * Fired when plugin file is loaded.
	 */
	function __construct() {
		$this->wcpt_permalinks = array();

		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'init', array( $this, 'register_taxonomies' ) );
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'after_theme_setup', array( $this, 'add_image_sizes' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );
		add_action( 'admin_print_styles', array( $this, 'admin_css' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_print_footer_scripts', array( $this, 'admin_print_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'wp_enqueue_scripts' ) );

		add_action( 'save_post', array( $this, 'save_post_speaker' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post_session' ), 10, 2 );
		add_action( 'save_post', array( $this, 'save_post_organizer' ), 10, 2);
		add_action( 'save_post', array( $this, 'save_post_sponsor' ), 10, 2);

		add_filter( 'manage_wcb_speaker_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_wcb_session_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_wcb_sponsor_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_wcb_organizer_posts_columns', array( $this, 'manage_post_types_columns' ) );
		add_filter( 'manage_edit-wcb_session_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		add_filter( 'display_post_states', array( $this, 'display_post_states' ) );

		add_action( 'manage_posts_custom_column', array( $this, 'manage_post_types_columns_output' ), 10, 2 );

		add_action( 'widgets_init', array( $this, 'register_widgets' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );

		add_shortcode( 'speakers', array( $this, 'shortcode_speakers' ) );
		add_shortcode( 'sessions', array( $this, 'shortcode_sessions' ) );
		add_shortcode( 'sponsors', array( $this, 'shortcode_sponsors' ) );
		add_shortcode( 'organizers', array( $this, 'shortcode_organizers' ) );
		add_shortcode( 'schedule', array( $this, 'shortcode_schedule' ) );

		add_filter( 'the_content', array( $this, 'add_avatar_to_speaker_posts' ) );
		add_filter( 'the_content', array( $this, 'add_speaker_info_to_session_posts' ) );
		add_filter( 'the_content', array( $this, 'add_slides_info_to_session_posts' ) );
		add_filter( 'the_content', array( $this, 'add_video_info_to_session_posts' ) );
		add_filter( 'the_content', array( $this, 'add_session_info_to_speaker_posts' ) );

		add_filter( 'dashboard_glance_items', array( $this, 'glance_items' ) );
		add_filter( 'option_default_comment_status', array( $this, 'default_comment_ping_status' ) );
		add_filter( 'option_default_ping_status', array( $this, 'default_comment_ping_status' ) );
	}

	function init() {
		do_action( 'wcpt_back_compat_init' );
	}

	/**
	 * Runs during admin_init.
	 */
	function admin_init() {
		register_setting( 'wcb_sponsor_options', 'wcb_sponsor_level_order', array( $this, 'validate_sponsor_options' ) );
		add_action( 'pre_get_posts', array( $this, 'admin_pre_get_posts' ) );
	}

	/**
	 * Runs during admin_menu
	 */
	function admin_menu() {
		$page = add_submenu_page( 'edit.php?post_type=wcb_sponsor', __( 'Order Sponsor Levels', 'wordcamporg' ), __( 'Order Sponsor Levels', 'wordcamporg' ), 'edit_posts', 'sponsor_levels', array( $this, 'render_order_sponsor_levels' ) );

		add_action( "admin_print_scripts-$page", array( $this, 'enqueue_order_sponsor_levels_scripts' ) );
	}

	/**
	 * Add custom image sizes
	 */
	function add_image_sizes() {
		add_image_size( 'wcb-sponsor-logo-horizontal-2x', 600, 220, false );
	}

	/**
	 * Enqueues scripts and styles for the render_order_sponsors_level admin page.
	 */
	function enqueue_order_sponsor_levels_scripts() {
		wp_enqueue_script( 'wcb-sponsor-order', plugins_url( '/js/order-sponsor-levels.js', __FILE__ ), array( 'jquery-ui-sortable' ), '20110212' );
		wp_enqueue_style( 'wcb-sponsor-order', plugins_url( '/css/order-sponsor-levels.css', __FILE__ ), array(), '20110212' );
	}

	/**
	 * Renders the Order Sponsor Levels admin page.
	 */
	function render_order_sponsor_levels() {
		if ( ! isset( $_REQUEST['updated'] ) )
			$_REQUEST['updated'] = false;

		$levels = $this->get_sponsor_levels();
		?>
		<div class="wrap">
			<?php screen_icon(); ?>
			<h1><?php _e( 'Order Sponsor Levels', 'wordcamporg' ); ?></h1>

			<?php if ( false !== $_REQUEST['updated'] ) : ?>
				<div class="updated fade"><p><strong><?php _e( 'Options saved', 'wordcamporg' ); ?></strong></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcb_sponsor_options' ); ?>
				<div class="description sponsor-order-instructions">
					<?php _e( 'Change the order of sponsor levels are displayed in the sponsors page template.', 'wordcamporg' ); ?>
				</div>
				<ul class="sponsor-order">
				<?php foreach( $levels as $term ): ?>
					<li class="level">
						<input type="hidden" class="level-id" name="wcb_sponsor_level_order[]" value="<?php echo esc_attr( $term->term_id ); ?>" />
						<?php echo esc_html( $term->name ); ?>
					</li>
				<?php endforeach; ?>
				</ul>
				<p class="submit">
					<input type="submit" class="button-primary" value="<?php _e( 'Save Options', 'wordcamporg' ); ?>" />
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Runs when settings are updated for the sponsor level order page.
	 */
	function validate_sponsor_options( $input ) {
		if ( ! is_array( $input ) ) {
			$input = null;
		} else {
			foreach ( $input as $key => $value ) {
				$input[ $key ] = (int) $input[ $key ];
			}
			$input = array_values( $input );
		}

		return $input;
	}

	/**
	 * Returns the sponsor level terms in set order.
	 */
	function get_sponsor_levels() {
		$option         = get_option( 'wcb_sponsor_level_order' );
		$term_objects   = get_terms( 'wcb_sponsor_level', array( 'get' => 'all' ) );
		$terms          = array();
		$ordered_terms  = array();

		foreach ( $term_objects as $term ) {
			$terms[ $term->term_id ] = $term;
		}

		if ( empty( $option ) )
			$option = array();

		foreach ( $option as $term_id ) {
			if ( isset( $terms[ $term_id ] ) ) {
				$ordered_terms[] = $terms[ $term_id ];
				unset( $terms[ $term_id ] );
			}
		}

		return array_merge( $ordered_terms, array_values( $terms ) );
	}

	/**
	 * Runs during pre_get_posts in admin.
	 *
	 * @param WP_Query $query
	 */
	function admin_pre_get_posts( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() )
			return;

		$current_screen = get_current_screen();

		// Order by session time
		if ( 'edit-wcb_session' == $current_screen->id && $query->get( 'orderby' ) == '_wcpt_session_time' ) {
			$query->set( 'meta_key', '_wcpt_session_time' );
			$query->set( 'orderby', 'meta_value_num' );
		}
	}

	function admin_enqueue_scripts() {
		global $post_type;

		// Enqueues scripts and styles for session admin page
		if ( 'wcb_session' == $post_type ) {
			wp_enqueue_script( 'jquery-ui-datepicker' );
			wp_enqueue_style( 'jquery-ui' );
			wp_enqueue_style( 'wp-datepicker-skins' );
		}
	}

	/*
	 * Print our JavaScript
	 */
	function admin_print_scripts() {
		global $post_type;

		// DatePicker for Session posts
		if ( 'wcb_session' == $post_type ) :
			?>

			<script type="text/javascript">
				jQuery( document ).ready( function( $ ) {
					$( '#wcpt-session-date' ).datepicker( {
						dateFormat:  'yy-mm-dd',
						changeMonth: true,
						changeYear:  true
					} );
				} );
			</script>

			<?php
		endif;
	}

	function wp_enqueue_scripts() {
		wp_enqueue_style( 'wcb_shortcodes', plugins_url( 'css/shortcodes.css', __FILE__ ), array(), 2 );
	}

	/**
	 * Runs during admin_print_styles, does some CSS things.
	 *
	 * @todo add an icon for wcb_organizer
	 * @uses get_current_screen()
	 * @uses wp_enqueue_style()
	 */
	function admin_css() {
		$screen = get_current_screen();

		switch ( $screen->id ) {
			case 'edit-wcb_organizer':
			case 'edit-wcb_speaker':
			case 'edit-wcb_sponsor':
			case 'edit-wcb_session':
			case 'wcb_sponsor':
			case 'dashboard':
				wp_enqueue_style( 'wcpt-admin', plugins_url( '/css/admin.css', __FILE__ ), array(), 1 );
				break;
			default:
		}
	}

	/**
	 * The [speakers] shortcode handler.
	 */
	function shortcode_speakers( $attr, $content ) {
		global $post;

		// Prepare the shortcode arguments
		$attr = shortcode_atts( array(
			'show_avatars'   => true,
			'avatar_size'    => 100,
			'posts_per_page' => -1,
			'orderby'        => 'date',
			'order'          => 'desc',
			'track'          => 'all',
			'speaker_link'   => '',
		), $attr );

		foreach ( array( 'orderby', 'order', 'track', 'speaker_link' ) as $key_for_case_sensitive_value ) {
			$attr[ $key_for_case_sensitive_value ] = strtolower( $attr[ $key_for_case_sensitive_value ] );
		}

		$attr['show_avatars'] = $this->str_to_bool( $attr['show_avatars'] );
		$attr['orderby']      = in_array( $attr['orderby'],      array( 'date', 'title', 'rand' ) ) ? $attr['orderby']      : 'date';
		$attr['order']        = in_array( $attr['order'],        array( 'asc', 'desc'           ) ) ? $attr['order']        : 'desc';
		$attr['speaker_link'] = in_array( $attr['speaker_link'], array( 'permalink'             ) ) ? $attr['speaker_link'] : '';

		/*
		 * Only allow 2014.capetown to use the new track attribute
		 * @todo Remove this and update docs after stakeholder review
		 */
		if ( ! in_array( get_current_blog_id(), apply_filters( 'wcpt_filter_speakers_by_track_allowed_sites', array( 423 ) ) ) ) {
			$attr['track'] = 'all';
		}

		// Fetch all the relevant sessions
		$session_args = array(
			'post_type'      => 'wcb_session',
			'posts_per_page' => -1,
		);

		if ( 'all' != $attr['track'] ) {
			$session_args['tax_query'] = array(
				array(
					'taxonomy' => 'wcb_track',
					'field'    => 'slug',
					'terms'    => explode( ',', $attr['track'] ),
				),
			);
		}

		$sessions = get_posts( $session_args );

		// Parse the sessions
		$speaker_ids = $speakers_tracks = array();
		foreach ( $sessions as $session ) {
			// Get the speaker IDs for all the sessions in the requested tracks
			$session_speaker_ids = get_post_meta( $session->ID, '_wcpt_speaker_id' );
			$speaker_ids = array_merge( $speaker_ids, $session_speaker_ids );

			// Map speaker IDs to their corresponding tracks
			$session_terms = wp_get_object_terms( $session->ID, 'wcb_track' );
			foreach ( $session_speaker_ids as $speaker_id ) {
				if ( isset ( $speakers_tracks[ $speaker_id ] ) ) {
					$speakers_tracks[ $speaker_id ] = array_merge( $speakers_tracks[ $speaker_id ], wp_list_pluck( $session_terms, 'slug' ) );
				} else {
					$speakers_tracks[ $speaker_id ] = wp_list_pluck( $session_terms, 'slug' );
				}
			}
		}

		// Remove duplicate entries
		$speaker_ids = array_unique( $speaker_ids );
		foreach ( $speakers_tracks as $speaker_id => $tracks ) {
			$speakers_tracks[ $speaker_id ] = array_unique( $tracks );
		}

		// Fetch all specified speakers
		$speaker_args = array(
			'post_type'      => 'wcb_speaker',
			'posts_per_page' => intval( $attr['posts_per_page'] ),
			'orderby'        => $attr['orderby'],
			'order'          => $attr['order'],
		);

		if ( 'all' != $attr['track'] ) {
			$speaker_args['post__in'] = $speaker_ids;
		}

		$speakers = new WP_Query( $speaker_args );

		if ( ! $speakers->have_posts() )
			return '';

		// Render the HTML for the shortcode
		ob_start();
		?>

		<div class="wcorg-speakers">

			<?php while ( $speakers->have_posts() ) : $speakers->the_post(); ?>

				<?php
					$speaker_classes = array( 'wcorg-speaker', 'wcorg-speaker-' . sanitize_html_class( $post->post_name ) );

					if ( isset( $speakers_tracks[ get_the_ID() ] ) ) {
						foreach ( $speakers_tracks[ get_the_ID() ] as $track ) {
							$speaker_classes[] = sanitize_html_class( 'wcorg-track-' . $track );
						}
					}
				?>

				<!-- Organizers note: The id attribute is deprecated and only remains for backwards compatibility, please use the corresponding class to target individual speakers -->
				<div id="wcorg-speaker-<?php echo sanitize_html_class( $post->post_name ); ?>" class="<?php echo implode( ' ', $speaker_classes ); ?>">
					<h2>
						<?php if ( 'permalink' === $attr['speaker_link'] ) : ?>
							<a href="<?php the_permalink(); ?>">
								<?php the_title(); ?>
							</a>
						<?php else : ?>
							<?php the_title(); ?>
						<?php endif; ?>
					</h2>
					<div class="wcorg-speaker-description">
						<?php echo ( $attr['show_avatars'] ) ? get_avatar( get_post_meta( get_the_ID(), '_wcb_speaker_email', true ), absint( $attr['avatar_size'] ) ) : ''; ?>
						<?php the_content(); ?>
					</div>
				</div><!-- .wcorg-speaker -->

			<?php endwhile; ?>

		</div><!-- .wcorg-speakers -->

		<?php

		wp_reset_postdata();
		return ob_get_clean();
	}

	/**
	 * The [organizers] shortcode callback.
	 */
	function shortcode_organizers( $attr, $content ) {
		$attr = shortcode_atts( array(
			'show_avatars'   => true,
			'avatar_size'    => 100,
			'posts_per_page' => -1,
			'orderby'        => 'date',
		), $attr );

		$attr['show_avatars'] = $this->str_to_bool( $attr['show_avatars'] );
		$attr['orderby'] = strtolower( $attr['orderby'] );
		$attr['orderby'] = ( in_array( $attr['orderby'], array( 'date', 'title', 'rand' ) ) ) ? $attr['orderby'] : 'date';

		$organizers = new WP_Query( array(
			'post_type' => 'wcb_organizer',
			'posts_per_page' => intval( $attr['posts_per_page'] ),
			'orderby' => $attr['orderby'],
		) );

		if ( ! $organizers->have_posts() )
			return '';

		ob_start();
		?>
		<div class="wcorg-organizers">

			<?php while ( $organizers->have_posts() ) : $organizers->the_post(); ?>

				<div class="wcorg-organizer">
					<h2><?php the_title(); ?></h2>
					<div class="wcorg-organizer-description">
						<?php /* Unlike speakers, organizers don't have a Gravatar e-mail field, so we pass the linked user ID to get_avatar */ ?>
						<?php echo ( $attr['show_avatars'] ) ? get_avatar( absint( get_post_meta( get_the_ID(), '_wcpt_user_id', true ) ), absint( $attr['avatar_size'] ) ) : ''; ?>
						<?php the_content(); ?>
					</div>
				</div>

			<?php endwhile; ?>

		</div><!-- .wcorg-organizers -->
		<?php
		wp_reset_postdata();
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * The [schedule] shortcode callback (experimental)
	 *
	 * @todo implement date arg
	 * @todo implement anchor for session_link
	 * @todo maybe simplify $attr['custom']
	 * @todo cleanup
	 */
	function shortcode_schedule( $attr, $content ) {
		$attr = shortcode_atts( array(
			'date'         => null,
			'tracks'       => 'all',
			'speaker_link' => 'anchor', // anchor|wporg|permalink|none
			'session_link' => 'permalink', // permalink|anchor|none
		), $attr );

		foreach ( array( 'tracks', 'speaker_link', 'session_link' ) as $key_for_case_sensitive_value ) {
			$attr[ $key_for_case_sensitive_value ] = strtolower( $attr[ $key_for_case_sensitive_value ] );
		}

		if ( ! in_array( $attr['speaker_link'], array( 'anchor', 'wporg', 'permalink', 'none' ) ) )
			$attr['speaker_link'] = 'anchor';

		if ( ! in_array( $attr['session_link'], array( 'permalink', 'anchor', 'none' ) ) )
			$attr['session_link'] = 'permalink';

		$columns = array();
		$tracks = array();

		$query_args = array(
			'post_type'      => 'wcb_session',
			'posts_per_page' => -1,
			'meta_query'     => array(
				'relation'   => 'AND',
				array(
					'key'     => '_wcpt_session_time',
					'compare' => 'EXISTS',
				),
			),
		);

		if ( 'all' == $attr['tracks'] ) {
			// Include all tracks.
			$tracks = get_terms( 'wcb_track' );
		} else {
			// Loop through given tracks and look for terms.
			$terms = array_map( 'trim', explode( ',', $attr['tracks'] ) );
			foreach ( $terms as $term_slug ) {
				$term = get_term_by( 'slug', $term_slug, 'wcb_track' );
				if ( $term )
					$tracks[ $term->term_id ] = $term;
			}

			// If tracks were provided, restrict the lookup in WP_Query.
			if ( ! empty( $tracks ) ) {
				$query_args['tax_query'][] = array(
					'taxonomy' => 'wcb_track',
					'field'    => 'id',
					'terms'    => array_values( wp_list_pluck( $tracks, 'term_id' ) ),
				);
			}
		}

		if ( $attr['date'] && strtotime( $attr['date'] ) ) {
			$query_args['meta_query'][] = array(
				'key'   => '_wcpt_session_time',
				'value' => array(
					strtotime( $attr['date'] ),
					strtotime( $attr['date'] . ' +1 day' ),
				),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		// Use tracks to form the columns.
		if ( $tracks ) {
			foreach ( $tracks as $track )
				$columns[ $track->term_id ] = $track->term_id;
		} else {
			$columns[ 0 ] = 0;
		}

		unset( $tracks );

		// Loop through all sessions and assign them into the formatted
		// $sessions array: $sessions[ $time ][ $track ] = $session_id
		// Use 0 as the track ID if no tracks exist

		$sessions = array();
		$sessions_query = new WP_Query( $query_args );
		foreach ( $sessions_query->posts as $session ) {
			$time = absint( get_post_meta( $session->ID, '_wcpt_session_time', true ) );
			$tracks = get_the_terms( $session->ID, 'wcb_track' );

			if ( ! isset( $sessions[ $time ] ) )
				$sessions[ $time ] = array();

			if ( empty( $tracks ) ) {
				$sessions[ $time ][ 0 ] = $session->ID;
			} else {
				foreach ( $tracks as $track )
					$sessions[ $time ][ $track->term_id ] = $session->ID;
			}
		}

		// Sort all sessions by their key (timestamp).
		ksort( $sessions );

		// Remove empty columns unless tracks have been explicitly specified
		if ( 'all' == $attr['tracks'] ) {
			$used_terms = array();

			foreach ( $sessions as $time => $entry )
				if ( is_array( $entry ) )
					foreach ( $entry as $term_id => $session_id )
						$used_terms[ $term_id ] = $term_id;

			$columns = array_intersect( $columns, $used_terms );
			unset( $used_terms );
		}

		$html = '<table class="wcpt-schedule" border="0">';
		$html .= '<thead>';
		$html .= '<tr>';

		// Table headings.
		$html .= '<th class="wcpt-col-time">Time</th>';
		foreach ( $columns as $term_id ) {
			$track = get_term( $term_id, 'wcb_track' );
			$html .= sprintf(
				'<th class="wcpt-col-track"> <span class="wcpt-track-name">%s</span> <span class="wcpt-track-description">%s</span> </th>',
				isset( $track->term_id ) ? esc_html( $track->name ) : '',
				isset( $track->term_id ) ? esc_html( $track->description ) : ''
			);
		}

		$html .= '</tr>';
		$html .= '</thead>';

		$html .= '<tbody>';

		$time_format = get_option( 'time_format', 'g:i a' );

		foreach ( $sessions as $time => $entry ) {

			$skip_next = $colspan = 0;

			$columns_html = '';
			foreach ( $columns as $key => $term_id ) {

				// Allow the below to skip some items if needed.
				if ( $skip_next > 0 ) {
					$skip_next--;
					continue;
				}

				// For empty items print empty cells.
				if ( empty( $entry[ $term_id ] ) ) {
					$columns_html .= '<td class="wcpt-session-empty"></td>';
					continue;
				}

				// For custom labels print label and continue.
				if ( is_string( $entry[ $term_id ] ) ) {
					$columns_html .= sprintf( '<td colspan="%d" class="wcpt-session-custom">%s</td>', count( $columns ), esc_html( $entry[ $term_id ] ) );
					break;
				}

				// Gather relevant data about the session
				$colspan              = 1;
				$classes              = array();
				$session              = get_post( $entry[ $term_id ] );
				$session_title        = apply_filters( 'the_title', $session->post_title );
				$session_tracks       = get_the_terms( $session->ID, 'wcb_track' );
				$session_track_titles = is_array( $session_tracks ) ? implode( ', ', wp_list_pluck( $session_tracks, 'name' ) ) : '';
				$session_type         = get_post_meta( $session->ID, '_wcpt_session_type', true );

				if ( ! in_array( $session_type, array( 'session', 'custom' ) ) )
					$session_type = 'session';

				// Fetch speakers associated with this session.
				$speakers = array();
				$speakers_ids = array_map( 'absint', (array) get_post_meta( $session->ID, '_wcpt_speaker_id' ) );
				if ( ! empty( $speakers_ids ) ) {
					$speakers = get_posts( array(
						'post_type'      => 'wcb_speaker',
						'posts_per_page' => -1,
						'post__in'       => $speakers_ids,
					) );
				}

				// Add CSS classes to help with custom styles
				foreach ( $speakers as $speaker ) {
					$classes[] = 'wcb-speaker-' . $speaker->post_name;
				}

				if ( is_array( $session_tracks ) ) {
					foreach ( $session_tracks as $session_track ) {
						$classes[] = 'wcb-track-' . $session_track->slug;
					}
				}

				$classes[] = 'wcpt-session-type-' . $session_type;
				$classes[] = 'wcb-session-' . $session->post_name;

				// Determine the session title
				if ( 'permalink' == $attr['session_link'] && 'session' == $session_type )
					$session_title_html = sprintf( '<a class="wcpt-session-title" href="%s">%s</a>', esc_url( get_permalink( $session->ID ) ), $session_title );
				elseif ( 'anchor' == $attr['session_link'] && 'session' == $session_type )
					$session_title_html = sprintf( '<a class="wcpt-session-title" href="%s">%s</a>', esc_url( $this->get_wcpt_anchor_permalink( $session->ID ) ), $session_title );
				else
					$session_title_html = sprintf( '<span class="wcpt-session-title">%s</span>', $session_title );

				$content = $session_title_html;

				$speakers_names = array();
				foreach ( $speakers as $speaker ) {
					$speaker_name = apply_filters( 'the_title', $speaker->post_title );

					if ( 'anchor' == $attr['speaker_link'] ) // speakers/#wcorg-speaker-slug
						$speaker_permalink = $this->get_wcpt_anchor_permalink( $speaker->ID );
					elseif ( 'wporg' == $attr['speaker_link'] ) // profiles.wordpress.org/user
						$speaker_permalink = $this->get_speaker_wporg_permalink( $speaker->ID );
					elseif ( 'permalink' == $attr['speaker_link'] ) // year.city.wordcamp.org/speakers/slug
						$speaker_permalink = get_permalink( $speaker->ID );

					if ( ! empty( $speaker_permalink ) )
						$speaker_name = sprintf( '<a href="%s">%s</a>', esc_url( $speaker_permalink ), esc_html( $speaker_name ) );

					$speakers_names[] = $speaker_name;
				}

				// Add speakers names to the output string.
				if ( count( $speakers_names ) )
					$content .= sprintf( ' <span class="wcpt-session-speakers">%s</span>', implode( ', ', $speakers_names ) );

				$columns_clone = $columns;

				// If the next element in the table is the same as the current one, use colspan
				if ( $key != key( array_slice( $columns, -1, 1, true ) ) ) {
					while ( $pair = each( $columns_clone ) ) {
						if ( $pair['key'] == $key )
							continue;

						if ( ! empty( $entry[ $pair['value'] ] ) && $entry[ $pair['value'] ] == $session->ID ) {
							$colspan++;
							$skip_next++;
						} else {
							break;
						}
					}
				}

				$columns_html .= sprintf( '<td colspan="%d" class="%s" data-track-title="%s">%s</td>', $colspan, esc_attr( implode( ' ', $classes ) ), $session_track_titles, $content );
			}

			$global_session      = $colspan == count( $columns ) ? ' global-session' : '';
			$global_session_slug = $global_session ? ' ' . sanitize_html_class( sanitize_title_with_dashes( $session->post_title ) ) : '';

			$html .= sprintf( '<tr class="%s">', sanitize_html_class( 'wcpt-time-' . date( $time_format, $time ) ) . $global_session . $global_session_slug );
			$html .= sprintf( '<td class="wcpt-time">%s</td>', str_replace( ' ', '&nbsp;', esc_html( date( $time_format, $time ) ) ) );
			$html .= $columns_html;
			$html .= '</tr>';
		}

		$html .= '</tbody>';
		$html .= '</table>';
		return $html;
	}

	/**
	 * Returns a speaker's WordPress.org profile url (if username set)
	 *
	 * @param $speaker_id int The speaker's post id.
	 */
	function get_speaker_wporg_permalink( $speaker_id ) {
		$post = get_post( $speaker_id );
		if ( $post->post_type != 'wcb_speaker' || $post->post_status != 'publish' )
			return;

		$wporg_user_id = get_post_meta( $speaker_id, '_wcpt_user_id', true );
		if ( ! $wporg_user_id )
			return;

		$user = get_user_by( 'id', $wporg_user_id );
		if ( ! $user )
			return;

		$permalink = sprintf( 'http://profiles.wordpress.org/%s', strtolower( $user->user_nicename ) );
		return esc_url_raw( $permalink );
	}

	/**
	 * Returns an anchor permalink for a Speaker or Session
	 *
	 * Any page with the [speakers | sessions] shortcode will contain IDs that can be used as anchors.
	 *
	 * If the current page contains the corresponding shortcode, we'll assume the user wants to link there.
	 * Otherwise, we'll attempt to find another page that contains the shortcode.
	 *
	 * @param $target_id int The speaker/session's post ID.
	 *
	 * @return string
	 */
	function get_wcpt_anchor_permalink( $target_id ) {
		global $post;
		$anchor_target = get_post( $target_id );

		if ( 'publish' != $anchor_target->post_status ) {
			return '';
		}

		switch( $anchor_target->post_type ) {
			case 'wcb_speaker':
				$permalink = has_shortcode( $post->post_content, 'speakers' ) ? get_permalink( $post->id ) : $this->get_wcpt_permalink( 'speakers' );
				$anchor_id = $anchor_target->post_name;
				break;

			case 'wcb_session':
				$permalink = has_shortcode( $post->post_content, 'sessions' ) ? get_permalink( $post->id ) : $this->get_wcpt_permalink( 'sessions' );
				$anchor_id = $anchor_target->ID;
				break;

			default:
				$permalink = $anchor_id = false;
				break;
		}

		if ( ! $permalink ) {
			return '';
		}

		return sprintf(
			'%s#wcorg-%s-%s',
			$permalink,
			str_replace( 'wcb_', '', $anchor_target->post_type ),
			sanitize_html_class( $anchor_id )
		);
	}

	/**
	 * Returns the page permalink for speakers, sessions or organizers
	 *
	 * Fetches for a post or page with the [speakers | sessions | organizers] shortcode and
	 * returns the permalink of whichever comes first.
	 *
	 * @param string $type
	 *
	 * @return false | string
	 */
	function get_wcpt_permalink( $type ) {
		if ( ! in_array( $type, array( 'speakers', 'sessions', 'organizers' ) ) ) {
			return false;
		}

		/*
		 * The [schedule] shortcode can call this for each session and speaker, so cache the result to avoid
		 * dozens of SQL queries.
		 */
		if ( isset( $this->wcpt_permalinks[ $type ] ) ) {
			return $this->wcpt_permalinks[ $type ];
		}

		$this->wcpt_permalinks[ $type ] = false;

		$wcpt_post = get_posts( array(
			'post_type'      => array( 'post', 'page' ),
			'post_status'    => 'publish',
			's'              => '[' . $type,
			'posts_per_page' => 1,
		) );

		if ( ! empty( $wcpt_post ) ) {
			$this->wcpt_permalinks[ $type ] = get_permalink( $wcpt_post[0] );
		}

		return $this->wcpt_permalinks[ $type ];
	}

	/**
	 * Convert a string representation of a boolean to an actual boolean
	 *
	 * @param string|bool
	 *
	 * @return bool
	 */
	function str_to_bool( $value ) {
		if ( true === $value ) {
			return true;
		}

		if ( in_array( strtolower( trim( $value ) ), array( 'yes', 'true', '1' ) ) ) {
			return true;
		}

		return false;
	}

	/**
	 * The [sessions] shortcode handler
	 */
	function shortcode_sessions( $attr, $content ) {
		global $post;

		$attr = shortcode_atts( array(
			'date'           => null,
			'show_meta'      => false,
			'show_avatars'   => false,
			'avatar_size'    => 100,
			'track'          => 'all',
			'speaker_link'   => 'wporg', // anchor|wporg|permalink|none
			'posts_per_page' => -1,
			'orderby'        => 'date', // date|title|rand
			'order'          => 'desc', // asc|desc
		), $attr );

		// Convert bools to real booleans.
		$bools = array( 'show_meta', 'show_avatars' );
		foreach ( $bools as $key )
			$attr[ $key ] = $this->str_to_bool( $attr[ $key ] );

		// Clean up other attributes.
		foreach ( array( 'track', 'speaker_link', 'orderby', 'order' ) as $key_for_case_sensitive_value ) {
			$attr[ $key_for_case_sensitive_value ] = strtolower( $attr[ $key_for_case_sensitive_value ] );
		}

		$attr['avatar_size'] = absint( $attr['avatar_size'] );

		if ( ! in_array( $attr['speaker_link'], array( 'anchor', 'wporg', 'permalink', 'none' ) ) )
			$attr['speaker_link'] = 'anchor';   // todo this is inconsistent with the values passed to shortcode_atts, and probably not needed if the default above is changed to 'anchor'

		$attr['orderby'] = ( in_array( $attr['orderby'], array( 'date', 'title', 'rand' ) ) ) ? $attr['orderby'] : 'date';

		if ( 'asc' != $attr['order'] ) {
			$attr['order'] = 'desc';
		}

		$args = array(
			'post_type'      => 'wcb_session',
			'posts_per_page' => intval( $attr['posts_per_page'] ),
			'tax_query'      => array(),
			'orderby'        => $attr['orderby'],
			'order'          => $attr['order'],

			// Only ones marked "session" or where the meta key does
			// not exist, for backwards compatibility.
			'meta_query' => array(
				'relation' => 'AND',

				array(
					'relation' => 'OR',

					array(
						'key'     => '_wcpt_session_type',
						'value'   => 'session',
						'compare' => '=',
					),

					array(
						'key'     => '_wcpt_session_type',
						'value'   => '',
						'compare' => 'NOT EXISTS',
					),
				)
			),
		);

		if ( $attr['date'] && strtotime( $attr['date'] ) ) {
			$args['meta_query'][] = array(
				'key'     => '_wcpt_session_time',
				'value'   => array(
					strtotime( $attr['date'] ),
					strtotime( $attr['date'] . ' +1 day' ),
				),
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			);
		}

		if ( 'all' != $attr['track'] ) {
			$args['tax_query'][] = array(
				'taxonomy' => 'wcb_track',
				'field'    => 'slug',
				'terms'    => $attr['track'],
			);
		}

		// Fetch sessions.
		$sessions = new WP_Query( $args );

		if ( ! $sessions->have_posts() )
			return;

		ob_start();
		?>

		<div class="wcorg-sessions">

			<?php while ( $sessions->have_posts() ) : $sessions->the_post(); ?>

				<?php
					// Things to be output, or not.
					$session_meta = '';
					$speakers_avatars = '';
					$links            = array();

					// Fetch speakers associated with this session.
					$speakers = array();
					$speakers_ids = array_map( 'absint', (array) get_post_meta( get_the_ID(), '_wcpt_speaker_id' ) );
					if ( ! empty( $speakers_ids ) ) {
						$speakers = get_posts( array(
							'post_type'      => 'wcb_speaker',
							'posts_per_page' => -1,
							'post__in'       => $speakers_ids,
						) );
					}

					// Should we add avatars?
					if ( $attr['show_avatars'] ) {
						foreach ( $speakers as $speaker ) {
							$speakers_avatars .= get_avatar( get_post_meta( $speaker->ID, '_wcb_speaker_email', true ), absint( $attr['avatar_size'] ) );
						}
					}

					// Should we output meta?
					if ( $attr['show_meta'] ) {
						$speaker_permalink = '';
						$speakers_names = array();
						$tracks_names = array();

						foreach ( $speakers as $speaker ) {
							$speaker_name = apply_filters( 'the_title', $speaker->post_title );

							if ( 'anchor' == $attr['speaker_link'] ) // speakers/#wcorg-speaker-slug
								$speaker_permalink = $this->get_wcpt_anchor_permalink( $speaker->ID );
							elseif ( 'wporg' == $attr['speaker_link'] ) // profiles.wordpress.org/user
								$speaker_permalink = $this->get_speaker_wporg_permalink( $speaker->ID );
							elseif ( 'permalink' == $attr['speaker_link'] ) // year.city.wordcamp.org/speakers/slug
								$speaker_permalink = get_permalink( $speaker->ID );

							if ( ! empty( $speaker_permalink ) )
								$speaker_name = sprintf( '<a href="%s">%s</a>', esc_url( $speaker_permalink ), esc_html( $speaker_name ) );

							$speakers_names[] = $speaker_name;
						}

						$tracks = get_the_terms( get_the_ID(), 'wcb_track' );
						if ( is_array( $tracks ) )
							foreach ( $tracks as $track )
								$tracks_names[] = apply_filters( 'the_title', $track->name );

						// Add speakers and tracks to session meta.
						if ( ! empty( $speakers_names ) && ! empty( $tracks_names ) )
							$session_meta .= sprintf( __( 'Presented by %1$s in %2$s.', 'wordcamporg' ), implode( ', ', $speakers_names ), implode( ', ', $tracks_names ) );
						elseif ( ! empty( $speakers_names ) )
							$session_meta .= sprintf( __( 'Presented by %s.', 'wordcamporg' ), implode( ', ', $speakers_names ) );
						elseif ( ! empty( $tracks_names ) )
							$session_meta .= sprintf( __( 'Presented in %s.', 'wordcamporg' ), implode( ', ', $tracks_names ) );

						if ( ! empty( $session_meta ) )
							$session_meta = sprintf( '<p class="wcpt-session-meta">%s</p>', $session_meta );
					}

					// Gather data for list of links
					if ( $url = get_post_meta( $post->ID, '_wcpt_session_slides', true ) ) {
						$links['slides'] = array(
							'url'   => $url,
							'label' => __( 'Slides', 'wordcamporg' ),
						);
					}

					if ( $url = get_post_meta( $post->ID, '_wcpt_session_video', true ) ) {
						$links['video'] = array(
							'url'   => $url,
							'label' => __( 'Video', 'wordcamporg' ),
						);
					}

				?>

				<div id="wcorg-session-<?php the_ID(); ?>" class="wcorg-session" >
					<h2><?php the_title(); ?></h2>
					<div class="wcorg-session-description">
						<?php the_post_thumbnail(); ?>
						<?php echo $session_meta; ?>
						<?php echo $speakers_avatars; ?>
						<?php the_content(); ?>

						<?php if ( $links ) : ?>
							<ul class="wcorg-session-links">
								<?php foreach( $links as $link ) : ?>
									<li>
										<a href="<?php echo esc_url( $link['url'] ); ?>">
											<?php echo esc_html( $link['label'] ); ?>
										</a>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>
					</div>
				</div>

			<?php endwhile; ?>

		</div><!-- .wcorg-sessions -->

		<?php

		wp_reset_postdata();
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * The [sponsors] shortcode handler.
	 */
	function shortcode_sponsors( $attr, $content ) {
		global $post;

		$attr = shortcode_atts( array(
			'link' => 'none'
		), $attr );

		$attr['link'] = strtolower( $attr['link'] );
		$terms = $this->get_sponsor_levels();

		ob_start();
		?>

		<div class="wcorg-sponsors">
		<?php foreach ( $terms as $term ) : ?>
			<?php
				$sponsors = new WP_Query( array(
					'post_type'      => 'wcb_sponsor',
					'order'          => 'ASC',
					'posts_per_page' => -1,
					'taxonomy'       => $term->taxonomy,
					'term'           => $term->slug,
				) );

				if ( ! $sponsors->have_posts() )
					continue;
			?>

			<div class="wcorg-sponsor-level-<?php echo sanitize_html_class( $term->slug ); ?>">
				<h2><?php echo esc_html( $term->name ); ?></h2>

				<?php while ( $sponsors->have_posts() ) : $sponsors->the_post(); ?>
				<?php $website = get_post_meta( get_the_ID(), '_wcpt_sponsor_website', true ); ?>

				<div id="wcorg-sponsor-<?php the_ID(); ?>" class="wcorg-sponsor">
					<?php if ( 'website' == $attr['link'] && $website ) : ?>
						<h3><a href="<?php echo esc_attr( esc_url( $website ) ); ?>"><?php the_title(); ?></a></h3>
					<?php else : ?>
						<h3><?php the_title(); ?></h3>
					<?php endif; ?>

					<div class="wcorg-sponsor-description">
						<?php if ( 'website' == $attr['link'] && $website ) : ?>
							<a href="<?php echo esc_attr( esc_url( $website ) ); ?>">
								<?php the_post_thumbnail( 'wcb-sponsor-logo-horizontal-2x' ); ?>
							</a>
						<?php else : ?>
							<?php the_post_thumbnail( 'wcb-sponsor-logo-horizontal-2x' ); ?>
						<?php endif; ?>

						<?php the_content(); ?>
					</div>
				</div><!-- #sponsor -->
				<?php endwhile; ?>

			</div><!-- .wcorg-sponsor-level -->

		<?php endforeach; ?>
		</div><!-- .wcorg-sponsors -->
		<?php

		wp_reset_postdata();
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * Determine if the current loop is just a single page, or a loop of posts within a page
	 *
	 * For example, this helps to target a single wcb_speaker post vs a page containing the [speakers] shortcode,
	 * which loops through wcb_speaker posts. Using functions like is_single() don't work, because they reference
	 * the main query instead of the $speakers query.
	 *
	 * @param string $post_type
	 *
	 * @return bool
	 */
	protected function is_single_cpt_post( $post_type ) {
		global $wp_query;

		return isset( $wp_query->query[ $post_type ] ) && $post_type == $wp_query->query['post_type'];
	}

	/**
	 * Add the speaker's avatar to their post
	 *
	 * We don't enable it for sites that were created before it was committed, because it may need custom CSS
	 * to look good with their custom design, but we allow older sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function add_avatar_to_speaker_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_speaker_post_avatar_enabled_site_ids', array( 364 ) );    // 2014.sf

		if ( ! $this->is_single_cpt_post( 'wcb_speaker') ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_speaker_post_avatar_min_site_id', 463 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$avatar = get_avatar( get_post_meta( $post->ID, '_wcb_speaker_email', true ) );
		return '<div class="speaker-avatar">' . $avatar . '</div>' . $content;
	}

	/**
	 * Add speaker information to Session posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the bio to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	function add_speaker_info_to_session_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_session_post_speaker_info_enabled_site_ids', array( 364 ) );    // 2014.sf

		if ( ! $this->is_single_cpt_post( 'wcb_session') ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_session_post_speaker_info_min_site_id', 463 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$speaker_ids = (array) get_post_meta( $post->ID, '_wcpt_speaker_id' );

		if ( empty ( $speaker_ids ) ) {
			return $content;
		}

		$speaker_args = array(
			'post_type'      => 'wcb_speaker',
			'posts_per_page' => -1,
			'post__in'       => $speaker_ids,
			'orderby'        => 'title',
			'order'          => 'asc',
		);

		$speakers = new WP_Query( $speaker_args );

		if ( ! $speakers->have_posts() ) {
			return $content;
		}

		$speakers_html = sprintf(
			'<h2 class="session-speakers">%s</h2>',
			_n(
				__( 'Speaker', 'wordcamporg' ),
				__( 'Speakers', 'wordcamporg' ),
				$speakers->post_count
			)
		);

		$speakers_html .= '<ul id="session-speaker-names">';
		while ( $speakers->have_posts() ) {
			$speakers->the_post();
			$speakers_html .= sprintf( '<li><a href="%s">%s</a></li>', get_the_permalink(), get_the_title() );
		}
		$speakers_html .= '</ul>';

		wp_reset_postdata();

		return $content . $speakers_html;
	}

	/**
	 * Add Slides link to Session posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the session to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	function add_slides_info_to_session_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_session_post_slides_info_enabled_site_ids', array(
			206,  // testing.wordcamp.org
			648,  // 2016.asheville
			651,  // 2016.kansascity
		) );

		if ( ! $this->is_single_cpt_post( 'wcb_session' ) ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_session_post_slides_info_min_site_id', 699 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$session_slides = get_post_meta( $post->ID, '_wcpt_session_slides', true );

		if ( empty ( $session_slides ) ) {
			return $content;
		}

		$session_slides_html  = '<div class="session-video">';
		$session_slides_html .= sprintf( __( '<a href="%s" target="_blank">View Session Slides</a>', 'wordcamporg' ), esc_url( $session_slides ) );
		$session_slides_html .= '</div>';

		return $content . $session_slides_html;
	}

	/**
	 * Add Video link to Session posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the session to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	function add_video_info_to_session_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_session_post_video_info_enabled_site_ids', array( 206 ) ); // testing.wordcamp.org

		if ( ! $this->is_single_cpt_post( 'wcb_session' ) ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_session_post_video_info_min_site_id', 699 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$session_video = get_post_meta( $post->ID, '_wcpt_session_video', true );

		if ( empty ( $session_video ) ) {
			return $content;
		}

		$session_video_html  = '<div class="session-video">';
		$session_video_html .= sprintf( __( '<a href="%s" target="_blank">View Session Video</a>', 'wordcamporg' ), esc_url( $session_video ) );
		$session_video_html .= '</div>';

		return $content . $session_video_html;
	}

	/**
	 * Add session information to Speaker posts
	 *
	 * We don't enable it for sites that were created before it was committed, because some will have already
	 * crafted the bio to include this content, so duplicating it would look wrong, but we still allow older
	 * sites to opt-in.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	function add_session_info_to_speaker_posts( $content ) {
		global $post;
		$enabled_site_ids = apply_filters( 'wcpt_speaker_post_session_info_enabled_site_ids', array( 364 ) );    // 2014.sf

		if ( ! $this->is_single_cpt_post( 'wcb_speaker') ) {
			return $content;
		}

		$site_id = get_current_blog_id();
		if ( $site_id <= apply_filters( 'wcpt_speaker_post_session_info_min_site_id', 463 ) && ! in_array( $site_id, $enabled_site_ids ) ) {
			return $content;
		}

		$session_args = array(
			'post_type'      => 'wcb_session',
			'posts_per_page' => -1,
			'meta_key'       => '_wcpt_speaker_id',
			'meta_value'     => $post->ID,
			'orderby'        => 'title',
			'order'          => 'asc',
		);

		$sessions = new WP_Query( $session_args );

		if ( ! $sessions->have_posts() ) {
			return $content;
		}

		$sessions_html = sprintf(
			'<h2 class="speaker-sessions">%s</h2>',
			_n(
				__( 'Session', 'wordcamporg' ),
				__( 'Sessions', 'wordcamporg' ),
				$sessions->post_count
			)
		);

		$sessions_html .= '<ul id="speaker-session-names">';
		while ( $sessions->have_posts() ) {
			$sessions->the_post();
			$sessions_html .= sprintf( '<li><a href="%s">%s</a></li>', get_the_permalink(), get_the_title() );
		}
		$sessions_html .= '</ul>';

		wp_reset_postdata();

		return $content . $sessions_html;
	}

	/**
	 * Fired during add_meta_boxes, adds extra meta boxes to our custom post types.
	 */
	function add_meta_boxes() {
		add_meta_box( 'speaker-info',   __( 'Speaker Info',   'wordcamporg'  ), array( $this, 'metabox_speaker_info'   ), 'wcb_speaker',   'side' );
		add_meta_box( 'organizer-info', __( 'Organizer Info', 'wordcamporg'  ), array( $this, 'metabox_organizer_info' ), 'wcb_organizer', 'side' );
		add_meta_box( 'speakers-list',  __( 'Speakers',       'wordcamporg'  ), array( $this, 'metabox_speakers_list'  ), 'wcb_session',   'side' );
		add_meta_box( 'session-info',   __( 'Session Info',   'wordcamporg'  ), array( $this, 'metabox_session_info'   ), 'wcb_session',   'normal' );
		add_meta_box( 'sponsor-info',   __( 'Sponsor Info',   'wordcamporg'  ), array( $this, 'metabox_sponsor_info'   ), 'wcb_sponsor',   'normal' );
		add_meta_box( 'invoice-sponsor', __( 'Invoice Sponsor', 'wordcamporg' ), array( $this, 'metabox_invoice_sponsor' ), 'wcb_sponsor', 'side'   );
	}

	/**
	 * Used by the Speakers post type
	 */
	function metabox_speaker_info() {
		global $post;
		$email = get_post_meta( $post->ID, '_wcb_speaker_email', true );

		$wporg_username = '';
		$user_id        = get_post_meta( $post->ID, '_wcpt_user_id', true );
		$wporg_user     = get_user_by( 'id', $user_id );

		if ( $wporg_user )
			$wporg_username = $wporg_user->user_nicename;
		?>

		<?php wp_nonce_field( 'edit-speaker-info', 'wcpt-meta-speaker-info' ); ?>

		<p>
			<label for="wcpt-gravatar-email"><?php _e( 'Gravatar Email:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-gravatar-email" name="wcpt-gravatar-email" value="<?php echo esc_attr( $email ); ?>" />
		</p>

		<p>
			<label for="wcpt-wporg-username"><?php _e( 'WordPress.org Username:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-wporg-username" name="wcpt-wporg-username" value="<?php echo esc_attr( $wporg_username ); ?>" />
		</p>

		<?php
	}

	/**
	 * Rendered in the Organizer post type
	 */
	function metabox_organizer_info() {
		global $post;

		$wporg_username = '';
		$user_id        = get_post_meta( $post->ID, '_wcpt_user_id', true );
		$wporg_user     = get_user_by( 'id', $user_id );

		if ( $wporg_user )
			$wporg_username = $wporg_user->user_nicename;
		?>

		<?php wp_nonce_field( 'edit-organizer-info', 'wcpt-meta-organizer-info' ); ?>

		<p>
			<label for="wcpt-wporg-username"><?php _e( 'WordPress.org Username:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-wporg-username" name="wcpt-wporg-username" value="<?php echo esc_attr( $wporg_username ); ?>" />
		</p>

		<?php
	}

	/**
	 * Used by the Sessions post type, renders a text box for speakers input.
	 */
	function metabox_speakers_list() {
		global $post;
		$speakers = get_post_meta( $post->ID, '_wcb_session_speakers', true );
		wp_enqueue_script( 'jquery-ui-autocomplete' );

		$speakers_names   = array();
		$speakers_objects = get_posts( array(
			'post_type'      => 'wcb_speaker',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
		) );

		// We'll use these in js.
		foreach ( $speakers_objects as $speaker_object )
			$speakers_names[] = $speaker_object->post_title;
		$speakers_names_first = array_pop( $speakers_names );
		?>

		<?php wp_nonce_field( 'edit-speakers-list', 'wcpt-meta-speakers-list-nonce' ); ?>
		<!--<input type="text" class="text" id="wcpt-speakers-list" name="wcpt-speakers-list" value="<?php echo esc_attr( $speakers ); ?>" />-->
		<textarea class="large-text" placeholder="Start typing a name" id="wcpt-speakers-list" name="wcpt-speakers-list"><?php echo esc_textarea( $speakers ); ?></textarea>
		<p class="description"><?php _e( 'A speaker entry must exist first. Separate multiple speakers with commas.', 'wordcamporg' ); ?></p>

		<script>
		jQuery(document).ready( function($) {
			var availableSpeakers = [ <?php
				foreach ( $speakers_names as $name ) { printf( "'%s', ", esc_js( $name ) ); }
				printf( "'%s'", esc_js( $speakers_names_first ) ); // avoid the trailing comma
			?> ];
			function split( val ) {
				return val.split( /,\s*/ );
			}
			function extractLast( term ) {
				return split( term ).pop();
			}
			$( '#wcpt-speakers-list' )
				.bind( 'keydown', function( event ) {
					if ( event.keyCode == $.ui.keyCode.TAB &&
						$( this ).data( 'autocomplete' ).menu.active ) {
						event.preventDefault();
					}
				})
				.autocomplete({
					minLength: 0,
					source: function( request, response ) {
						response( $.ui.autocomplete.filter(
							availableSpeakers, extractLast( request.term ) ) )
					},
					focus: function() {
						return false;
					},
					select: function( event, ui ) {
						var terms = split( this.value );
						terms.pop();
						terms.push( ui.item.value );
						terms.push( '' );
						this.value = terms.join( ', ' );
						$(this).focus();
						return false;
					},
					open: function() { $(this).addClass('open'); },
					close: function() { $(this).removeClass('open'); }
				});
		});
		</script>

		<?php
	}

	function metabox_session_info() {
		$post             = get_post();
		$session_time     = absint( get_post_meta( $post->ID, '_wcpt_session_time', true ) );
		$session_date     = ( $session_time ) ? date( 'Y-m-d', $session_time ) : date( 'Y-m-d' );
		$session_hours    = ( $session_time ) ? date( 'g', $session_time )     : date( 'g' );
		$session_minutes  = ( $session_time ) ? date( 'i', $session_time )     : '00';
		$session_meridiem = ( $session_time ) ? date( 'a', $session_time )     : 'am';
		$session_type     = get_post_meta( $post->ID, '_wcpt_session_type', true );
		$session_slides   = get_post_meta( $post->ID, '_wcpt_session_slides', true );
		$session_video    = get_post_meta( $post->ID, '_wcpt_session_video',  true );
		?>

		<?php wp_nonce_field( 'edit-session-info', 'wcpt-meta-session-info' ); ?>

		<p>
			<label for="wcpt-session-date"><?php _e( 'Date:', 'wordcamporg' ); ?></label>
			<input type="text" id="wcpt-session-date" data-date="<?php echo esc_attr( $session_date ); ?>" name="wcpt-session-date" value="<?php echo esc_attr( $session_date ); ?>" /><br />
			<label><?php _e( 'Time:', 'wordcamporg' ); ?></label>

			<select name="wcpt-session-hour" aria-label="<?php _e( 'Session Start Hour', 'wordcamporg' ); ?>">
				<?php for ( $i = 1; $i <= 12; $i++ ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, $session_hours ) ?>>
						<?php echo esc_html( $i ); ?>
					</option>
				<?php endfor; ?>
			</select> :

			<select name="wcpt-session-minutes" aria-label="<?php _e( 'Session Start Minutes', 'wordcamporg' ); ?>">
				<?php for ( $i = '00'; (int) $i <= 55; $i = sprintf( '%02d', (int) $i + 5 ) ) : ?>
					<option value="<?php echo esc_attr( $i ); ?>" <?php selected( $i, $session_minutes ) ?>>
						<?php echo esc_html( $i ); ?>
					</option>
				<?php endfor; ?>
			</select>

			<select name="wcpt-session-meridiem" aria-label="<?php _e( 'Session Meridiem', 'wordcamporg' ); ?>">
				<option value="am" <?php selected( 'am', $session_meridiem ) ?>>am</option>
				<option value="pm" <?php selected( 'pm', $session_meridiem ) ?>>pm</option>
			</select>
		</p>

		<p>
			<label for="wcpt-session-type"><?php _e( 'Type:', 'wordcamporg' ); ?></label>
			<select id="wcpt-session-type" name="wcpt-session-type">
				<option value="session" <?php selected( $session_type, 'session' ); ?>><?php _e( 'Regular Session', 'wordcamporg' ); ?></option>
				<option value="custom" <?php selected( $session_type, 'custom' ); ?>><?php _e( 'Break, Lunch, etc.', 'wordcamporg' ); ?></option>
			</select>
		</p>

		<p>
			<label for="wcpt-session-slides"><?php _e( 'Slides URL:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-session-slides" name="wcpt-session-slides" value="<?php echo esc_url( $session_slides ); ?>" />
		</p>

		<p>
			<label for="wcpt-session-video"><?php _e( 'WordPress.TV URL:', 'wordcamporg' ); ?></label>
			<input type="text" class="widefat" id="wcpt-session-video" name="wcpt-session-video" value="<?php echo esc_url( $session_video ); ?>" />
		</p>

		<?php
	}

	/**
	 * Render the Sponsor Info metabox view
	 *
	 * @param WP_Post $sponsor
	 */
	function metabox_sponsor_info( $sponsor ) {
		$company_name      = get_post_meta( $sponsor->ID, '_wcpt_sponsor_company_name',      true );
		$website           = get_post_meta( $sponsor->ID, '_wcpt_sponsor_website',           true );
		$first_name        = get_post_meta( $sponsor->ID, '_wcpt_sponsor_first_name',        true );
		$last_name         = get_post_meta( $sponsor->ID, '_wcpt_sponsor_last_name',         true );
		$email_address     = get_post_meta( $sponsor->ID, '_wcpt_sponsor_email_address',     true );
		$phone_number      = get_post_meta( $sponsor->ID, '_wcpt_sponsor_phone_number',      true );
		$vat_number        = get_post_meta( $sponsor->ID, '_wcpt_sponsor_vat_number',        true );

		$street_address1 = get_post_meta( $sponsor->ID, '_wcpt_sponsor_street_address1',   true );
		$street_address2 = get_post_meta( $sponsor->ID, '_wcpt_sponsor_street_address2',   true );
		$city            = get_post_meta( $sponsor->ID, '_wcpt_sponsor_city',              true );
		$state           = get_post_meta( $sponsor->ID, '_wcpt_sponsor_state',             true );
		$zip_code        = get_post_meta( $sponsor->ID, '_wcpt_sponsor_zip_code',          true );
		$country         = get_post_meta( $sponsor->ID, '_wcpt_sponsor_country',           true );

		$available_countries = array( 'Abkhazia', 'Afghanistan', 'Aland', 'Albania', 'Algeria', 'American Samoa', 'Andorra', 'Angola', 'Anguilla', 'Antigua and Barbuda', 'Argentina', 'Armenia', 'Aruba', 'Ascension', 'Ashmore and Cartier Islands', 'Australia', 'Australian Antarctic Territory', 'Austria', 'Azerbaijan', 'Bahamas, The', 'Bahrain', 'Baker Island', 'Bangladesh', 'Barbados', 'Belarus', 'Belgium', 'Belize', 'Benin', 'Bermuda', 'Bhutan', 'Bolivia', 'Bosnia and Herzegovina', 'Botswana', 'Bouvet Island', 'Brazil', 'British Antarctic Territory', 'British Indian Ocean Territory', 'British Sovereign Base Areas', 'British Virgin Islands', 'Brunei', 'Bulgaria', 'Burkina Faso', 'Burundi', 'Cambodia', 'Cameroon', 'Canada', 'Cape Verde', 'Cayman Islands', 'Central African Republic', 'Chad', 'Chile', "China, People's Republic of", 'China, Republic of (Taiwan)', 'Christmas Island', 'Clipperton Island', 'Cocos (Keeling) Islands', 'Colombia', 'Comoros', 'Congo, (Congo  Brazzaville)', 'Congo, (Congo  Kinshasa)', 'Cook Islands', 'Coral Sea Islands', 'Costa Rica', "Cote d'Ivoire (Ivory Coast)", 'Croatia', 'Cuba', 'Cyprus', 'Czech Republic', 'Denmark', 'Djibouti', 'Dominica', 'Dominican Republic', 'Ecuador', 'Egypt', 'El Salvador', 'Equatorial Guinea', 'Eritrea', 'Estonia', 'Ethiopia', 'Falkland Islands (Islas Malvinas)', 'Faroe Islands', 'Fiji', 'Finland', 'France', 'French Guiana', 'French Polynesia', 'French Southern and Antarctic Lands', 'Gabon', 'Gambia, The', 'Georgia', 'Germany', 'Ghana', 'Gibraltar', 'Greece', 'Greenland', 'Grenada', 'Guadeloupe', 'Guam', 'Guatemala', 'Guernsey', 'Guinea', 'Guinea-Bissau', 'Guyana', 'Haiti', 'Heard Island and McDonald Islands', 'Honduras', 'Hong Kong', 'Howland Island', 'Hungary', 'Iceland', 'India', 'Indonesia', 'Iran', 'Iraq', 'Ireland', 'Isle of Man', 'Israel', 'Italy', 'Jamaica', 'Japan', 'Jarvis Island', 'Jersey', 'Johnston Atoll', 'Jordan', 'Kazakhstan', 'Kenya', 'Kingman Reef', 'Kiribati', 'Korea, North', 'Korea, South', 'Kuwait', 'Kyrgyzstan', 'Laos', 'Latvia', 'Lebanon', 'Lesotho', 'Liberia', 'Libya', 'Liechtenstein', 'Lithuania', 'Luxembourg', 'Macau', 'Macedonia', 'Madagascar', 'Malawi', 'Malaysia', 'Maldives', 'Mali', 'Malta', 'Marshall Islands', 'Martinique', 'Mauritania', 'Mauritius', 'Mayotte', 'Mexico', 'Micronesia', 'Midway Islands', 'Moldova', 'Monaco', 'Mongolia', 'Montenegro', 'Montserrat', 'Morocco', 'Mozambique', 'Myanmar (Burma)', 'Nagorno-Karabakh', 'Namibia', 'Nauru', 'Navassa Island', 'Nepal', 'Netherlands', 'Netherlands Antilles', 'New Caledonia', 'New Zealand', 'Nicaragua', 'Niger', 'Nigeria', 'Niue', 'Norfolk Island', 'Northern Cyprus', 'Northern Mariana Islands', 'Norway', 'Oman', 'Pakistan', 'Palau', 'Palmyra Atoll', 'Panama', 'Papua New Guinea', 'Paraguay', 'Peru', 'Peter I Island', 'Philippines', 'Pitcairn Islands', 'Poland', 'Portugal', 'Pridnestrovie (Transnistria)', 'Puerto Rico', 'Qatar', 'Queen Maud Land', 'Reunion', 'Romania', 'Ross Dependency', 'Russia', 'Rwanda', 'Saint Barthelemy', 'Saint Helena', 'Saint Kitts and Nevis', 'Saint Lucia', 'Saint Martin', 'Saint Pierre and Miquelon', 'Saint Vincent and the Grenadines', 'Samoa', 'San Marino', 'Sao Tome and Principe', 'Saudi Arabia', 'Senegal', 'Serbia', 'Seychelles', 'Sierra Leone', 'Singapore', 'Slovakia', 'Slovenia', 'Solomon Islands', 'Somalia', 'Somaliland', 'South Africa', 'South Georgia & South Sandwich Islands', 'South Ossetia', 'Spain', 'Sri Lanka', 'Sudan', 'Suriname', 'Svalbard', 'Swaziland', 'Sweden', 'Switzerland', 'Syria', 'Tajikistan', 'Tanzania', 'Thailand', 'Timor-Leste (East Timor)', 'Togo', 'Tokelau', 'Tonga', 'Trinidad and Tobago', 'Tristan da Cunha', 'Tunisia', 'Turkey', 'Turkmenistan', 'Turks and Caicos Islands', 'Tuvalu', 'U.S. Virgin Islands', 'Uganda', 'Ukraine', 'United Arab Emirates', 'United Kingdom', 'United States', 'Uruguay', 'Uzbekistan', 'Vanuatu', 'Vatican City', 'Venezuela', 'Vietnam', 'Wake Island', 'Wallis and Futuna', 'Yemen', 'Zambia', 'Zimbabwe' );
		// todo use WordCamp_Budgets::get_valid_countries_iso3166() instead. need to switch multi-event sponsors at same time.

		wp_nonce_field( 'edit-sponsor-info', 'wcpt-meta-sponsor-info' );

		require_once( __DIR__ . '/views/sponsors/metabox-sponsor-info.php' );
	}

	/**
	 * Render the Invoice Sponsor metabox view
	 *
	 * @param WP_Post $sponsor
	 */
	function metabox_invoice_sponsor( $sponsor ) {
		$current_screen = get_current_screen();

		$existing_invoices = get_posts( array(
			'post_type'      => \WordCamp\Budgets\Sponsor_Invoices\POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => - 1,

			'meta_query' => array(
				array(
					'key'   => '_wcbsi_sponsor_id',
					'value' => $sponsor->ID,
				),
			),
		) );

		$new_invoice_url = add_query_arg(
			array(
				'post_type'  => 'wcb_sponsor_invoice',
				'sponsor_id' => $sponsor->ID,
			),
			admin_url( 'post-new.php' )
		);

		require_once( __DIR__ . '/views/sponsors/metabox-invoice-sponsor.php' );
	}

	/**
	 * Display the indicator that marks a form field as required
	 */
	function render_form_field_required_indicator() {
		require( __DIR__ . '/views/common/form-field-required-indicator.php' );
	}

	/**
	 * Fired when a post is saved, makes sure additional metadata is also updated.
	 */
	function save_post_speaker( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || $post->post_type != 'wcb_speaker' || ! current_user_can( 'edit_post', $post_id ) )
			return;

		if ( isset( $_POST['wcpt-meta-speaker-info'] ) && wp_verify_nonce( $_POST['wcpt-meta-speaker-info'], 'edit-speaker-info' ) ) {
			$email          = sanitize_text_field( $_POST['wcpt-gravatar-email'] );
			$wporg_username = sanitize_text_field( $_POST['wcpt-wporg-username'] );
			$wporg_user     = wcorg_get_user_by_canonical_names( $wporg_username );

			if ( empty( $email ) )
				delete_post_meta( $post_id, '_wcb_speaker_email' );
			elseif ( $email && is_email( $email ) )
				update_post_meta( $post_id, '_wcb_speaker_email', $email );

			if ( ! $wporg_user )
				delete_post_meta( $post_id, '_wcpt_user_id' );
			else
				update_post_meta( $post_id, '_wcpt_user_id', $wporg_user->ID );
		}
	}

	/**
	 * When an Organizer post is saved, update some meta data.
	 */
	function save_post_organizer( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || $post->post_type != 'wcb_organizer' || ! current_user_can( 'edit_post', $post_id ) )
			return;

		if ( isset( $_POST['wcpt-meta-organizer-info'] ) && wp_verify_nonce( $_POST['wcpt-meta-organizer-info'], 'edit-organizer-info' ) ) {
			$wporg_username = sanitize_text_field( $_POST['wcpt-wporg-username'] );
			$wporg_user = wcorg_get_user_by_canonical_names( $wporg_username );

			if ( ! $wporg_user )
				delete_post_meta( $post_id, '_wcpt_user_id' );
			else
				update_post_meta( $post_id, '_wcpt_user_id', $wporg_user->ID );
		}
	}

	/**
	 * Fired when a post is saved, updates additional sessions metadada.
	 */
	function save_post_session( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || $post->post_type != 'wcb_session' )
			return;

		if ( isset( $_POST['wcpt-meta-speakers-list-nonce'] ) && wp_verify_nonce( $_POST['wcpt-meta-speakers-list-nonce'], 'edit-speakers-list' ) && current_user_can( 'edit_post', $post_id ) ) {

			// Update the text box as is for backwards compatibility.
			$speakers = sanitize_text_field( $_POST['wcpt-speakers-list'] );
			update_post_meta( $post_id, '_wcb_session_speakers', $speakers );
		}

		if ( isset( $_POST['wcpt-meta-session-info'] ) && wp_verify_nonce( $_POST['wcpt-meta-session-info'], 'edit-session-info' ) ) {
			// Update session time
			$session_time = strtotime( sprintf(
				'%s %d:%02d %s',
				sanitize_text_field( $_POST['wcpt-session-date'] ),
				absint( $_POST['wcpt-session-hour'] ),
				absint( $_POST['wcpt-session-minutes'] ),
				'am' == $_POST['wcpt-session-meridiem'] ? 'am' : 'pm'
			) );
			update_post_meta( $post_id, '_wcpt_session_time', $session_time );

			// Update session type
			$session_type = sanitize_text_field( $_POST['wcpt-session-type'] );
			if ( ! in_array( $session_type, array( 'session', 'custom' ) ) )
				$session_type = 'session';

			update_post_meta( $post_id, '_wcpt_session_type', $session_type );

			// Update session slides link
			update_post_meta( $post_id, '_wcpt_session_slides', esc_url_raw( $_POST['wcpt-session-slides'] ) );

			// Update session video link
			if ( 'wordpress.tv' == str_replace( 'www.', '', strtolower( parse_url( $_POST['wcpt-session-video'], PHP_URL_HOST ) ) ) ) {
				update_post_meta( $post_id, '_wcpt_session_video', esc_url_raw( $_POST['wcpt-session-video'] ) );
			}
		}

		// Allowed outside of $_POST. If anything updates a session, make sure
		// we parse the list of speakers and add the references to speakers.
		$speakers_list = get_post_meta( $post_id, '_wcb_session_speakers', true );
		$speakers_list = explode( ',', $speakers_list );

		if ( ! is_array( $speakers_list ) )
			$speakers_list = array();

		$speaker_ids = array();
		$speakers    = array_unique( array_map( 'trim', $speakers_list ) );

		foreach ( $speakers as $speaker_name ) {
			if ( empty( $speaker_name ) )
				continue;

			/*
			 * Look for speakers by their names.
			 *
			 * @todo - This is very fragile, it fails if the speaker name has a tab character instead of a space
			 * separating the first from last name, or an extra space at the end, etc. Those situations often arise
			 * from copy/pasting the speaker data from spreadsheets. Moving to automated speaker submissions and
			 * tighter integration with WordPress.org usernames should avoid this, but if not we should do something
			 * here to make it more forgiving.
			 */
			$speaker = get_page_by_title( $speaker_name, OBJECT, 'wcb_speaker' );
			if ( $speaker )
				$speaker_ids[] = $speaker->ID;
		}

		// Add speaker IDs to post meta.
		$speaker_ids = array_unique( $speaker_ids );
		delete_post_meta( $post_id, '_wcpt_speaker_id' );
		foreach ( $speaker_ids as $speaker_id )
			add_post_meta( $post_id, '_wcpt_speaker_id', $speaker_id );

		// Set the speaker as the author of the session post, so the single
		// view doesn't confuse users who see "posted by [organizer name]"
		foreach ( $speaker_ids as $speaker_post ) {
			$wporg_user_id = get_post_meta( $speaker_post, '_wcpt_user_id', true );
			$user = get_user_by( 'id', $wporg_user_id );

			if ( $user ) {
				remove_action( 'save_post', array( $this, 'save_post_session' ), 10, 2 );	// avoid infinite recursion
				wp_update_post( array(
					'ID'          => $post_id,
					'post_author' => $user->ID
				) );
				add_action( 'save_post', array( $this, 'save_post_session' ), 10, 2 );

				break;
			}
		}
	}

	/**
	 * Save meta data for Sponsor posts
	 */
	function save_post_sponsor( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || $post->post_type != 'wcb_sponsor' || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( isset( $_POST['wcpt-meta-sponsor-info'] ) && wp_verify_nonce( $_POST['wcpt-meta-sponsor-info'], 'edit-sponsor-info' ) ) {
			$text_values = array(
				'company_name',	'first_name', 'last_name', 'email_address', 'phone_number', 'vat_number',
				'street_address1', 'street_address2', 'city', 'state', 'zip_code', 'country'
			);

			foreach ( $text_values as $id ) {
				$values[ $id ] = sanitize_text_field( $_POST["_wcpt_sponsor_$id"] );
			}

			$values['website'] = esc_url_raw( $_POST['_wcpt_sponsor_website'] );
			// TODO: maybe only allows links to home page, depending on outcome of http://make.wordpress.org/community/2013/12/31/irs-rules-for-corporate-sponsorship-of-wordcamp/

			$values['first_name'] = ucfirst( $values['first_name'] );
			$values['last_name' ] = ucfirst( $values['last_name' ] );

			foreach( $values as $id => $value ) {
				if ( empty( $value ) ) {
					delete_post_meta( $post_id, "_wcpt_sponsor_$id" );
				} else {
					update_post_meta( $post_id, "_wcpt_sponsor_$id", $value );
				}
			}
		}
	}

	/**
	 * Registers the custom post types, runs during init.
	 */
	function register_post_types() {
		// Speaker post type labels.
		$labels = array(
			'name'                  => __( 'Speakers', 'wordcamporg' ),
			'singular_name'         => __( 'Speaker', 'wordcamporg' ),
			'add_new'               => __( 'Add New', 'wordcamporg' ),
			'add_new_item'          => __( 'Create New Speaker', 'wordcamporg' ),
			'edit'                  => __( 'Edit', 'wordcamporg' ),
			'edit_item'             => __( 'Edit Speaker', 'wordcamporg' ),
			'new_item'              => __( 'New Speaker', 'wordcamporg' ),
			'view'                  => __( 'View Speaker', 'wordcamporg' ),
			'view_item'             => __( 'View Speaker', 'wordcamporg' ),
			'search_items'          => __( 'Search Speakers', 'wordcamporg' ),
			'not_found'             => __( 'No speakers found', 'wordcamporg' ),
			'not_found_in_trash'    => __( 'No speakers found in Trash', 'wordcamporg' ),
			'parent_item_colon'     => __( 'Parent Speaker:', 'wordcamporg' ),
		);

		// Register speaker post type.
		register_post_type( 'wcb_speaker', array(
			'labels'            => $labels,
			'rewrite'           => array( 'slug' => 'speaker', 'with_front' => true ),
			'supports'          => array( 'title', 'editor', 'revisions', 'comments' ),
			'menu_position'     => 20,
			'public'            => true,
			'show_ui'           => true,
			'can_export'        => true,
			'capability_type'   => 'post',
			'hierarchical'      => false,
			'query_var'         => true,
			'menu_icon'         => 'dashicons-megaphone',
		) );

		// Session post type labels.
		$labels = array(
			'name'                  => __( 'Sessions', 'wordcamporg' ),
			'singular_name'         => __( 'Session', 'wordcamporg' ),
			'add_new'               => __( 'Add New', 'wordcamporg' ),
			'add_new_item'          => __( 'Create New Session', 'wordcamporg' ),
			'edit'                  => __( 'Edit', 'wordcamporg' ),
			'edit_item'             => __( 'Edit Session', 'wordcamporg' ),
			'new_item'              => __( 'New Session', 'wordcamporg' ),
			'view'                  => __( 'View Session', 'wordcamporg' ),
			'view_item'             => __( 'View Session', 'wordcamporg' ),
			'search_items'          => __( 'Search Sessions', 'wordcamporg' ),
			'not_found'             => __( 'No sessions found', 'wordcamporg' ),
			'not_found_in_trash'    => __( 'No sessions found in Trash', 'wordcamporg' ),
			'parent_item_colon'     => __( 'Parent Session:', 'wordcamporg' ),
		);

		// Register session post type.
		register_post_type( 'wcb_session', array(
			'labels'            => $labels,
			'rewrite'           => array( 'slug' => 'session', 'with_front' => false ),
			'supports'          => array( 'title', 'editor', 'revisions', 'thumbnail' ),
			'menu_position'     => 21,
			'public'            => true,
			'show_ui'           => true,
			'can_export'        => true,
			'capability_type'   => 'post',
			'hierarchical'      => false,
			'query_var'         => true,
			'menu_icon'         => 'dashicons-schedule',
		) );

		// Sponsor post type labels.
		$labels = array(
			'name'                  => __( 'Sponsors', 'wordcamporg' ),
			'singular_name'         => __( 'Sponsor', 'wordcamporg' ),
			'add_new'               => __( 'Add New', 'wordcamporg' ),
			'add_new_item'          => __( 'Create New Sponsor', 'wordcamporg' ),
			'edit'                  => __( 'Edit', 'wordcamporg' ),
			'edit_item'             => __( 'Edit Sponsor', 'wordcamporg' ),
			'new_item'              => __( 'New Sponsor', 'wordcamporg' ),
			'view'                  => __( 'View Sponsor', 'wordcamporg' ),
			'view_item'             => __( 'View Sponsor', 'wordcamporg' ),
			'search_items'          => __( 'Search Sponsors', 'wordcamporg' ),
			'not_found'             => __( 'No sponsors found', 'wordcamporg' ),
			'not_found_in_trash'    => __( 'No sponsors found in Trash', 'wordcamporg' ),
			'parent_item_colon'     => __( 'Parent Sponsor:', 'wordcamporg' ),
		);

		// Register sponsor post type.
		register_post_type( 'wcb_sponsor', array(
			'labels'            => $labels,
			'rewrite'           => array( 'slug' => 'sponsor', 'with_front' => false ),
			'supports'          => array( 'title', 'editor', 'revisions', 'thumbnail' ),
			'menu_position'     => 21,
			'public'            => true,
			'show_ui'           => true,
			'can_export'        => true,
			'capability_type'   => 'post',
			'hierarchical'      => false,
			'query_var'         => true,
			'menu_icon'         => 'dashicons-heart',
		) );

		// Organizer post type labels.
		$labels = array(
			'name'                  => __( 'Organizers', 'wordcamporg' ),
			'singular_name'         => __( 'Organizer', 'wordcamporg' ),
			'add_new'               => __( 'Add New', 'wordcamporg' ),
			'add_new_item'          => __( 'Create New Organizer', 'wordcamporg' ),
			'edit'                  => __( 'Edit', 'wordcamporg' ),
			'edit_item'             => __( 'Edit Organizer', 'wordcamporg' ),
			'new_item'              => __( 'New Organizer', 'wordcamporg' ),
			'view'                  => __( 'View Organizer', 'wordcamporg' ),
			'view_item'             => __( 'View Organizer', 'wordcamporg' ),
			'search_items'          => __( 'Search Organizers', 'wordcamporg' ),
			'not_found'             => __( 'No organizers found', 'wordcamporg' ),
			'not_found_in_trash'    => __( 'No organizers found in Trash', 'wordcamporg' ),
			'parent_item_colon'     => __( 'Parent Organizer:', 'wordcamporg' ),
		);

		// Register organizer post type.
		register_post_type( 'wcb_organizer', array(
			'labels'            => $labels,
			'rewrite'           => array( 'slug' => 'organizer', 'with_front' => false ),
			'supports'          => array( 'title', 'editor', 'revisions' ),
			'menu_position'     => 22,
			'public'            => false,
				// todo public or publicly_queryable = true, so consistent with others? at the very least set show_in_json = true
			'show_ui'           => true,
			'can_export'        => true,
			'capability_type'   => 'post',
			'hierarchical'      => false,
			'query_var'         => true,
			'menu_icon'         => 'dashicons-groups',
		) );
	}

	/**
	 * Registers custom taxonomies to post types.
	 */
	function register_taxonomies() {
		// Labels for tracks.
		$labels = array(
			'name'              => __( 'Tracks', 'wordcamporg' ),
			'singular_name'     => __( 'Track', 'wordcamporg' ),
			'search_items'      => __( 'Search Tracks', 'wordcamporg' ),
			'popular_items'     => __( 'Popular Tracks','wordcamporg' ),
			'all_items'         => __( 'All Tracks', 'wordcamporg' ),
			'edit_item'         => __( 'Edit Track', 'wordcamporg' ),
			'update_item'       => __( 'Update Track', 'wordcamporg' ),
			'add_new_item'      => __( 'Add Track', 'wordcamporg' ),
			'new_item_name'     => __( 'New Track', 'wordcamporg' ),
		);

		// Register the Tracks taxonomy.
		register_taxonomy( 'wcb_track', 'wcb_session', array(
			'labels'                => $labels,
			'rewrite'               => array( 'slug' => 'track' ),
			'query_var'             => 'track',
			'hierarchical'          => true,
			'public'                => true,
			'show_ui'               => true,
		) );

		// Labels for sponsor levels.
		$labels = array(
			'name'              => __( 'Sponsor Levels', 'wordcamporg' ),
			'singular_name'     => __( 'Sponsor Level', 'wordcamporg' ),
			'search_items'      => __( 'Search Sponsor Levels', 'wordcamporg' ),
			'popular_items'     => __( 'Popular Sponsor Levels', 'wordcamporg' ),
			'all_items'         => __( 'All Sponsor Levels', 'wordcamporg' ),
			'edit_item'         => __( 'Edit Sponsor Level', 'wordcamporg' ),
			'update_item'       => __( 'Update Sponsor Level', 'wordcamporg' ),
			'add_new_item'      => __( 'Add Sponsor Level', 'wordcamporg' ),
			'new_item_name'     => __( 'New Sponsor Level', 'wordcamporg' ),
		);

		// Register sponsor level taxonomy
		register_taxonomy( 'wcb_sponsor_level', 'wcb_sponsor', array(
			'labels'                => $labels,
			'rewrite'               => array( 'slug' => 'sponsor_level' ),
			'query_var'             => 'sponsor_level',
			'hierarchical'          => true,
			'public'                => true,
			'show_ui'               => true,
		) );
	}

	/**
	 * Filters our custom post types columns. Instead of creating a filter for each
	 * post type, we applied the same callback function to the post types we want to
	 * override.
	 *
	 * @uses current_filter()
	 * @see __construct()
	 */
	function manage_post_types_columns( $columns ) {
		$current_filter = current_filter();

		switch ( $current_filter ) {
			case 'manage_wcb_organizer_posts_columns':
				// Insert at offset 1, that's right after the checkbox.
				$columns = array_slice( $columns, 0, 1, true ) + array( 'wcb_organizer_avatar' => __( 'Avatar', 'wordcamporg' ) )   + array_slice( $columns, 1, null, true );
				break;

			case 'manage_wcb_speaker_posts_columns':
				$original_columns = $columns;

				$columns =  array_slice( $original_columns, 0, 1, true );
				$columns += array( 'wcb_speaker_avatar' => __( 'Avatar', 'wordcamporg' ) );
				$columns += array_slice( $original_columns, 1, 1, true );
				$columns += array(
					'wcb_speaker_email'          => __( 'Gravatar Email',         'wordcamporg' ),
					'wcb_speaker_wporg_username' => __( 'WordPress.org Username', 'wordcamporg' ),
				);
				$columns += array_slice( $original_columns, 2, null, true );

				break;

			case 'manage_wcb_session_posts_columns':
				$columns = array_slice( $columns, 0, 2, true ) + array( 'wcb_session_speakers' => __( 'Speakers', 'wordcamporg' ) ) + array_slice( $columns, 2, null, true );
				$columns = array_slice( $columns, 0, 1, true ) + array( 'wcb_session_time'     => __( 'Time', 'wordcamporg' ) )     + array_slice( $columns, 1, null, true );
				break;
			default:
		}

		return $columns;
	}

	/**
	 * Custom columns output
	 *
	 * This generates the output to the extra columns added to the posts lists in the admin.
	 *
	 * @see manage_post_types_columns()
	 */
	function manage_post_types_columns_output( $column, $post_id ) {
		switch ( $column ) {
			case 'wcb_organizer_avatar':
				edit_post_link( get_avatar( absint( get_post_meta( get_the_ID(), '_wcpt_user_id', true ) ), 32 ) );
				break;

			case 'wcb_speaker_avatar':
				edit_post_link( get_avatar( get_post_meta( get_the_ID(), '_wcb_speaker_email', true ), 32 ) );
				break;

			case 'wcb_speaker_email':
				echo esc_html( get_post_meta( get_the_ID(), '_wcb_speaker_email', true ) );
				break;

			case 'wcb_speaker_wporg_username':
				$user_id    = get_post_meta( get_the_ID(), '_wcpt_user_id', true );
				$wporg_user = get_user_by( 'id', $user_id );

				if ( $wporg_user ) {
					echo esc_html( $wporg_user->user_nicename );
				}

				break;

			case 'wcb_session_speakers':
				$speakers = array();
				$speakers_ids = array_map( 'absint', (array) get_post_meta( $post_id, '_wcpt_speaker_id' ) );
				if ( ! empty( $speakers_ids ) ) {
					$speakers = get_posts( array(
						'post_type' => 'wcb_speaker',
						'posts_per_page' => -1,
						'post__in' => $speakers_ids,
					) );
				}

				$output = array();
				foreach ( $speakers as $speaker ) {
					$output[] = sprintf( '<a href="%s">%s</a>', esc_url( get_edit_post_link( $speaker->ID ) ), esc_html( apply_filters( 'the_title', $speaker->post_title ) ) );
				}
				echo implode( ', ', $output );

				break;

			case 'wcb_session_time':
				$session_time = absint( get_post_meta( get_the_ID(), '_wcpt_session_time', true ) );
				$session_time = ( $session_time ) ? date( get_option( 'time_format' ), $session_time ) : '&mdash;';
				echo esc_html( $session_time );
				break;

			default:
		}
	}

	/**
	 * Additional sortable columns for WP_Posts_List_Table
	 */
	function manage_sortable_columns( $sortable ) {
		$current_filter = current_filter();

		if ( 'manage_edit-wcb_session_sortable_columns' == $current_filter )
			$sortable['wcb_session_time'] = '_wcpt_session_time';

		return $sortable;
	}

	/**
	 * Display an additional post label if needed.
	 */
	function display_post_states( $states ) {
		$post = get_post();

		if ( 'wcb_session' != $post->post_type )
			return $states;

		$session_type = get_post_meta( $post->ID, '_wcpt_session_type', true );
		if ( ! in_array( $session_type, array( 'session', 'custom' ) ) )
			$session_type = 'session';

		if ( 'session' == $session_type )
			$states['wcpt-session-type'] = __( 'Session', 'wordcamporg' );
		elseif ( 'custom' == $session_type )
			$states['wcpt-session-type'] = __( 'Custom', 'wordcamporg' );

		return $states;
	}

	/**
	 * Register some widgets.
	 */
	function register_widgets() {
		require_once( 'inc/widgets.php' );

		register_widget( 'WCB_Widget_Sponsors'    );
		register_widget( 'WCPT_Widget_Speakers'   );
		register_widget( 'WCPT_Widget_Sessions'   );
		register_widget( 'WCPT_Widget_Organizers' );
	}

	/**
	 * Add post types to 'At a Glance' dashboard widget
	 */
	function glance_items( $items = array() ) {
		$post_types = array( 'wcb_speaker', 'wcb_session', 'wcb_sponsor' );

		foreach ( $post_types as $post_type ) {

			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$num_posts        = wp_count_posts( $post_type );
			$post_type_object = get_post_type_object( $post_type );

			if ( $num_posts && $num_posts->publish ) {

				switch ( $post_type ) {
					case 'wcb_speaker':
						$text = $text = _n( '%s Speaker', '%s Speakers', $num_posts->publish );
						break;
					case 'wcb_session':
						$text = $text = _n( '%s Session', '%s Sessions', $num_posts->publish );
						break;
					case 'wcb_sponsor':
						$text = $text = _n( '%s Sponsor', '%s Sponsors', $num_posts->publish );
						break;
					default:
				}

				$text = sprintf( $text, number_format_i18n( $num_posts->publish ) );

				if ( current_user_can( $post_type_object->cap->edit_posts ) ) {
					$items[] = sprintf( '<a class="%1$s-count" href="edit.php?post_type=%1$s">%2$s</a>', $post_type, $text ) . "\n";
				} else {
					$items[] = sprintf( '<span class="%1$s-count">%2$s</span>', $post_type, $text ) . "\n";
				}
			}
		}

		return $items;
	}

	/**
	 * Comments and pings on speakers closed by default.
	 *
	 * @param string $status Default comment status.
	 * @return string Resulting status.
	 */
	public function default_comment_ping_status( $status ) {
		$screen = get_current_screen();
		if ( ! empty( $screen->post_type ) && $screen->post_type == 'wcb_speaker' )
			$status = 'closed';

		return $status;
	}
}

// Load the plugin class.
$GLOBALS['wcpt_plugin'] = new WordCamp_Post_Types_Plugin;
