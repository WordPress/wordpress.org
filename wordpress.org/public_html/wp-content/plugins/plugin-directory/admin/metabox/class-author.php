<?php
namespace WordPressdotorg\Plugin_Directory\Admin\Metabox;

/**
 * Replacement for the core function post_author_meta_box on theme edit pages.
 *
 * Uses javascript for username autocompletion and to adjust the hidden id field.
 *
 * @package WordPressdotorg\Plugin_Directory\Admin\Metabox
 */
class Author {

	/**
	 * Displays information about the author of the current plugin.
	 */
	public static function display() {
		$post  = get_post();
		$value = empty( $post->ID ) ? get_current_user_id() : $post->post_author;
		$user  = new \WP_User( $value );

		?>
		<label><input id="post_author_username" type="text" value="<?php echo esc_attr( $user->user_login ); ?>" /></label>
		<input id="post_author_override" type="hidden" name="post_author_override" value="<?php echo esc_attr( $value ); ?>" />
		<label class="screen-reader-text"><?php _e( 'Author', 'wporg-plugins' ); ?></label>

		<script>
			jQuery( function( $ ) {
				$( '#post_author_username' ).autocomplete( {
					source: '<?php echo add_query_arg( array( 'action' => 'plugin-author-lookup', '_ajax_nonce' => wp_create_nonce( 'wporg_plugins_author_lookup' ) ), admin_url( 'admin-ajax.php' ) ); ?>',
					minLength: 2,
					delay: 700,
					autoFocus: true,
					select: function( event, ui ) {
						$( '#post_author_override' ).val( ui.item.value );
						$( '#post_author_username' ).val( ui.item.label );
						return false;
					},
					change: function() {
						if ( ! $( '#post_author_username' ).val().length ) {
							$( '#post_author_override' ).val( '' );
						}
					}
				}).keydown( function( event ) {
					if ( 13 === event.keyCode ) {
						event.preventDefault();
						return false;
					}
				});
			});
		</script>
		<?php
	}

	/**
	 * Admin ajax function to lookup a username for author autocompletion
	 *
	 * Note: nonce protected, only available to logged in users.
	 */
	public static function lookup_author() {
		check_ajax_referer( 'wporg_plugins_author_lookup' );

		$term = sanitize_text_field( wp_unslash( $_REQUEST['term'] ) );

		$user_query = new \WP_User_Query( array(
			'search'         => $term . '*',
			'search_columns' => array( 'user_login', 'user_nicename' ),
			'fields'         => array( 'ID', 'user_login' ),
			'number'         => 8,

			// ID zero here allows it to search all users, not just those with roles in the plugin directory.
			'blog_id'        => 0,
		) );

		if ( $user_query->results ) {
			$resp = array();

			foreach ( $user_query->results as $result ) {
				$resp[] = array(
					'label' => $result->user_login,
					'value' => $result->ID,
				);
			}
			wp_die( json_encode( $resp ) );
		}
	}
}
