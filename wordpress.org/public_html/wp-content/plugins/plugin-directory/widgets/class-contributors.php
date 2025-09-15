<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display contributor information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Contributors extends \WP_Widget {

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_contributors', __( 'Plugin Contributors', 'wporg-plugins' ), array(
			'classname'   => 'plugin-contributors',
			'description' => __( 'Displays contributor information.', 'wporg-plugins' ),
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

		$contributors = get_terms( array(
			'taxonomy'   => 'plugin_contributors',
			'object_ids' => array( $post->ID ),
			'orderby'    => 'term_order',
			'fields'     => 'names',
		) );

		if ( is_wp_error( $contributors ) ) {
			$contributors = [];
		}

		// The owner of the plugin is always a contributor, and shown first.
		$plugin_owner = get_the_author_meta( 'user_nicename', $post->post_author );
		if ( $plugin_owner && 0 !== array_search( $plugin_owner, $contributors, true ) ) {
			$contributors = array_unique(
				array_merge(
					[ $plugin_owner ],
					$contributors
				)
			);
		}

		// Convert the user_nicenames to user objects.
		$contributors = array_map(
			function( $user_nicename ) {
				return get_user_by( 'slug', $user_nicename );
			},
			$contributors
		);

		// Remove any users that didn't exist.
		$contributors = array_filter( $contributors );

		$title = apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Contributors', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base );

		echo $args['before_widget'];
		echo $args['before_title'] . $title . $args['after_title'];
		?>

		<ul id="contributors-list" class="contributors-list">
			<?php foreach ( $contributors as $contributor ) : ?>
			<li>
				<?php echo get_avatar( $contributor->ID, 32 ); ?>
				<a href="<?php echo esc_url( "https://profiles.wordpress.org/{$contributor->user_nicename}/" ); ?>">
					<?php echo Template::encode( $contributor->display_name ?: $contributor->user_nicename ); ?>
				</a>
			</li>
			<?php endforeach; ?>
		</ul>

		<?php
		echo $args['after_widget'];
	}
}
