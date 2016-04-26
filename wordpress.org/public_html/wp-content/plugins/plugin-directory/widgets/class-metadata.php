<?php
namespace WordPressdotorg\Plugin_Directory\Widgets;
use WordPressdotorg\Plugin_Directory\Template;
use WordPressdotorg\Plugin_Directory\Theme;

/**
 * A Widget to display the basic metadata about a plugin.
 *
 * @package WordPressdotorg\Plugin_Directory\Widgets
 */
class Metadata extends \WP_Widget {

	public function __construct() {
		$widget_ops = array( 
			'classname' => 'plugin-metadata',
			'description' => 'Displays the basic metadata about a plugin.',
		);
		parent::__construct( 'plugin_metadata', 'Plugin Metadata', $widget_ops );
	}

	/**
	 * Outputs the content of the widget
	 *
	 * @param array $args
	 * @param array $instance
	 */
	public function widget( $args, $instance ) {
		echo $args['before_widget'];
		?>
		<strong>Version:</strong> <?php echo Theme\wporg_plugins_the_version(); ?><br>
		<strong>Requires:</strong> <?php printf( __('%s or higher', 'wporg-plugins' ), Theme\wporg_plugins_template_requires() ); ?><br>
		<strong>Compatible up to:</strong> <?php echo Theme\wporg_plugins_template_compatible_up_to(); ?><br>
		<strong>Last Updated: </strong> <?php echo Theme\wporg_plugins_template_last_updated(); ?><br>
		<strong>Active Installs:</strong> <?php echo Template::active_installs( false ); ?><br>
		<meta itemprop="dateModified" content="<?php the_time('Y-m-d'); ?>" />
		<?php
		echo $args['after_widget'];
	}

}
