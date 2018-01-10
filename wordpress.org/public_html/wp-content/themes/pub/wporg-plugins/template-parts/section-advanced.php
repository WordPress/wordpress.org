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

	<h2><?php esc_html_e( 'Plugin Stats', 'wporg-plugins' ); ?></h2>

	<h4><?php esc_html_e( 'Active versions', 'wporg-plugins' ); ?></h4>
	<div id="plugin-version-stats" class="chart version-stats"></div>

	<h4><?php esc_html_e( 'Downloads Per Day', 'wporg-plugins' ); ?></h4>
	<div id="plugin-download-stats" class="chart download-stats"></div>

	<h4><?php esc_html_e( 'Active Install Growth', 'wporg-plugins' ); ?></h4>
	<div id="plugin-growth-stats" class="chart download-stats"></div>

	<h5><?php esc_html_e( 'Downloads history', 'wporg-plugins' ); ?></h5>
	<table id="plugin-download-history-stats" class="download-history-stats">
		<tbody></tbody>
	</table>

	<?php the_previous_version_download(); ?>
</div>
