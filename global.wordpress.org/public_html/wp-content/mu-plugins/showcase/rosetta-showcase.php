<?php
/*
 * Plugin Name: Rosetta Showcase
 * Author: Zé Fontainhas and Andrew Nacin
 */


add_action( 'init', 'rosetta_showcase_register' );

function rosetta_showcase_register() {
	$labels = array(
		'name' => _x( 'Showcase', 'post type general name', 'rosetta' ),
		'singular_name' => _x( 'Showcase Site', 'post type singular name', 'rosetta' ),
		'add_new' => _x( 'Add New', 'showcase item', 'rosetta' ),
		'add_new_item' => __( 'Add New Site', 'rosetta' ),
		'edit_item' => __( 'Edit Site', 'rosetta' ),
		'new_item' => __( 'New Site', 'rosetta' ),
		'view_item' => __( 'View Site', 'rosetta' ),
		'search_items' => __( 'Search Showcase', 'rosetta' ),
		'not_found' =>  __( 'Nothing found', 'rosetta' ),
		'not_found_in_trash' => __( 'Nothing found in Trash', 'rosetta' ),
	);

	$args = array(
		'labels' => $labels,
		'public' => true,
		'show_ui' => true,
		'rewrite' => false,
		'has_archive' => true,
		'capability_type' => 'post',
		'map_meta_cap' => true,
		'hierarchical' => false,
		'supports' => array( 'title','excerpt' ),
	);

	register_post_type( 'showcase', $args );
}

add_filter( 'post_type_link', 'rosetta_filter_showcase_permalink', 10, 2 );

function rosetta_filter_showcase_permalink( $post_link, $post ) {
	if ( 'showcase' == $post->post_type ) {
		$url = get_post_meta( $post->ID, '_rosetta_showcase_url', true );
		if ( $url ) {
			return $url;
		}
	}
	return $post_link;
}

add_action( 'add_meta_boxes_showcase', 'rosetta_add_showcase_meta_box' );

function rosetta_add_showcase_meta_box() {
	remove_meta_box( 'postexcerpt', 'showcase', 'normal' );
	add_meta_box( 'rosetta_showcase_meta', __( 'Description and URL', 'rosetta' ), 'rosetta_showcase_meta_box', 'showcase', 'normal', 'default' );
}

function rosetta_showcase_meta_box( $post ) {
	$url = get_post_meta( $post->ID, '_rosetta_showcase_url', true );
	?>
	<p><label for="rosetta_showcase_url"><?php _e( 'URL' ) // core ?></label>
	<input style="margin-left: 0; width: 98%" name="rosetta_showcase_url" type="text" value="<?php echo esc_url( $url ); ?>" /></p>
	<label for="excerpt"><?php _e( 'Description' ) // core ?></label>
	<textarea rows="1" cols="40" name="excerpt" tabindex="6" id="excerpt"><?php echo $post->post_excerpt; // textarea_escaped ?></textarea>
	<?php
}

add_action( 'save_post', 'save_showcase_url', 10, 2 );

function save_showcase_url( $post_id, $post ) {

	if ( $post->post_type != 'showcase' ) {
		return;
	}

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	if ( defined( 'DOING_AUTOSAVE' ) || defined( 'DOING_AJAX' ) ) {
		return;
	}

	if ( ! isset( $_POST['rosetta_showcase_url' ] ) ) {
		return;
	}

	$url = esc_url_raw( $_POST['rosetta_showcase_url'] );

	update_post_meta( $post_id, '_rosetta_showcase_url', $url );
}

/* Show columns on the 'All Sites' page */

add_action( 'manage_showcase_posts_columns',  'rosetta_showcase_custom_columns' );
add_filter( 'manage_showcase_posts_custom_column', 'rosetta_showcase_custom_column', 10, 2 );

function rosetta_showcase_custom_columns( $columns ) {
	$columns = array(
		'cb' => $columns['cb'],
		'shot' => __( 'Image' ), // core
		'title' => __( 'Website' ), // core
		'description' => __( 'Description' ), // core
		'url' => __( 'URL' ), // core
	);
	return $columns;
}

function rosetta_showcase_custom_column( $column, $post_id ) {
	$post = get_post( $post_id );

	switch ( $column ) {
		case 'description' :
			the_excerpt();
			break;
		case 'url' :
			$url = get_post_meta( $post_id, '_rosetta_showcase_url', true );
			echo '<a href="' . esc_url( $url ) . '">' . esc_url_raw( $url ) . '</a>';
			break;
		case 'shot' :
			$url = esc_url( get_post_meta( $post_id, '_rosetta_showcase_url', true ) );
			if ( $url ) {
				echo '<a href="' . $url . '" target="_blank"><img width="100" src="http://s.wordpress.com/mshots/v1/' . urlencode( $url ) . '?w=100" /></a>';
			}
			break;
	}
}

/* Add Showcase posts to the main loop and feed */
//add_filter( 'pre_get_posts', 'rosetta_get_posts_with_showcase' );
function rosetta_get_posts_with_showcase( $query ) {
	global $wp_the_query;
	if ( ( is_home() && $wp_the_query === $query ) || is_feed() ) {
		$query->set( 'post_type', array( 'post', 'showcase' ) );
	}

	return $query;
}

class Rosetta_Showcase {
	function front() {
		$posts = get_posts( array( 'post_type' => 'showcase', 'numberposts' => -1 ) );
		shuffle( $posts );
		return array_slice( $posts, 0, 4 );
	}
}
