<?php

if ( !class_exists( 'WordCamp_Admin' ) ) :
/**
 * WCPT_Admin
 *
 * Loads plugin admin area
 *
 * @package WordCamp Post Type
 * @subpackage Admin
 * @since WordCamp Post Type (0.1)
 */
class WordCamp_Admin {

	/**
	 * wcpt_admin ()
	 *
	 * Initialize WCPT Admin
	 */
	function WordCamp_Admin () {

		// Add some general styling to the admin area
		add_action( 'wcpt_admin_head', array( $this, 'admin_head' ) );

		// Forum column headers.
		add_filter( 'manage_' . WCPT_POST_TYPE_ID . '_posts_columns', array( $this, 'column_headers' ) );

		// Forum columns (in page row)
		add_action( 'manage_posts_custom_column', array( $this, 'column_data' ), 10, 2 );
		add_filter( 'post_row_actions', array( $this, 'post_row_actions' ), 10, 2 );

		// Topic metabox actions
		add_action( 'admin_menu', array( $this, 'metabox' ) );
		add_action( 'save_post', array( $this, 'metabox_save' ) );
		
		// Scripts and CSS
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_print_scripts', array( $this, 'admin_print_scripts' ), 99 );
		add_action( 'admin_print_styles', array( $this, 'admin_styles' ) );

		// Post status transitions
		add_action( 'transition_post_status', array( $this, 'add_organizer_to_central' ), 10, 3 );
		add_action( 'transition_post_status', array( $this, 'notify_mes_sponsors_when_wordcamp_scheduled' ), 10, 3 );
	}

	/**
	 * metabox ()
	 *
	 * Add the metabox
	 *
	 * @uses add_meta_box
	 */
	function metabox () {
		add_meta_box (
			'wcpt_information',
			__( 'WordCamp Information', 'wcpt' ),
			'wcpt_wordcamp_metabox',
			WCPT_POST_TYPE_ID,
			'advanced',
			'high'
		);

		add_meta_box (
			'wcpt_organizer_info',
			__( 'Organizing Team', 'wcpt' ),
			'wcpt_organizer_metabox',
			WCPT_POST_TYPE_ID,
			'advanced',
			'high'
		);

		add_meta_box (
			'wcpt_venue_info',
			__( 'Venue Information', 'wcpt' ),
			'wcpt_venue_metabox',
			WCPT_POST_TYPE_ID,
			'advanced',
			'high'
		);
	}

	/**
	 * metabox_save ()
	 *
	 * Pass the metabox values before saving
	 *
	 * @param int $post_id
	 * @return int
	 */
	function metabox_save ( $post_id ) {
		
		// Don't add/remove meta on revisions and auto-saves
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) )
			return;
		
		// WordCamp post type only
		if ( WCPT_POST_TYPE_ID == get_post_type() ) {
			// Post meta keys
			$wcpt_meta_keys = WordCamp_Admin::meta_keys();

			// Loop through meta keys and update
			foreach ( $wcpt_meta_keys as $key => $value ) {

				// Get post value
				$post_value   = wcpt_key_to_str( $key, 'wcpt_' );
				$values[$key] = isset( $_POST[$post_value] ) ? esc_attr( $_POST[$post_value] ) : '';

				switch ( $value ) {
					case 'text'     :
					case 'textarea' :
						update_post_meta( $post_id, $key, $values[$key] );
						break;

					case 'date' :
						if ( !empty( $values[$key] ) )
							$values[$key] = strtotime( $values[$key] );

						update_post_meta( $post_id, $key, $values[$key] );
						break;
					
					default:
						do_action( 'wcpt_metabox_save', $key, $value, $post_id );
						break;
				}
			}
		}
	}

	/**
	 * meta_keys ()
	 *
	 * Returns post meta key
	 *
	 * @return array
	 */
	function meta_keys ( $meta_group = '' ) {

		switch ( $meta_group ) {
			case 'organizer' :
				$retval = array (
					'Organizer Name'                  => 'text',
					'WordPress.org Username'          => 'text',
					'Email Address'                   => 'text',
					'Telephone'                       => 'text',
					'Mailing Address'                 => 'textarea',
					'Sponsor Wrangler Name'           => 'text',
					'Sponsor Wrangler E-mail Address' => 'text',
					'Budget Wrangler Name'            => 'text',
					'Budget Wrangler E-mail Address'  => 'text',
				);

				break;

			case 'venue' :
				$retval = array (
					'Venue Name'          => 'text',
					'Physical Address'    => 'textarea',
					'Maximum Capacity'    => 'text',
					'Available Rooms'     => 'text',
					'Website URL'         => 'text',
					'Contact Information' => 'textarea'
				);
				break;

			case 'wordcamp' :
				$retval = array (
					'Start Date (YYYY-mm-dd)' => 'date',
					'End Date (YYYY-mm-dd)'   => 'date',
					'Location'                => 'text',
					'URL'                     => 'text',
					'E-mail Address'          => 'text',
					'Twitter'                 => 'text',
					'WordCamp Hashtag'                => 'text',
					'Number of Anticipated Attendees' => 'text',
					'Multi-Event Sponsor Region'      => 'mes-dropdown',
				);
				break;

			case 'all' :
			default :
				$retval = array(
					'Start Date (YYYY-mm-dd)' => 'date',
					'End Date (YYYY-mm-dd)'   => 'date',
					'Location'                => 'text',
					'URL'                     => 'text',
					'E-mail Address'          => 'text',
					'Twitter'                 => 'text',
					'WordCamp Hashtag'                => 'text',
					'Number of Anticipated Attendees' => 'text',
					'Multi-Event Sponsor Region'      => 'mes-dropdown',

					'Organizer Name'                  => 'text',
					'WordPress.org Username'          => 'text',
					'Email Address'                   => 'text',
					'Telephone'                       => 'text',
					'Mailing Address'                 => 'textarea',
					'Sponsor Wrangler Name'           => 'text',
					'Sponsor Wrangler E-mail Address' => 'text',
					'Budget Wrangler Name'            => 'text',
					'Budget Wrangler E-mail Address'  => 'text',

					'Venue Name'          => 'text',
					'Physical Address'    => 'textarea',
					'Maximum Capacity'    => 'text',
					'Available Rooms'     => 'text',
					'Website URL'         => 'text',
					'Contact Information' => 'textarea'
				);
				break;

		}

		return apply_filters( 'wcpt_admin_meta_keys', $retval, $meta_group );
	}
	
	/**
	 * Fired during admin_print_styles
	 * Adds jQuery UI
	 */
	function admin_scripts() {
		if ( get_post_type() == WCPT_POST_TYPE_ID )
			wp_enqueue_script( 'jquery-ui-custom', WCPT_URL . '/assets/js/jquery-ui-1.8.18.custom.min.js', array( 'jquery' ) );
	}
	
	function admin_print_scripts() {
		if ( get_post_type() == WCPT_POST_TYPE_ID ) :
		?>
		<script>
			jQuery(document).ready(function($) {
				$('.date-field').datepicker({
					dateFormat: 'yy-mm-dd'
				});
			});
		</script>
		<?php
		endif;
	}
	
	function admin_styles() {
		if ( get_post_type() == WCPT_POST_TYPE_ID )
			wp_enqueue_style( 'jquery-ui-redmond', WCPT_URL . '/assets/css/redmond/jquery-ui-1.8.18.custom.css' );
	}

	/**
	 * admin_head ()
	 *
	 * Add some general styling to the admin area
	 */
	function admin_head () {
		// Icons for top level admin menus
		$menu_icon_url	= WCPT_IMAGES_URL . '/icon-wordcamp.png';

		// Top level menu classes
		$class = sanitize_html_class( WCPT_POST_TYPE_ID ); ?>

		#menu-posts-<?php echo $class; ?> .wp-menu-image {
			background: url(<?php echo $menu_icon_url; ?>) no-repeat 0 -32px;
		}
		#menu-posts-<?php echo $class; ?>:hover .wp-menu-image,
		#menu-posts-<?php echo $class; ?>.wp-has-current-submenu .wp-menu-image {
			background: url(<?php echo $menu_icon_url; ?>) no-repeat 0 0;
		}

		<?php if ( !empty( $_GET['post_type'] ) && $_GET['post_type'] == WCPT_POST_TYPE_ID ) : ?>

			#icon-edit, #icon-post {
				background: url(<?php echo WCPT_IMAGES_URL . '/icon32.png'; ?>) no-repeat 4px 0;
			}

			.column-title { width: 40%; }
			.column-wcpt_location, .column-wcpt_date, column-wcpt_organizer { white-space: nowrap; }

		<?php endif;
	}

	/**
	 * user_profile_update ()
	 *
	 * Responsible for showing additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_update ( $user_id ) {
		if ( !wcpt_has_access() )
			return false;

	}

	/**
	 * user_profile_wordcamp ()
	 *
	 * Responsible for saving additional profile options and settings
	 *
	 * @todo Everything
	 */
	function user_profile_wordcamp ( $profileuser ) {

		if ( !wcpt_has_access() )
			return false;

?>
		<h3><?php _e( 'WordCamps', 'wcpt' ); ?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php _e( 'WordCamps', 'wcpt' ); ?></th>
				<td>

				</td>
			</tr>
		</table>
<?php
	}

	/**
	 * column_headers ()
	 *
	 * Manage the column headers
	 *
	 * @param array $columns
	 * @return array $columns
	 */
	function column_headers ( $columns ) {
		$columns = array (
			'cb'               => '<input type="checkbox" />',
			'title'            => __( 'Title', 'wcpt' ),
			//'wcpt_location'    => __( 'Location', 'wcpt' ),
			'wcpt_date'        => __( 'Date',      'wcpt' ),
			'wcpt_organizer'   => __( 'Organizer', 'wcpt' ),
			'wcpt_venue'       => __( 'Venue',     'wcpt' ),
			'date'             => __( 'Status',    'wcpt' )
		);
		return $columns;
	}

	/**
	 * column_data ( $column, $post_id )
	 *
	 * Print extra columns
	 *
	 * @param string $column
	 * @param int $post_id
	 */
	function column_data ( $column, $post_id ) {
		if ( $_GET['post_type'] !== WCPT_POST_TYPE_ID )
			return $column;

		switch ( $column ) {
			case 'wcpt_location' :
				echo wcpt_get_wordcamp_location() ? wcpt_get_wordcamp_location() : __( 'No Location', 'wcpt' );
				break;

			case 'wcpt_date' :

				// Has a start date
				if ( $start = wcpt_get_wordcamp_start_date() ) {

					// Has an end date
					if ( $end = wcpt_get_wordcamp_end_date() ) {
						$string_date = sprintf( __( 'Start: %1$s<br />End: %2$s', 'wcpt' ), $start, $end );

					// No end date
					} else {
						$string_date = sprintf( __( 'Start: %1$s', 'wcpt' ), $start );
					}

				// No date
				} else {
					$string_date = __( 'No Date', 'wcpt' );
				}

				echo $string_date;
				break;

			case 'wcpt_organizer' :
				echo wcpt_get_wordcamp_organizer_name() ? wcpt_get_wordcamp_organizer_name() : __( 'No Organizer', 'wcpt' );

				break;

			case 'wcpt_venue' :
				echo wcpt_get_wordcamp_venue_name() ? wcpt_get_wordcamp_venue_name() : __( 'No Venue', 'wcpt' );
				break;
		}
	}

	/**
	 * post_row_actions ( $actions, $post )
	 *
	 * Remove the quick-edit action link and display the description under
	 *
	 * @param array $actions
	 * @param array $post
	 * @return array $actions
	 */
	function post_row_actions ( $actions, $post ) {
		if ( WCPT_POST_TYPE_ID == $post->post_type ) {
			unset( $actions['inline hide-if-no-js'] );

			$wc = array();

			if ( $wc_location = wcpt_get_wordcamp_location() )
				$wc['location'] = $wc_location;

			if ( $wc_url = make_clickable( wcpt_get_wordcamp_url() ) )
				$wc['url'] = $wc_url;

			echo implode( ' - ', (array) $wc );
		}
		return $actions;
	}

	/**
	 * Add the lead organizer to Central when a WordCamp application is accepted.
	 *
	 * When an application is submitted, a `wordcamp` post is created with a `draft` status. When it's accepted,
	 * the status changes to `pending`. Adding the lead organizer to Central allows them to enter all the `wordcamp`
	 * meta info themselves, and also post updates to the Central blog.
	 *
	 * @param string $new_status
	 * @param string $old_status
	 * @param WP_Post $post
	 */
	public function add_organizer_to_central( $new_status, $old_status, $post ) {
		if ( empty( $post->post_type ) || WCPT_POST_TYPE_ID != $post->post_type ) {
			return;
		}

		if ( 'draft' != $old_status || 'pending' != $new_status ) {
			return;
		}

		$lead_organizer = get_user_by( 'login', get_post_meta( $post->ID, 'WordPress.org Username', true ) );

		if ( $lead_organizer && add_user_to_blog( get_current_blog_id(), $lead_organizer->ID, 'contributor' ) ) {
			$message_body = sprintf(
				"Howdy! Your application for %s has been accepted, and we've granted your WordPress.org account access to central.wordcamp.org. Most of the information that we'll need to collect from you is stored in a custom 'WordCamp' post located at the address below. Please log in and fill out all those empty fields. We can't add you to the official schedule until all of the fields are completed.

				%s

				Please let us know if you have any questions,
				WordCamp Central",
				sanitize_text_field( $post->post_title ),
				admin_url( 'post.php?post=' . $post->ID . '&action=edit' )
			);

			wp_mail(
				$lead_organizer->user_email,
				$post->post_title . ' application accepted',
				str_replace( "\t", '', $message_body ),
				array( 'Reply-To: support@wordcamp.org' )
			);
		}
	}

	/**
	 * Notify Multi Event Sponsors when a new WordCamp in their region is added to the schedule.
	 *
	 * This assumes that all of the meta fields we use are filled in, but that should be a safe assumption
	 * since we require them to be before publishing the post. This will need to be updated if that ever
	 * changes, though.
	 *
	 * @param string  $new_status
	 * @param string  $old_status
	 * @param WP_Post $wordcamp
	 */
	public function notify_mes_sponsors_when_wordcamp_scheduled( $new_status, $old_status, $wordcamp ) {
		if ( empty( $wordcamp->post_type ) || WCPT_POST_TYPE_ID != $wordcamp->post_type ) {
			return;
		}

		if ( 'pending' != $old_status || 'publish' != $new_status || ! class_exists( 'MES_Sponsor' )) {
			return;
		}

		$wordcamp_region                 = (int) get_post_meta( $wordcamp->ID, 'Multi-Event Sponsor Region', true );
		$wordcamp_sponsor_wrangler_email = get_post_meta( $wordcamp->ID, 'Sponsor Wrangler E-mail Address', true );
		$wordcamp_lead_organizer_email   = get_post_meta( $wordcamp->ID, 'Email Address', true );

		$mes_sponsors = get_posts( array(
			'post_type'   => MES_Sponsor::POST_TYPE_SLUG,
			'numberposts' => -1,
		) );

		foreach ( $mes_sponsors as $sponsor ) {
			$sponsor_email_address         = get_post_meta( $sponsor->ID, 'mes_email_address', true );
			$sponsor_first_name            = get_post_meta( $sponsor->ID, 'mes_first_name', true );
			$sponsor_regional_sponsorships = get_post_meta( $sponsor->ID, 'mes_regional_sponsorships', true );

			if ( ! $sponsor_email_address || ! is_numeric( $sponsor_regional_sponsorships[ $wordcamp_region ] ) ) {
				continue;
			}

			$headers = array(
				'Reply-To: support@wordcamp.org',
				'CC: ' . $wordcamp_sponsor_wrangler_email . ', ' . $wordcamp_lead_organizer_email,
			);

			$message_body = sprintf(
				"Howdy, %s! This email is to tell you that %s has been added to the schedule and has accepted your sponsorship offer.

				Their site is: %s

				Their date is: %s, and they expect about %d attendees.

				Their venue address is:

				%s

				Their shipping address is:

				%s

				Their Twitter handle is %s and their hashtag is %s

				You can reach them by emailing: %s or %s

				Your company information and logo will be displayed on the WordCamp's site shortly.

				Thanks for your support of %s!

				Best wishes,

				Andrea

				Andrea Middleton
				WordCamp Central",
				sanitize_text_field( $sponsor_first_name ),
				sanitize_text_field( $wordcamp->post_title ),
				esc_url( get_site_url( absint( get_post_meta( $wordcamp->ID, '_site_id', true ) ) ) ),
				date( 'l, F jS, Y', absint( get_post_meta( $wordcamp->ID, 'Start Date (YYYY-mm-dd)', true ) ) ),
				absint( get_post_meta( $wordcamp->ID, 'Number of Anticipated Attendees', true ) ),
				strip_tags( get_post_meta( $wordcamp->ID, 'Physical Address', true ) ),
				strip_tags( get_post_meta( $wordcamp->ID, 'Mailing Address', true ) ),
				esc_url( 'https://twitter.com/' . get_post_meta( $wordcamp->ID, 'Twitter', true ) ),
				esc_url( 'https://twitter.com/hashtag/' . get_post_meta( $wordcamp->ID, 'WordCamp Hashtag', true ) ),
				sanitize_text_field( get_post_meta( $wordcamp->ID, 'E-mail Address', true ) ),
				sanitize_text_field( get_post_meta( $wordcamp->ID, 'Sponsor Wrangler E-mail Address', true ) ),
				sanitize_text_field( $wordcamp->post_title )
			);

			wp_mail(
				$sponsor_email_address,
				$wordcamp->post_title . ' has accepted your sponsorship offer',
				str_replace( "\t", '', $message_body ),
				$headers
			);
		}
	}
}
endif; // class_exists check

/**
 * Functions for displaying specific meta boxes
 */
function wcpt_wordcamp_metabox () {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'wordcamp' );
	wcpt_metabox( $meta_keys );
}

function wcpt_organizer_metabox () {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'organizer' );
	wcpt_metabox( $meta_keys );
}

function wcpt_venue_metabox () {
	$meta_keys = $GLOBALS['wordcamp_admin']->meta_keys( 'venue' );
	wcpt_metabox( $meta_keys );
}

/**
 * wcpt_metabox ()
 *
 * The metabox that holds all of the additional information
 *
 * @package WordCamp Post Type
 * @subpackage Template Tags
 * @since WordCamp Post Type (0.1)
 */
function wcpt_metabox ( $meta_keys ) {
	global $post_id;

	foreach ( $meta_keys as $key => $value ) :
		$object_name = wcpt_key_to_str( $key, 'wcpt_' );
?>

		<div class="inside">
			<p>
				<strong><?php echo $key; ?></strong>
			</p>
			<p>
				<label class="screen-reader-text" for="<?php echo $object_name; ?>"><?php echo $key; ?></label>

<?php			switch ( $value ) {
					case 'text' : ?>

						<input type="text" size="36" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>" value="<?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?>" />

<?php					break;

					case 'date' :

						// Quick filter on dates
						if ( $date = get_post_meta( $post_id, $key, true ) ) {
							$date = date( 'Y-m-d', $date );
						}

						?>

						<input type="text" size="36" class="date-field" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>" value="<?php echo $date; ?>" />

<?php					break;

					case 'textarea' : ?>

						<textarea rows="4" cols="23" name="<?php echo $object_name; ?>" id="<?php echo $object_name; ?>"><?php echo esc_attr( get_post_meta( $post_id, $key, true ) ); ?></textarea>

<?php					break;

					default:
						do_action( 'wcpt_metabox_value', $key, $value, $object_name );
						break;
				} ?>

			</p>
		</div>

<?php

	endforeach;
}

?>