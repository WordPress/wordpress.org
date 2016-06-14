<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;
use WordPressdotorg\Plugin_Directory\Template;

/**
 * A Widget to display meta information about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Meta extends \WP_Widget {

	/**
	 * Meta constructor.
	 */
	public function __construct() {
		parent::__construct( 'plugin_meta', __( 'Plugin Meta', 'wporg-plugins' ), array(
			'classname'   => 'plugin-meta',
			'description' => __( 'Displays plugin meta information.', 'wporg-plugins' ),
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

		echo $args['before_widget'];
		?>

		<link itemprop="applicationCategory" href="http://schema.org/OtherApplication" />
		<span itemprop="offers" itemscope itemtype="http://schema.org/Offer">
			<meta itemprop="price" content="0.00" />
			<meta itemprop="priceCurrency" content="USD" />
			<span itemprop="seller" itemscope itemtype="http://schema.org/Organization">
				<span itemprop="name" content="WordPress.org"></span>
			</span>
		</span>

		<ul>
			<li><?php printf( __( 'Last updated: %s ago', 'wporg-plugins' ), '<span itemprop="dateModified" content="' . esc_attr( get_post_modified_time( 'c' ) ) . '">' . human_time_diff( get_post_modified_time() ) . '</span>' ); ?></li>
			<li><?php printf( __( 'Active installs: %s', 'wporg-plugins' ), Template::active_installs( false ) ); ?></li>
			<?php if ( $categories = get_the_term_list( $post->ID, 'plugin_category', '<div class="tags">', '', '</div>' ) ) : ?>
				<li><?php printf( _n( 'Category: %s', 'Categories: %s', count( get_the_terms( $post, 'plugin_category' ) ), 'wporg-plugins' ), $categories ); ?></li>
			<?php endif; ?>
			<?php if ( $built_for = get_the_term_list( $post->ID, 'plugin_built_for', '', ', ' ) ) : ?>
				<li><?php printf( __( 'Designed to work with: %s', 'wporg-plugins' ), $built_for ); ?></li>
			<?php endif; ?>
			<?php if ( $business_model = get_the_term_list( $post->ID, 'plugin_business_model', '', ', ' ) ) : ?>
				<li><?php printf( __( 'Business Model: %s', 'wporg-plugins' ), $business_model ); ?></li>
			<?php endif; ?>
		</ul>

		<?php
		echo $args['after_widget'];
	}
}
