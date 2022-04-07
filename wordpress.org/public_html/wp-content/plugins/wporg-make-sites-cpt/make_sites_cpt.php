<?php
/**
* Plugin Name: Sites CPT for make.wordpress.org
* Plugin URI: http://make.wordpress.org
* Description: A sub-sites Custom Post Type for the Make WordPress master site.
* Author: George Stephanis
* Version: 1.0
* Author URI: http://profiles.wordpress.org/georgestephanis
*/

add_action( 'init', 'make_site_register_cpt' );
function make_site_register_cpt() {
	$labels = array(
		'name'               => _x( 'Sites',                   'site', 'make_site_cpt' ),
		'singular_name'      => _x( 'Site',                    'site', 'make_site_cpt' ),
		'add_new'            => _x( 'Add New',                 'site', 'make_site_cpt' ),
		'add_new_item'       => _x( 'Add New Site',            'site', 'make_site_cpt' ),
		'edit_item'          => _x( 'Edit Site',               'site', 'make_site_cpt' ),
		'new_item'           => _x( 'New Site',                'site', 'make_site_cpt' ),
		'view_item'          => _x( 'View Site',               'site', 'make_site_cpt' ),
		'search_items'       => _x( 'Search Sites',            'site', 'make_site_cpt' ),
		'not_found'          => _x( 'No sites found',          'site', 'make_site_cpt' ),
		'not_found_in_trash' => _x( 'No sites found in Trash', 'site', 'make_site_cpt' ),
		'parent_item_colon'  => _x( 'Parent Site:',            'site', 'make_site_cpt' ),
		'menu_name'          => _x( 'Sites',                   'site', 'make_site_cpt' ),
	);

	$args = array(
		'labels'       => $labels,
		'hierarchical' => true,
		'description'  => _x( 'A sub-site on the make.wordpress.org network', 'site', 'make_site_cpt' ),
		'supports'     => array(
			'title',
			'editor',
			'excerpt',
			'thumbnail',
			'revisions',
			'page-attributes'
		),
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'menu_position'       => 5,
		'show_in_nav_menus'   => true,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'has_archive'         => true,
		'query_var'           => true,
		'can_export'          => true,
		'rewrite'             => true,
		'capability_type'     => 'post',
		'show_in_rest'        => true
	);

	register_post_type( 'make_site', $args );

	$make_sites = make_site_get_network_sites();
	register_rest_field( 'make_site', 'link', array(
		'get_callback' => function( $site ) use ( $make_sites ) {
			$make_site_id = get_post_meta( $site['id'], 'make_site_id', true );
			return isset( $make_sites[ $make_site_id ] ) ? $make_sites[ $make_site_id ] : '';
		},
		'update_callback' => null,
		'schema' => array(
			'description' => __ ('Site link.' ),
			'type' => 'string',
		),
	) );
}

function make_site_get_network_sites() {
	global $wpdb;

	if ( ! is_multisite() )
		return array();

	$sql = "SELECT `blog_id`, CONCAT( `domain`, `path` ) AS `url` FROM `{$wpdb->blogs}` WHERE `public` = 1 AND `deleted` = 0";
	$results = $wpdb->get_results( $sql );
	$return = array();

	foreach ( $results as $result ) {
		$return[ $result->blog_id ] = 'https://'.$result->url;
	}

	return $return;
}

add_action( 'add_meta_boxes', 'make_site_add_meta_box' );
function make_site_add_meta_box() {
	add_meta_box( 'make_site_properties', __( 'Site Properties', 'make_site_cpt' ), 'make_site_properties_cb', 'make_site', 'advanced', 'core' );
}

function make_site_properties_cb( $post ) {
	wp_nonce_field( 'make_site_nonce', 'make_site_nonce' );
	$weekly_meeting       = get_post_meta( $post->ID, 'weekly_meeting', true );
	$weekly_meeting_when  = get_post_meta( $post->ID, 'weekly_meeting_when', true );
	$weekly_meeting_where = get_post_meta( $post->ID, 'weekly_meeting_where', true );
	$make_site_id         = get_post_meta( $post->ID, 'make_site_id', true );
	?>
	<table class="form-table">
		<tbody>
			<tr>
				<th><label for="weekly_meeting"><?php esc_html_e( 'Weekly Meeting', 'make_site_cpt' ); ?></label></th>
				<td><input type="checkbox" id="weekly_meeting" name="weekly_meeting" value="1" <?php checked( $weekly_meeting, '1' ); ?> /></td>
			</tr>
			<tr>
				<th><label style="margin-left:2em;" for="weekly_meeting_when"><?php esc_html_e( 'When?', 'make_site_cpt' ); ?></label></th>
				<td><input class="widefat" type="text" id="weekly_meeting_when" name="weekly_meeting_when" placeholder="Wednesdays @ 20:00 UTC" value="<?php echo esc_attr( $weekly_meeting_when ); ?>" /></td>
			</tr>
			<tr>
				<th><label style="margin-left:2em;" for="weekly_meeting_where"><?php esc_html_e( 'Where?', 'make_site_cpt' ); ?></label></th>
				<td><input class="widefat" type="text" id="weekly_meeting_where" name="weekly_meeting_where" placeholder="#team on Slack" value="<?php echo esc_attr( $weekly_meeting_where ); ?>" /></td>
			</tr>
			<?php if ( is_multisite() ) : ?>
				<tr>
					<th><label for="make_site_id"><?php esc_html_e( 'Site', 'make_site_cpt' ); ?></label></th>
					<td><select name="make_site_id" id="make_site_id">
						<option value=""></option>
<?php 
	foreach ( make_site_get_network_sites() as $value => $label ) {
		
		// skip all the non make blogs, so it's a lot easier to find the right one in the list
		if ( strpos( $label, "https://make.wordpress.org" ) === false ) {
			continue;
		} ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $make_site_id, $value ) ?>><?php echo esc_html( $label ); ?></option>
						<?php } // endforeach; ?>
					</select></td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
	<?php
}

add_action( 'save_post', 'make_site_save_postdata' );
function make_site_save_postdata( $post_id ) {

	if ( empty( $_REQUEST['post_type'] ) ) {
		return;
	}

	if ( 'make_site' != $_REQUEST['post_type'] ) {
		return;
	}

	if ( empty( $_POST['make_site_nonce'] ) ) {
		return;
	}

	if ( ! wp_verify_nonce( $_POST['make_site_nonce'], 'make_site_nonce' ) ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$weekly_meeting       = empty( $_POST['weekly_meeting'] ) ? '' : '1';
	$weekly_meeting_when  = sanitize_text_field( $_POST['weekly_meeting_when'] );
	$weekly_meeting_where = sanitize_text_field( $_POST['weekly_meeting_where'] );

	update_post_meta( $post_id, 'weekly_meeting',       $weekly_meeting );
	update_post_meta( $post_id, 'weekly_meeting_when',  $weekly_meeting_when );
	update_post_meta( $post_id, 'weekly_meeting_where', $weekly_meeting_where );

	if ( is_multisite() ) {
		$make_site_id = intval( $_POST['make_site_id'] );
		update_post_meta( $post_id, 'make_site_id', $make_site_id );
	}
}

