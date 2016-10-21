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
			h3 a.addnew:before {
				content: '+';
			}
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
				visibility: hidden;
			}
			ul.committer-list li:hover a.remove {
				visibility: visible;
			}
		</style>
		<h3><?php _e( 'Committers', 'wporg-plugins' ); ?> <a href="#" class="addnew"><?php _e( 'Add', 'wporg-plugins' ); ?></button></h3>

		<ul class="committer-list">
		<?php foreach ( $committers as $committer ) {
			echo '<li>' .
				get_avatar( $committer->ID, 32 ) .
				'<a href="' . esc_url( 'https://profiles.wordpress.org/' . $committer->user_nicename ) . '">' . Template::encode( $committer->display_name ) . '</a>' .
				'<br><small>' . esc_html( $committer->user_email ) . ' ' .
				'<a href="#" class="remove">' . __( 'Remove', 'wporg-plugins' ) . '</a>' .
				'</small>' .
				'</li>';
		} ?>
		</ul>


		<?php
		echo $args['after_widget'];
	}
}
