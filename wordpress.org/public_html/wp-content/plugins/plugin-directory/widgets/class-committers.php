<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * A Widget to display committer information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Committers extends \WP_Widget {

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_committers', __( 'Plugin Committers', 'wporg-plugins' ), array(
			'classname'   => 'plugin-committers',
			'description' => __( 'Displays committer information.', 'wporg-plugins' ),
		) );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$post = get_post();

		$committers = Tools::get_plugin_committers( $post->post_name );
		$committers = array_map( function( $user_login ) {
			return get_user_by( 'login', $user_login );
		}, $committers );

		echo $args['before_widget'];
		?>
		<style>
			<?php // TODO: Yes, these need to be moved into the CSS somewhere. ?>
			ul.committer-list {
				list-style: none;
				margin: 0;
				font-size: 0.9em;
			}
			ul.committer-list li {
				clear: both;
				padding-bottom: 0.5em;
			}
			ul.committer-list a.remove {
				color: red;
				cursor: pointer;
				visibility: hidden;
			}
			ul.committer-list li:hover a.remove {
				visibility: visible;
			}
		</style>

		<h3><?php _e( 'Committers', 'wporg-plugins' ); ?></h3>

		<ul class="committer-list">
		<?php foreach ( $committers as $committer ) {
			echo '<li data-user="' . esc_attr( $committer->user_nicename ) . '">' .
				get_avatar( $committer->ID, 32 ) .
				'<a href="' . esc_url( 'https://profiles.wordpress.org/' . $committer->user_nicename ) . '">' . Template::encode( $committer->display_name ) . '</a>' .
				'<br><small>' .
				( current_user_can( 'plugin_review' ) ? esc_html( $committer->user_email ) . ' ' : '' ) .
				'<a class="remove">' . __( 'Remove', 'wporg-plugins' ) . '</a>' .
				'</small>' .
				'</li>';
		} ?>
		<li class="new" data-template="<?php echo esc_attr( '<li><img src="" class="avatar avatar-32 photo" height="32" width="32"><a class="profile" href=""></a><br><small><span class="email"></span> <a class="remove">' . __( 'Remove', 'wporg-plugins' ) . '</a></small></li>' ); ?>">
			<input type="text" name="committer" placeholder="<?php esc_attr_e( 'Login, Slug, or Email.', 'wporg-plugins' ); ?>">
			<input type="submit" value="<?php esc_attr_e( 'Add', 'wporg-plugins' ); ?>">
		</li>

		</ul>
		<script>
			var rest_api_url = <?php echo wp_json_encode( get_rest_url() ); ?>,
				rest_api_nonce = <?php echo wp_json_encode( wp_create_nonce( 'wp_rest' ) ); ?>,
				plugin_slug = <?php echo wp_json_encode( $post->post_name ); ?>;

		jQuery( 'ul.committer-list' ).on( 'click', 'a.remove', function(e) {
			e.preventDefault();

			var $this = jQuery( this ),
				$row = $this.parents('li'),
				user_nicename = $row.data( 'user' ),
				url = rest_api_url + 'plugins/v1/plugin/' + plugin_slug + '/committers/' + user_nicename + '/?_wpnonce=' + rest_api_nonce;

			jQuery.post({
				url: url,
				method: 'DELETE',
			}).success( function( result ) {
				if ( true === result ) {
					$row.slideUp( 500, function() {
						$row.remove();
					} );
				} else {
					alert( result.messsage );
				}
			} ).fail( function( result ) {
				result = jQuery.parseJSON( result.responseText );
				if ( typeof result.message !== undefined ) {
					alert( result.message );
				}
			} );
		} );

		jQuery( 'ul.committer-list' ).on( 'click', 'li.new input[type="submit"]', function(e) {
			e.preventDefault();

			var $this = jQuery( this ),
				$row = $this.parents('li'),
				$list = $row.parents('ul'),
				$new_user_input = $row.find( 'input[name="committer"]' ),
				user_to_add = $new_user_input.val(),
				url = rest_api_url + 'plugins/v1/plugin/' + plugin_slug + '/committers/?_wpnonce=' + rest_api_nonce;

			jQuery.post({
				url: url,
				dataType: 'json',
				data: {
					committer: user_to_add
				}
			}).done( function( result ) {
				if ( typeof result.name !== "undefined" ) {
					$newrow = jQuery( $row.data('template') );
					$newrow.data('user', result.nicename );
					$newrow.find('img').attr( 'src', result.avatar );
					$newrow.find('a.profile').attr('href', result.profile ).html( result.name );
					if ( typeof result.email !== "undefined" ) {
						$newrow.find('span.email').text( result.email );
					}
					$row.before( $newrow );
					$new_user_input.val('');
				} else {
					alert( result.messsage );
				}
			} ).fail( function( result ) {
				result = jQuery.parseJSON( result.responseText );
				if ( typeof result.message !== undefined ) {
					alert( result.message );
				}
			} );
		} );
			
		</script>

		<?php
		echo $args['after_widget'];
	}
}
