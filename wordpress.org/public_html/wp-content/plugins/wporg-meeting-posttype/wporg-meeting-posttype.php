<?php
/*
Plugin Name: WPORG Make Homepage Meeting Post Type
Description: Creates the meeting post type and assorted filters for https://make.wordpress.org/meetings
Version:     1.0
License:     GPLv2 or later
Author:      WordPress.org
Author URI:  http://wordpress.org/
Text Domain: wporg
*/

if ( !class_exists('Meeting_Post_Type') ):
class Meeting_Post_Type {

	protected static $instance = NULL;

	public static function getInstance() {
		NULL === self::$instance and self::$instance = new self;
		return self::$instance;
	}

	public static function init() {
		$mpt = Meeting_Post_Type::getInstance();
		add_action( 'init',                               array( $mpt, 'register_meeting_post_type' ) );
		add_action( 'save_post_meeting',                  array( $mpt, 'save_meta_boxes' ), 10, 2 );
		add_filter( 'pre_get_posts',                      array( $mpt, 'meeting_archive_page_query' ) );
		add_filter( 'the_posts',                          array( $mpt, 'meeting_set_next_meeting' ), 10, 2 );
		add_filter( 'manage_meeting_posts_columns',       array( $mpt, 'meeting_add_custom_columns' ) );
		add_action( 'manage_meeting_posts_custom_column', array( $mpt, 'meeting_custom_columns' ), 10, 2 );
		add_action( 'admin_head',                         array( $mpt, 'meeting_column_width' ) );
		add_action( 'admin_bar_menu',                     array( $mpt, 'add_edit_meetings_item_to_admin_bar' ), 80 );
		add_action( 'wp_enqueue_scripts',                 array( $mpt, 'add_edit_meetings_icon_to_admin_bar' ) );
		add_shortcode( 'meeting_time',                    array( $mpt, 'meeting_time_shortcode' ) );
	}

	public function meeting_column_width() { ?>
		<style type="text/css">
			.column-team { width: 10em !important; overflow: hidden; }
			#meeting-info .recurring label { padding-right: 10px; }
		</style>
		<?php
	}

	public function meeting_add_custom_columns( $columns ) {
		$columns = array_slice( $columns, 0, 1, true )
			+ array( 'team' => __('Team', 'wporg') )
			+ array_slice( $columns, 1, null, true );
		return $columns;
	}

	public function meeting_custom_columns( $column, $post_id ) {
		switch ( $column ) {
		case 'team' :
			$team = get_post_meta( $post_id, 'team', true );
			echo esc_html( $team );
			break;
		}
	}

	public function meeting_archive_page_query( $query ) {
		if ( is_admin() || ! $query->is_main_query() || ! $query->is_post_type_archive( 'meeting' ) ) {
			return;
		}
		// turn off paging on the archive page, to show all meetings in the table
		$query->set( 'nopaging', true );

		// meta query to eliminate expired meetings from query
		$query->set( 'meta_query', $this->meeting_meta_query );

		// WP doesn't understand CURDATE() and prepares it as a quoted string. Repair this:
		add_filter( 'get_meta_sql', function ($sql) {
			return str_replace( "'CURDATE()'", 'CURDATE()', $sql );
		} );

	}

	public function meeting_set_next_meeting( $posts, $query ) {
		if ( !$query->is_post_type_archive( 'meeting' ) ) {
			return $posts;
		}

		// for each entry, set a fake meta value to show the next date for recurring meetings
		array_walk( $posts, function ( &$post ) {
			if ( 'weekly' === $post->recurring || '1' === $post->recurring ) {
				try {
					// from the start date, advance the week until it's past now
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings
					$now   = new DateTime( '-30 minutes' );

					if ( $next < $now ) {
						$interval = $start->diff( $now );
						// add one to days to account for events that happened earlier today
						$weekdiff = ceil( ( $interval->days + 1 ) / 7 );
						$next->modify( '+ ' . $weekdiff . ' weeks' );
					}

					$post->next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					// if the datetime is invalid, then set the post->next_date to the start date instead
					$post->next_date = $post->start_date;
				}
			} else if ( 'biweekly' === $post->recurring ) {
				try {
					// advance the start date 2 weeks at a time until it's past now
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings
					$now   = new DateTime( '-30 minutes' );

					while ( $next < $now ) {
						$next->modify( '+2 weeks' );
					}

					$post->next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					// if the datetime is invalid, then set the post->next_date to the start date instead
					$post->next_date = $post->start_date;
				}
			} else if ( 'occurrence' === $post->recurring ) {
				try {
					// advance the occurrence day in the current month until it's past now
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings
					$now   = new DateTime( '-30 minutes' );

					$day_index = date( 'w', strtotime( sprintf( '%s %s GMT', $post->start_date, $post->time ) ) );
					$day_name  = $GLOBALS['wp_locale']->get_weekday( $day_index );
					$numerals  = array( 'first', 'second', 'third', 'fourth' );
					$months    = array( 'this month', 'next month' );

					foreach ( $months as $month ) {
						foreach ( $post->occurrence as $index ) {
							$next = new DateTime( sprintf( '%s %s of %s %s GMT', $numerals[ $index - 1 ], $day_name, $month, $post->time ) );
							if ( $next > $now ) {
								break 2;
							}
						}
					}

					$post->next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					// if the datetime is invalid, then set the post->next_date to the start date instead
					$post->next_date = $post->start_date;
				}
			} else if ( 'monthly' === $post->recurring ) {
				try {
					// advance the start date 1 month at a time until it's past now
					$start = new DateTime( sprintf( '%s %s GMT', $post->start_date, $post->time ) );
					$next  = $start;
					// minus 30 minutes to account for currently ongoing meetings
					$now   = new DateTime( '-30 minutes' );

					while ( $next < $now ) {
						$next->modify( '+1 month' );
					}

					$post->next_date = $next->format( 'Y-m-d' );
				} catch ( Exception $e ) {
					// if the datetime is invalid, then set the post->next_date to the start date instead
					$post->next_date = $post->start_date;
				}
			} else {
				$post->next_date = $post->start_date;
			}
		});

		// reorder the posts by next_date + time
		usort( $posts, function ($a, $b) {
			$adate = strtotime( $a->next_date . ' ' . $a->time );
			$bdate = strtotime( $b->next_date . ' ' . $b->time );
			if ( $adate == $bdate ) {
				return 0;
			}
			return ( $adate < $bdate ) ? -1 : 1;
		});

		return $posts;
	}

	public function register_meeting_post_type() {
	    $labels = array(
	        'name'                => _x( 'Meetings', 'Post Type General Name', 'wporg' ),
	        'singular_name'       => _x( 'Meeting', 'Post Type Singular Name', 'wporg' ),
	        'menu_name'           => __( 'Meetings', 'wporg' ),
	        'name_admin_bar'      => __( 'Meeting', 'wporg' ),
	        'parent_item_colon'   => __( 'Parent Meeting:', 'wporg' ),
	        'all_items'           => __( 'All Meetings', 'wporg' ),
	        'add_new_item'        => __( 'Add New Meeting', 'wporg' ),
	        'add_new'             => __( 'Add New', 'wporg' ),
	        'new_item'            => __( 'New Meeting', 'wporg' ),
	        'edit_item'           => __( 'Edit Meeting', 'wporg' ),
	        'update_item'         => __( 'Update Meeting', 'wporg' ),
	        'view_item'           => __( 'View Meeting', 'wporg' ),
	        'view_items'          => __( 'View Meetings', 'wporg' ),
	        'search_items'        => __( 'Search Meeting', 'wporg' ),
	        'not_found'           => __( 'Not found', 'wporg' ),
	        'not_found_in_trash'  => __( 'Not found in Trash', 'wporg' ),
	    );
	    $args = array(
	        'label'               => __( 'meeting', 'wporg' ),
	        'description'         => __( 'Meeting', 'wporg' ),
	        'labels'              => $labels,
	        'supports'            => array( 'title' ),
	        'hierarchical'        => false,
	        'public'              => true,
	        'show_ui'             => true,
	        'show_in_menu'        => true,
	        'menu_position'       => 20,
	        'menu_icon'           => 'dashicons-calendar',
	        'show_in_admin_bar'   => true,
	        'show_in_nav_menus'   => false,
	        'can_export'          => false,
	        'has_archive'         => true,
	        'exclude_from_search' => true,
	        'publicly_queryable'  => true,
	        'capability_type'     => 'post',
			'register_meta_box_cb'=> array( $this, 'add_meta_boxes' ),
			'rewrite'             => array(
				'with_front'      => false,
				'slug'            => __( 'meetings', 'wporg' ),
			),
	    );
		register_post_type( 'meeting', $args );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'meeting-info',
			'Meeting Info',
			array( $this, 'render_meta_boxes' ),
			'meeting',
			'normal',
			'high'
		);
	}

	function render_meta_boxes( $post ) {
		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'jquery-ui-style', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.11.4/themes/smoothness/jquery-ui.css', true);

		$meta       = get_post_custom( $post->ID );
		$team       = isset( $meta['team'][0] ) ? $meta['team'][0] : '';
		$start      = isset( $meta['start_date'][0] ) ? $meta['start_date'][0] : '';
		$end        = isset( $meta['end_date'][0] ) ? $meta['end_date'][0] : '';
		$time       = isset( $meta['time'][0] ) ? $meta['time'][0] : '';
		$recurring  = isset( $meta['recurring'][0] ) ? $meta['recurring'][0] : '';
		if ( '1' === $recurring ) {
			$recurring = 'weekly';
		}
		$occurrence = isset( $meta['occurrence'][0] ) ? unserialize( $meta['occurrence'][0] ) : array();
		$link       = isset( $meta['link'][0] ) ? $meta['link'][0] : '';
		$location   = isset( $meta['location'][0] ) ? $meta['location'][0] : '';
		wp_nonce_field( 'save_meeting_meta_'.$post->ID , 'meeting_nonce' );
		?>

		<p>
		<label for="team">
			<?php _e( 'Team: ', 'wporg' ); ?>
			<input type="text" id="team" name="team" class="regular-text wide" value="<?php echo esc_attr( $team ); ?>">
		</label>
		</p>
		<p>
		<label for="start_date">
			<?php _e( 'Start Date', 'wporg' ); ?>
			<input type="text" name="start_date" id="start_date" class="date" value="<?php echo esc_attr( $start ); ?>">
		</label>
		<label for="end_date">
			<?php _e( 'End Date', 'wporg' ); ?>
			<input type="text" name="end_date" id="end_date" class="date" value="<?php echo esc_attr( $end ); ?>">
		</label>
		</p>
		<p>
		<label for="time">
			<?php _e( 'Time (UTC)', 'wporg' ); ?>
			<input type="text" name="time" id="time" class="time" value="<?php echo esc_attr( $time ); ?>">
		</label>
		</p>
		<p class="recurring">
		<?php _e( 'Recurring: ', 'wporg' ); ?><br />
		<label for="weekly">
			<input type="radio" name="recurring" value="weekly" id="weekly" class="regular-radio" <?php checked( $recurring, 'weekly' ); ?>>
			<?php _e( 'Weekly', 'wporg' ); ?>
		</label><br />

		<label for="biweekly">
			<input type="radio" name="recurring" value="biweekly" id="biweekly" class="regular-radio" <?php checked( $recurring, 'biweekly' ); ?>>
			<?php _e( 'Biweekly', 'wporg' ); ?>
		</label><br />

		<label for="occurrence">
			<input type="radio" name="recurring" value="occurrence" id="occurrence" class="regular-radio" <?php checked( $recurring, 'occurrence' ); ?>>
			<?php _e( 'Occurrence in a month:', 'wporg' ); ?>
		</label>
		<label for="week-1">
			<input type="checkbox" name="occurrence[]" value="1" id="week-1" <?php checked( in_array( 1, $occurrence ) ); ?>>
			<?php _e( '1st', 'wporg' ); ?>
		</label>
		<label for="week-2">
			<input type="checkbox" name="occurrence[]" value="2" id="week-2" <?php checked( in_array( 2, $occurrence ) ); ?>>
			<?php _e( '2nd', 'wporg' ); ?>
		</label>
		<label for="week-3">
			<input type="checkbox" name="occurrence[]" value="3" id="week-3" <?php checked( in_array( 3, $occurrence ) ); ?>>
			<?php _e( '3rd', 'wporg' ); ?>
		</label>
		<label for="week-4">
			<input type="checkbox" name="occurrence[]" value="4" id="week-4" <?php checked( in_array( 4, $occurrence ) ); ?>>
			<?php _e( '4th', 'wporg' ); ?>
		</label><br />

		<label for="monthly">
			<input type="radio" name="recurring" value="monthly" id="monthly" class="regular-radio" <?php checked( $recurring, 'monthly' ); ?>>
			<?php _e( 'Monthly', 'wporg' ); ?>
		</label>
		</p>
		<p>
		<label for="link"><?php _e( 'Link: ', 'wporg' ); ?>
			<input type="text" name="link" id="link" class="regular-text wide" value="<?php echo esc_url( $link ); ?>">
		</label>
		</p>
		<p>
		<label for="location"><?php _e( 'Location: ', 'wporg' ); ?>
			<input type="text" name="location" id="location" class="regular-text wide" value="<?php echo esc_attr( $location ); ?>">
		</label>
		</p>
		<script>
		jQuery(document).ready( function($) {
			$('.date').datepicker({
				dateFormat: 'yy-mm-dd'
			});

			$('input[name="recurring"]').change( function() {
				var disabled = ( 'occurrence' !== $(this).val() );
				$('#meeting-info').find('[name^="occurrence"]').prop('disabled', disabled);
			});

			if ( 'occurrence' !== $('input[name="recurring"]:checked').val() ) {
				$('#meeting-info').find('[name^="occurrence"]').prop('disabled', true);
			}
		});
		</script>
	<?php
	}

	function save_meta_boxes( $post_id ) {

		global $post;

		// Verify nonce
		if ( !isset( $_POST['meeting_nonce'] ) || !wp_verify_nonce( $_POST['meeting_nonce'], 'save_meeting_meta_'.$post_id ) ) {
			return $post_id;
		}

		// Check autosave
		if ( (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || ( defined('DOING_AJAX') && DOING_AJAX) || isset($_REQUEST['bulk_edit']) ) {
			return $post_id;
		}

		// Don't save for revisions
		if ( isset( $post->post_type ) && 'revision' === $post->post_type ) {
			return $post_id;
		}

		// Check permissions
		if ( !current_user_can( 'edit_post', $post->ID ) ) {
			return $post_id;
		}

		$meta['team']        = ( isset( $_POST['team'] ) ? esc_textarea( $_POST['team'] ) : '' );
		$meta['start_date']  = ( isset( $_POST['start_date'] ) ? esc_textarea( $_POST['start_date'] ) : '' );
		$meta['end_date']    = ( isset( $_POST['end_date'] ) ? esc_textarea( $_POST['end_date'] ) : '' );
		$meta['time']        = ( isset( $_POST['time'] ) ? esc_textarea( $_POST['time'] ) : '' );
		$meta['recurring']   = ( isset( $_POST['recurring'] )
		                         && in_array( $_POST['recurring'], array( 'weekly', 'biweekly', 'occurrence', 'monthly' ) )
		                         ? ( $_POST['recurring'] ) : '' );
		$meta['occurrence']  = ( isset( $_POST['occurrence'] ) && 'occurrence' === $meta['recurring']
		                         && is_array( $_POST['occurrence'] )
		                         ? array_map( 'intval', $_POST['occurrence'] ) : array() );
		$meta['link']        = ( isset( $_POST['link'] ) ? esc_url( $_POST['link'] ) : '' );
		$meta['location']    = ( isset( $_POST['location'] ) ? esc_textarea( $_POST['location'] ) : '' );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post->ID, $key, $value );
		}
	}

	/**
	 * Adds "Edit Meetings" item after "Add New" menu.
	 *
	 * @param \WP_Admin_Bar $wp_admin_bar The admin bar instance.
	 */
	public function add_edit_meetings_item_to_admin_bar( $wp_admin_bar ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		if ( is_admin() || ! is_post_type_archive( 'meeting' ) ) {
			return;
		}

		$wp_admin_bar->add_menu(
			array(
				'id'    => 'edit-meetings',
				'title' => '<span class="ab-icon"></span>' . __( 'Edit Meetings', 'wporg' ),
				'href'  => admin_url( 'edit.php?post_type=meeting' ),
			)
		);
	}

	/**
	 * Adds icon for the "Edit Meetings" item.
	 */
	public function add_edit_meetings_icon_to_admin_bar() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return;
		}

		wp_add_inline_style( 'admin-bar', '
			#wpadminbar #wp-admin-bar-edit-meetings .ab-icon:before {
				content: "\f145";
				top: 2px;
			}
		' );
	}

	/**
	 * Renders meeting information with the next meeting time based on user's local timezone. Used in Make homepage.
	 */
	public function meeting_time_shortcode( $attr, $content = '' ) {

		$attr = shortcode_atts( array(
			'team' => null,
			'limit' => 1,
			'before' => __( 'Next meeting: ', 'wporg' ),
			'titletag' => 'strong',
			'more' => true,
		), $attr );

		if ( empty( $attr['team'] ) ) {
			return '';
		}

		if ( $attr['team'] === 'Documentation' ) {
			$attr['team'] = 'Docs';
		}

		if ( ! has_action( 'wp_footer', array( $this, 'time_conversion_script' ) ) ) {
			add_action( 'wp_footer', array( $this, 'time_conversion_script' ), 999 );
		}


		// meta query to eliminate expired meetings from query
		add_filter( 'get_meta_sql', function ($sql) {
			return str_replace( "'CURDATE()'", 'CURDATE()', $sql );
		} );

		switch_to_blog( get_main_site_id() );

		$query = new WP_Query(
			array(
				'post_type' => 'meeting',
				'nopaging'  => true,
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key'     => 'team',
						'value'   => $attr['team'],
						'compare' => 'EQUALS',
					),
					$this->meeting_meta_query
				)
			)
		);

		$limit = $attr['limit'] > 0 ? $attr['limit'] : count( $query->posts );

		$out = '';
		foreach ( array_slice( $query->posts, 0, $limit ) as $post ) {
			$next_meeting_datestring = $post->next_date;
			$utc_time = strftime( '%H:%M:%S', strtotime( $post->time ) );
			$next_meeting_iso        = $next_meeting_datestring . 'T' . $utc_time . '+00:00';
			$next_meeting_timestamp = strtotime( $next_meeting_datestring . ' '. $utc_time );
			$next_meeting_display = strftime( '%c %Z', $next_meeting_timestamp );

			$slack_channel = null;
			if ( $post->location && preg_match( '/^#([-\w]+)$/', trim( $post->location ), $match ) ) {
				$slack_channel = sanitize_title( $match[1] );
			}

			$out .= '<p>';
			$out .= esc_html( $attr['before'] );
			$out .= '<strong class="meeting-title">' . esc_html( $post->post_title ) . '</strong>';
			$display_more = $query->found_posts - intval( $limit );
			if ( $display_more > 0 ) {
				$out .= ' <a title="Click to view all meetings for this team" href="/meetings/#' . esc_attr( strtolower( $attr['team'] ) ) . '">' . sprintf( __( '(+%s more)'), $display_more ) . '</a>';
			}
			$out .= '</br>';
			$out .= '<time class="date" date-time="' . esc_attr( $next_meeting_iso ) . '" title="' . esc_attr( $next_meeting_iso ) . '">' . $next_meeting_display . '</time> ';
			$out .= sprintf( esc_html__( '(%s from now)' ), human_time_diff( $next_meeting_timestamp, current_time('timestamp') ) );
			if ( $post->location && $slack_channel ) {
				$out .= ' ' . sprintf( wp_kses( __('at <a href="%s">%s</a> on Slack'), array(  'a' => array( 'href' => array() ) ) ), 'https://wordpress.slack.com/messages/' . $slack_channel,   $post->location );
			}
			$out .= '</p>';
		}

		restore_current_blog();

		return $out;
	}

	private $meeting_meta_query = array(
		'relation'=>'OR',
			// not recurring  AND start_date >= CURDATE() = one-time meeting today or still in future
			array(
				'relation'=>'AND',
				array(
					'key'=>'recurring',
					'value'=>array( 'weekly', 'biweekly', 'occurrence', 'monthly', '1' ),
					'compare'=>'NOT IN',
				),
				array(
					'key'=>'start_date',
					'type'=>'DATE',
					'compare'=>'>=',
					'value'=>'CURDATE()',
				)
			),
			// recurring = 1 AND ( end_date = '' OR end_date > CURDATE() ) = recurring meeting that has no end or has not ended yet
			array(
				'relation'=>'AND',
				array(
					'key'=>'recurring',
					'value'=>array( 'weekly', 'biweekly', 'occurrence', 'monthly', '1' ),
					'compare'=>'IN',
				),
				array(
					'relation'=>'OR',
					array(
						'key'=>'end_date',
						'value'=>'',
						'compare'=>'=',
					),
					array(
						'key'=>'end_date',
						'type'=>'DATE',
						'compare'=>'>',
						'value'=>'CURDATE()',
					)
				)
			),
		);

	public function time_conversion_script() {
		echo <<<EOF
<script type="text/javascript">

	var parse_date = function (text) {
		var m = /^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})\+00:00$/.exec(text);
		var d = new Date();
		d.setUTCFullYear(+m[1]);
		d.setUTCDate(+m[3]);
		d.setUTCMonth(+m[2]-1);
		d.setUTCHours(+m[4]);
		d.setUTCMinutes(+m[5]);
		d.setUTCSeconds(+m[6]);
		return d;
	}
	var format_time = function (d) {
		return d.toLocaleTimeString(navigator.language, {weekday: 'long', hour: '2-digit', minute: '2-digit', timeZoneName: 'short'});
	}

	var nodes = document.getElementsByTagName('time');
	for (var i=0; i<nodes.length; ++i) {
		var node = nodes[i];
		if (node.className === 'date') {
			var d = parse_date(node.getAttribute('date-time'));
			if (d) {
				node.textContent = format_time(d);
			}
		}
	}
</script>
EOF;
	}
}

// fire it up
Meeting_Post_Type::init();

endif;


