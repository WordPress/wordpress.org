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

		<h3 class="screen-reader-text"><?php echo apply_filters( 'widget_title', empty( $instance['title'] ) ? __( 'Meta', 'wporg-plugins' ) : $instance['title'], $instance, $this->id_base ); ?></h3>
		<link itemprop="applicationCategory" href="http://schema.org/OtherApplication" />
		<span itemprop="offers" itemscope itemtype="http://schema.org/Offer">
			<meta itemprop="price" content="0.00" />
			<meta itemprop="priceCurrency" content="USD" />
			<span itemprop="seller" itemscope itemtype="http://schema.org/Organization">
				<span itemprop="name" content="WordPress.org"></span>
			</span>
		</span>

		<ul>
			<?php if ( $built_for = get_the_term_list( $post->ID, 'plugin_built_for', '', ', ' ) ) : ?>
				<li><?php printf( __( 'Designed to work with: %s', 'wporg-plugins' ), $built_for ); ?></li>
			<?php endif; ?>
			<li><?php printf( __( 'Version: %s', 'wporg-plugins' ), '<strong>' . get_post_meta( $post->ID, 'version', true ) . '</strong>' ); ?></li>
			<li>
				<?php
				printf( __( 'Last updated: %s', 'wporg-plugins' ),
					/* Translators: Plugin modified time. */
					'<strong>' . sprintf( __( '%s ago', 'wporg-plugins' ), '<span itemprop="dateModified" content="' . esc_attr( get_post_modified_time( 'c' ) ) . '">' . human_time_diff( get_post_modified_time() ) . '</span>' ) . '</strong>'
				);
				?>
			</li>
			<li><?php printf( __( 'Active installs: %s', 'wporg-plugins' ), '<strong>' . Template::active_installs( false ) . '</strong>' ); ?></li>
			<li><?php printf( __( 'Tested up to: %s', 'wporg-plugins' ), '<strong>' . (string) get_post_meta( $post->ID, 'tested', true ) . '</strong>' ); ?></li>
			<?php if ( $tags = get_the_term_list( $post->ID, 'plugin_tags', '<div class="tags">', '', '</div>' ) ) : ?>
				<li><?php printf( _n( 'Tags: %s', 'Tags: %s', count( get_the_terms( $post, 'plugin_tags' ) ), 'wporg-plugins' ), $tags ); ?></li>
			<?php endif; ?>
		</ul>

		<?php
		echo $args['after_widget'];
	}
}
