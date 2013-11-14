<?php
/*
Plugin Name: P2 New Post Categories
Description: Adds a category dropdown to P2s new post form.
Version:     0.2
License:     GPLv2 or Later
*/

/*
 * Note: This has been forked from the original version to conform to WordPress.org conventions.
 */


class P2NewPostCategories {
	const VERSION = '0.2';

	/*
	 * Register hook callbacks
	 */
	public function __construct() {
		add_action( 'wp_head',                              array( $this, 'print_css' ) );
		add_action( 'wp_footer',                            array( $this, 'print_javascript' ) );

		add_action( 'p2_post_form',                         array( $this, 'add_new_post_category_dropdown' ) );
		add_action( 'wp_ajax_p2npc_assign_category',        array( $this, 'assign_category_to_post' ) );
		add_action( 'wp_ajax_nopriv_p2npc_assign_category', array( $this, 'assign_category_to_post' ) );
	}

	/*
	 * Print our CSS
	 */
	public function print_css() {
		?>

		<!-- Begin P2 New Post Categories -->
		<style type="text/css">
			#new_post {
				position: relative;
				padding-bottom: 2.25em;
			}

			#p2-new-post-category {
				position: absolute;
				left: 0;
				bottom: 0;
			}
		</style>
		<!-- End P2 New Post Categories -->

		<?php
	}

	/*
	 * Print our JavaScript
	 */
	public function print_javascript() {
		?>

		<!-- Begin P2 New Post Categories -->
		<script type="text/javascript">
			( function( $ ) {

				var P2NewPostCategories = {

					/*
					 * Initialization
					 */
					construct : function() {
						P2NewPostCategories.dropdown         = $( '#p2-new-post-category' );
						P2NewPostCategories.dropdown_default = P2NewPostCategories.dropdown.val();

						$( document ).on( 'p2_new_post_submit_success', P2NewPostCategories.new_post );
					},

					/**
					 * Assign the selected category to the new post
					 * @param object event
					 * @param object data
					 */
					new_post : function( event, data ) {
						$.post(
							ajaxUrl.replace( '?p2ajax=true', '' ), {
								'action'                      : 'p2npc_assign_category',
								'post_id'                     : parseInt( data.post_id ),
								'category_id'                 :	parseInt( P2NewPostCategories.dropdown.val() ),
								'p2npc_assign_category_nonce' : $( '#p2npc_assign_category_nonce' ).val()
							}
						);

						P2NewPostCategories.dropdown.val( parseInt( P2NewPostCategories.dropdown_default ) ).change();
					}
				};

				P2NewPostCategories.construct();

			} )( jQuery );
		</script>
		<!-- End P2 New Post Categories -->

		<?php
	}

	public function add_new_post_category_dropdown() {
		$params = apply_filters( 'p2npc_category_dropdown_params', array(
			'orderby'    => 'name',
			'id'         => 'p2-new-post-category',
			'hide_empty' => false,
			'selected'   => get_option( 'default_category' ),
		) );

		wp_dropdown_categories( $params );
		wp_nonce_field( 'p2npc_assign_category', 'p2npc_assign_category_nonce' );
	}

	/*
	 * Assign a category to a post
	 * This is an AJAX handler.
	 */
	public function assign_category_to_post() {
		$assigned    = false;
		$post_id     = absint( $_REQUEST['post_id'] );
		$category_id = absint( $_REQUEST['category_id'] );
		
		check_ajax_referer( 'p2npc_assign_category', 'p2npc_assign_category_nonce' );
		
		if ( current_user_can( 'edit_post', $post_id ) ) { 
			$assigned = wp_set_object_terms( $post_id, $category_id, 'category' );
			$assigned = is_array( $assigned ) && ! empty( $assigned );
		}
		
		wp_die( $assigned );
	}

} // end P2NewPostCategories

$GLOBALS['P2NewPostCategories'] = new P2NewPostCategories();
