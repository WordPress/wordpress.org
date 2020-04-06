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

	<h2><?php esc_html_e( 'Statistics', 'wporg-plugins' ); ?></h2>

	<h4><?php esc_html_e( 'Active versions', 'wporg-plugins' ); ?></h4>
	<div id="plugin-version-stats" class="chart version-stats"></div>

	<h4><?php esc_html_e( 'Downloads Per Day', 'wporg-plugins' ); ?></h4>
	<div id="plugin-download-stats" class="chart download-stats"></div>

	<h4><?php esc_html_e( 'Active Install Growth', 'wporg-plugins' ); ?></h4>
	<div id="plugin-growth-stats" class="chart download-stats"></div>

	<h4><?php esc_html_e( 'Downloads history', 'wporg-plugins' ); ?></h4>
	<table id="plugin-download-history-stats" class="download-history-stats">
		<tbody></tbody>
	</table>

	<hr>

	<h2><?php esc_html_e( 'Advanced Options', 'wporg-plugins' ); ?></h2>

	<p><?php esc_html_e( 'This section is intended for advanced users and developers only. They are presented here for testing and educational purposes.', 'wporg-plugins' ); ?></p>

	<?php the_previous_version_download(); ?>

	<hr>

	<h2><?php esc_html_e( 'The Danger Zone', 'wporg-plugins' ); ?></h2>

	<p><?php esc_html_e( 'The following features are restricted to plugin committers only. They exist to allow plugin developers more control over their work.', 'wporg-plugins' ); ?></p>

	<div class="plugin-notice notice notice-error notice-alt"><p><?php esc_html_e( 'These features often cannot be undone without intervention. Please do not attempt to use them unless you are absolutely certain. When in doubt, contact the plugins team for assistance.', 'wporg-plugins' ); ?></p></div>

	<?php the_plugin_self_transfer_form(); ?>

	<?php the_plugin_self_close_button(); ?>

</div>
