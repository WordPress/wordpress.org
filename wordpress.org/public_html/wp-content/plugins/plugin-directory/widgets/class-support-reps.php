<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Tools;

/**
 * A Widget to display support rep information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Support_Reps extends \WP_Widget {

	/**
	 * Support Reps constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_support_reps', __( 'Plugin Support Reps', 'wporg-plugins' ), array(
			'classname'   => 'plugin-support-reps',
			'description' => __( 'Displays support rep information.', 'wporg-plugins' ),
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

		$support_reps = Tools::get_plugin_support_reps( $post->post_name );
		$support_reps = array_map( function ( $user_login ) {
			return get_user_by( 'slug', $user_login );
		}, $support_reps );

		if ( current_user_can( 'plugin_add_support_rep', $post ) || current_user_can( 'plugin_remove_support_rep', $post ) ) {
			wp_enqueue_script( 'wporg-plugins-support-reps', plugins_url( 'js/support-reps.js', __FILE__ ), array( 'wp-util' ), true );
			wp_localize_script( 'wporg-plugins-support-reps', 'supportRepsWidget', array(
				'restUrl'             => get_rest_url(),
				'restNonce'           => wp_create_nonce( 'wp_rest' ),
				'pluginSlug'          => $post->post_name,
				'removeSupportRepAYS' => __( 'Are you sure you want to remove this support rep?', 'wporg-plugins' ),
			) );
		} elseif ( ! $support_reps ) {
			return;
		}

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Support Reps', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];
		?>

		<ul id="support-rep-list" class="support-rep-list">

			<?php foreach ( $support_reps as $support_rep ) : ?>
				<li data-user="<?php echo esc_attr( $support_rep->user_nicename ); ?>">
					<?php echo get_avatar( $support_rep->ID, 32 ); ?>
					<a href="<?php echo esc_url( "https://profiles.wordpress.org/{$support_rep->user_nicename}/" ); ?>">
						<?php echo Template::encode( $support_rep->display_name ); ?>
					</a><br>

					<?php if ( current_user_can( 'plugin_remove_support_rep', $post ) ) : ?>
					<small>
						<?php echo current_user_can( 'plugin_review' ) ? esc_html( $support_rep->user_email ) . ' ' : ''; ?>
						<button class="button-link remove"><?php _e( 'Remove', 'wporg-plugins' ); ?></button>
					</small>
					<?php endif; ?>
				</li>
			<?php endforeach; ?>

			<?php if ( current_user_can( 'plugin_add_support_rep', $post ) ) : ?>
			<li class="new">
				<form id="add-support-rep" action="POST">
					<input type="text" name="support_rep" placeholder="<?php esc_attr_e( 'Login, Slug, or Email.', 'wporg-plugins' ); ?>">
					<button type="submit" class="button button-secondary"><?php esc_attr_e( 'Add', 'wporg-plugins' ); ?></button>
				</form>

				<script id="tmpl-new-support-rep" type="text/template">
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
