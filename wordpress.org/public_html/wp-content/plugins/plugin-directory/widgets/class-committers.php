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
	 * Committers constructor.
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
		$committers = array_map( function ( $user_login ) {
			return get_user_by( 'login', $user_login );
		}, $committers );

		if ( current_user_can( 'plugin_add_committer', $post ) || current_user_can( 'plugin_remove_committer', $post ) ) {
			wp_enqueue_script( 'wporg-plugins-committers', plugins_url( 'js/committers.js', __FILE__ ), array( 'wp-util' ), true );
			wp_localize_script( 'wporg-plugins-committers', 'committersWidget', array(
				'restUrl'            => get_rest_url(),
				'restNonce'          => wp_create_nonce( 'wp_rest' ),
				'pluginSlug'         => $post->post_name,
				'removeCommitterAYS' => __( 'Are you sure you want to remove this committer?', 'wporg-plugins' ),
			) );
		}

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Committers', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];
		?>

		<ul id="committer-list" class="committer-list">

			<?php foreach ( $committers as $committer ) : ?>
				<li data-user="<?php echo esc_attr( $committer->user_nicename ); ?>">
					<?php echo get_avatar( $committer->ID, 32 ); ?>
					<a href="<?php echo esc_url( "https://profiles.wordpress.org/{$committer->user_nicename}/" ); ?>">
						<?php echo Template::encode( $committer->display_name ); ?>
					</a><br>

					<?php if ( current_user_can( 'plugin_remove_committer', $post ) ) : ?>
					<small>
						<?php echo current_user_can( 'plugin_review' ) ? esc_html( $committer->user_email ) . ' ' : ''; ?>
						<button class="button-link remove"><?php _e( 'Remove', 'wporg-plugins' ); ?></button>
					</small>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>

			<?php if ( current_user_can( 'plugin_add_committer', $post ) ) : ?>
			<li class="new">
				<form id="add-committer" action="POST">
					<input type="text" name="committer" placeholder="<?php esc_attr_e( 'Login, Slug, or Email.', 'wporg-plugins' ); ?>">
					<button type="submit" class="button button-secondary"><?php esc_attr_e( 'Add', 'wporg-plugins' ); ?></button>
				</form>

				<script id="tmpl-new-committer" type="text/template">
					<li data-user="{{ data.nicename }}">
						<a class="profile" href="{{ data.profile }}">
							<img src="{{ data.avatar }}" class="avatar avatar-32 photo" height="32" width="32">
							{{ data.name }}
						</a><br>
						<small>
							<# if ( data.email ) { #>
								<span class="email">{{ data.email }}</span>
							<# } #>
							<button class="button-link remove"><?php _e( 'Remove', 'wporg-plugins' ); ?></button>
						</small>
					</li>
				</script>
			</li>
			<?php endif; ?>
		</ul>

		<?php
		echo $args['after_widget'];
	}
}
