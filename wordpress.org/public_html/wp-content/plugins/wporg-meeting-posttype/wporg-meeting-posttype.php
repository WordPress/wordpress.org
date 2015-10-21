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
		add_action( 'init', array( $mpt, 'register_meeting_post_type' ) );
		add_filter( 'pre_get_posts', array( $mpt, 'meeting_archive_page_query' ) );
		add_filter( 'the_posts', array( $mpt, 'meeting_set_next_meeting' ), 10, 2 );
		add_filter( 'manage_meeting_posts_columns', array( $mpt, 'meeting_add_custom_columns' ) );
		add_action( 'manage_meeting_posts_custom_column' , array( $mpt, 'meeting_custom_columns' ), 10, 2 );
		add_action( 'admin_head', array( $mpt, 'meeting_column_width' ) );
	}

	public function meeting_column_width() {
		echo '<style type="text/css">.column-team { width:10em !important; overflow:hidden }</style>';
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
		if ( !$query->is_main_query() || !$query->is_post_type_archive( 'meeting' ) ) {
			return;
		}
		// turn off paging on the archive page, to show all meetings in the table
		$query->set( 'nopaging', true );

		// meta query to eliminate expired meetings from query
		$query->set( 'meta_query', array(
			'relation'=>'OR',
				// not recurring  AND start_date >= CURDATE() = one-time meeting today or still in future
				array(
					'relation'=>'AND',
					array(
						'key'=>'recurring',
						'value'=>array('weekly','monthly', '1'),
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
						'value'=>array('weekly', 'monthly', '1'),
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
			)
		);

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
			if ( $post->recurring == 'weekly' || $post->recurring === '1' ) {
				// from the start date, advance the week until it's past now
				$start = new DateTime( $post->start_date.' '.$post->time.' GMT' );
				$now = new DateTime();
				$interval = $start->diff($now);
				// add one to days to account for events that happened earlier today
				$weekdiff = ceil( ($interval->days+1) / 7 );
				$next = strtotime( "{$post->start_date} + {$weekdiff} weeks" );
				$post->next_date = date('Y-m-d', $next);
			} else if ( $post->recurring == 'monthly' ) {
				// advance the start date 1 month at a time until it's past now
				$start = new DateTime( $post->start_date.' '.$post->time.' GMT' );
				$next = $start;
				$now = new DateTime();
				while ( $now > $next ) {
					$next->modify('+1 month');
				}
				$post->next_date = $next->format('Y-m-d');
			}
			else {
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
	        'name_admin_bar'      => __( 'Meetings', 'wporg' ),
	        'parent_item_colon'   => __( 'Parent Meeting:', 'wporg' ),
	        'all_items'           => __( 'All Meetings', 'wporg' ),
	        'add_new_item'        => __( 'Add New Meeting', 'wporg' ),
	        'add_new'             => __( 'Add New', 'wporg' ),
	        'new_item'            => __( 'New Meeting', 'wporg' ),
	        'edit_item'           => __( 'Edit Meeting', 'wporg' ),
	        'update_item'         => __( 'Update Meeting', 'wporg' ),
	        'view_item'           => __( 'View Meeting', 'wporg' ),
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
	    add_action( 'save_post_meeting', array( $this, 'save_meta_boxes' ),  10, 2 );
	}

	public function add_meta_boxes() {
		add_meta_box(
			'meeting_info',
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

		$meta = get_post_custom( $post->ID );
		$team = ! isset( $meta['team'][0] ) ? '' : $meta['team'][0];
		$start = ! isset( $meta['start_date'][0] ) ? '' : $meta['start_date'][0];
		$end = ! isset( $meta['end_date'][0] ) ? '' : $meta['end_date'][0];
		$time = ! isset( $meta['time'][0] ) ? '' : $meta['time'][0];
		$recurring = ! isset( $meta['recurring'][0] ) ? '' : $meta['recurring'][0];
		if ( $recurring === '1' ) {
			$recurring = 'weekly';
		}
		$link = ! isset( $meta['link'][0] ) ? '' : $meta['link'][0];
		$location = ! isset( $meta['location'][0] ) ? '' : $meta['location'][0];
		wp_nonce_field( 'save_meeting_meta_'.$post->ID , 'meeting_nonce' );
		?>
		
		<p>
		<label for="team"><?php _e( 'Team: ', 'wporg' ); ?>
			<input type="text" id="team" name="team" class="regular-text wide" value="<?php echo esc_attr($team); ?>">
		</label>
		</p>
		<p>
		<label for="start_date"><?php _e( 'Start Date', 'wporg' ); ?>
			<input type="text" name="start_date" class="date" value="<?php echo esc_attr($start); ?>">
		</label>
		<label for="end_date"><?php _e( 'End Date', 'wporg' ); ?>
			<input type="text" name="end_date" class="date" value="<?php echo esc_attr($end); ?>">
		</label>
		</p>
		<p>
		<label for="time"><?php _e( 'Time (UTC)', 'wporg' ); ?>
			<input type="text" name="time" class="time" value="<?php echo esc_attr($time); ?>">
		</label>
		<label for="recurring"><?php _e( 'Recurring: ', 'wporg' ); ?>
			<label for="weekly"><?php _e( 'Weekly', 'wporg' ); ?></label>
			<input type="radio" name="recurring" value="weekly" class="regular-radio" <?php checked( $recurring, 'weekly' ); ?>>
			<label for="monthly"><?php _e( 'Monthly', 'wporg' ); ?></label>			
			<input type="radio" name="recurring" value="monthly" class="regular-radio" <?php checked( $recurring, 'monthly' ); ?>>
		</label>
		</p>
		<p>
		<label for="link"><?php _e( 'Link: ', 'wporg' ); ?>
			<input type="text" name="link" class="regular-text wide" value="<?php echo esc_url($link); ?>">
		</label>
		</p>
		<p>
		<label for="location"><?php _e( 'Location: ', 'wporg' ); ?>
			<input type="text" id="location" name="location" class="regular-text wide" value="<?php echo esc_attr($location); ?>">
		</label>
		</p>
		<script>
		jQuery(document).ready(function($){
			$('.date').datepicker({
				dateFormat: "yy-mm-dd"
			});
			$(document).on( 'keydown', '#title, #location', function( e ) {
				var keyCode = e.keyCode || e.which;
				if ( 9 == keyCode){
					e.preventDefault();
					var target = $(this).attr('id') == 'title' ? '#team' : 'textarea#content';
					if ( (target === '#team') || $('#wp-content-wrap').hasClass('html-active') ) {
						$(target).focus();
					} else {
						tinymce.execCommand('mceFocus',false,'content');
					}
				}
			});
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
		if ( isset( $post->post_type ) && $post->post_type == 'revision' ) {
			return $post_id;
		}

		// Check permissions
		if ( !current_user_can( 'edit_post', $post->ID ) ) {
			return $post_id;
		}

		$team = ! isset( $meta['team'][0] ) ? '' : $meta['team'][0];
		$start = ! isset( $meta['start_date'][0] ) ? '' : $meta['start_date'][0];
		$end = ! isset( $meta['end_date'][0] ) ? '' : $meta['end_date'][0];
		$recurring = ! isset( $meta['recurring'][0] ) ? '' : $meta['recurring'][0];
		$link = ! isset( $meta['link'][0] ) ? '' : $meta['link'][0];
		$location = ! isset( $meta['location'][0] ) ? '' : $meta['location'][0];
		
		$meta['team'] = ( isset( $_POST['team'] ) ? esc_textarea( $_POST['team'] ) : '' );
		$meta['start_date'] = ( isset( $_POST['start_date'] ) ? esc_textarea( $_POST['start_date'] ) : '' );
		$meta['end_date'] = ( isset( $_POST['end_date'] ) ? esc_textarea( $_POST['end_date'] ) : '' );
		$meta['time'] = ( isset( $_POST['time'] ) ? esc_textarea( $_POST['time'] ) : '' );
		$meta['recurring'] = ( isset ( $_POST['recurring'] ) && ( in_array( $_POST['recurring'], array('weekly', 'monthly') ) ) ? ( $_POST['recurring'] ) : '' );
		$meta['link'] = ( isset( $_POST['link'] ) ? esc_url( $_POST['link'] ) : '' );
		$meta['location'] = ( isset( $_POST['location'] ) ? esc_textarea( $_POST['location'] ) : '' );

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post->ID, $key, $value );
		}
	}
}

// fire it up
Meeting_Post_Type::init();

endif;


