<?php
/*
Plugin Name: P2 New Post Categories
Description: Adds a category dropdown to P2s new post form.
Version:     0.1
License:     GPLv2 or Later
*/

/*
 * Note: This has been forked from the original version to conform to WordPress.org conventions.
 */


class P2NewPostCategories {
	protected $new_category;
	const VERSION = '0.1';
	const REGEX_CATEGORY_IN_TAG_LIST = '/\[category=(.*)\]/i';

	/*
	 * Register hook callbacks
	 */
	public function __construct() {
		add_action( 'wp_head',        array( $this, 'print_css' ) );
		add_action( 'wp_footer',      array( $this, 'print_javascript' ) );

		add_action( 'p2_post_form',   array( $this, 'add_new_post_category_dropdown' ) );
		add_action( 'p2_ajax',        array( $this, 'parse_new_post_category' ) );
		add_action( 'wp_insert_post', array( $this, 'assign_new_post_to_category' ) );
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
						$( '#p2-new-post-category' ).change( this.appendCategoryToTagList );
					},

					/*
					 * Adds the name of the selected category to the #tags input field
					 * See the comments in parse_new_post_category() for details.
					 */
					appendCategoryToTagList : function( event ) {
						var optionalComma = '',
							tags          = $( '#tags' ).val(),
							category      = $( this ).children( 'option:selected' ).text();

						tags = tags.replace( /Tag it,?/, '' ).replace( /,?\[category=(.*)\]/, '' );
						if ( tags.length > 0 && ',' != tags.slice( -1 ) ) {
							optionalComma = ',';
						}

						if ( 'Uncategorized' != category ) {
							tags += optionalComma + '[category=' + category + ']';
						}

						if ( ',' == tags.substring( 0, 1 ) ) {
							tags = tags.substring( 1, tags.length );
						}

						$( '#tags' ).val( tags );
					}
				}

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
		) );

		wp_dropdown_categories( $params );
	}

	/*
	 * Since we can't hook into p2.posts.submit() and add a category parameter to the $.ajax() call,
	 * we append the category to the tags list and then parse it out before P2 processes it.
	 */
	public function parse_new_post_category( $action ) {
		if ( 'new_post' == $action ) {
			if ( preg_match( self::REGEX_CATEGORY_IN_TAG_LIST, $_POST['tags'], $matches ) ) {
				$this->new_category = get_term_by( 'name', $matches[1], 'category' );
				$_POST['tags'] = preg_replace( self::REGEX_CATEGORY_IN_TAG_LIST, '', $_POST['tags'] );
			}
		}
	}

	/*
	 * When the new post is being saved, we assign the category that we parsed out in parse_new_post_category()
	 */
	public function assign_new_post_to_category( $post_id ) {
		if ( isset( $this->new_category->term_id ) && $this->new_category->term_id ) {
			wp_set_object_terms( $post_id, $this->new_category->slug, 'category' );
		}
	}

} // end P2NewPostCategories

$GLOBALS['P2NewPostCategories'] = new P2NewPostCategories();
