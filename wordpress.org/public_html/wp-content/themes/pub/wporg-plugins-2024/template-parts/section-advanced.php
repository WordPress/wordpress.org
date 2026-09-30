<?php
/**
 * Template part for displaying the plugin administration sections.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

use WordPressdotorg\Plugin_Directory\Template;

global $post;
?>

<div id="admin" class="section">
	<?php the_closed_plugin_notice(); ?>
	<?php the_no_self_management_notice(); ?>

	<h2><?php esc_html_e( 'Statistics', 'wporg-plugins' ); ?></h2>

	<h4><?php esc_html_e( 'Active versions', 'wporg-plugins' ); ?></h4>
	<div id="plugin-version-stats" class="chart version-stats"></div>

	<h4><?php esc_html_e( 'Downloads Per Day', 'wporg-plugins' ); ?></h4>
	<div id="plugin-download-stats" class="chart download-stats"></div>

	<h4><?php esc_html_e( 'Downloads history', 'wporg-plugins' ); ?></h4>
	<table id="plugin-download-history-stats" class="download-history-stats">
		<tbody></tbody>
	</table>

	<?php
		do_action( 'before_plugin_advanced_zone' );
		// Display the community categorization options.
		the_plugin_community_zone();

		// Display the commercial categorization options.
		the_plugin_commercial_zone();

		// Display the advanced controls (only seen if the plugin is open).
		the_plugin_advanced_zone();

		do_action( 'after_plugin_advanced_zone' );

		// Display the danger zone (only shown to committers of the plugin).
		the_plugin_danger_zone();
	?>

</div>
