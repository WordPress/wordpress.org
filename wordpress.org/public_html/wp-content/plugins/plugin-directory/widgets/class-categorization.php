<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;

use WordPressdotorg\Plugin_Directory\Plugin_I18n;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display categorization information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Categorization extends \WP_Widget {

	/**
	 * Categorization constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_categorization', __( 'Plugin Categorization', 'wporg-plugins' ), [
			'classname'   => 'plugin-categorization',
			'description' => __( 'Displays plugin categorization information.', 'wporg-plugins' ),
		] );
	}

	/**
	 * Outputs the content of the widget.
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		$post = get_post();

		if ( has_term( 'commercial', 'plugin_business_model', $post ) ) {
			$model_type  = 'commercial';
			$title       = __( 'Commercial Plugin', 'wporg-plugins' );
			$url         = get_post_meta( $post->ID, 'external_support_url', true );
			$link_text   = __( 'Support', 'wporg-plugins' );
			$description = __( 'This plugin is free but offers additional paid commercial upgrades or support.', 'wporg-plugins' );
		}
		elseif ( has_term( 'community', 'plugin_business_model', $post ) ) {
			$model_type  = 'community';
			$title       = __( 'Community Plugin', 'wporg-plugins' );
			$url         = get_post_meta( $post->ID, 'external_repository_url', true );
			$link_text   = __( 'Contribute', 'wporg-plugins' );
			$description = __( 'This plugin is developed and supported by a community.', 'wporg-plugins' );
		}
		else {
			return;
		}

		echo $args['before_widget'];
		?>

		<div class="widget categorization-widget categorization-widget-<?php echo esc_attr( $model_type ); ?>">
			<div class="widget-head">
				<h2><?php echo esc_html( apply_filters( 'widget_title', $title, $instance, $this->id_base ) ); ?></h2>

				<?php
				// Always show URL if there is one, but also output the markup if on the advanced view tab
				// and the current user can update the categorization options so that the update can be
				// easily reflected in the widget on the same page. (CSS will prevent a link with an empty
				// URL from being shown.)
				if ( $url || ( get_query_var( 'plugin_advanced' ) && current_user_can( 'plugin_admin_edit', $post ) ) ) {
					printf( '<a href="%1$s" rel="nofollow">%2$s</a>', esc_url( $url ), esc_html( $link_text ) );
				}
				?>
			</div>
	
			<p><?php echo esc_html( $description ); ?></p>
		</div>
		<?php
		echo $args['after_widget'];
	}
}
