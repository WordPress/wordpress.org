<?php
/**
 * Template part for displaying the plugin administration sections.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Plugin_Directory;
use WordPressdotorg\Plugin_Directory\Template;
?>
<div id="admin" class="section">
	<h2><?php _e( 'Plugin Stats', 'wporg-plugins' ); ?></h2>

	<h4><?php _e( 'Active versions', 'wporg-plugins' ); ?></h4>
	<div id="plugin-version-stats" class="chart"></div>

	<h4><?php _e( 'Downloads Per Day', 'wporg-plugins' ); ?></h4>
	<div id="plugin-download-stats" class="chart"></div>

	<h5><?php _e( 'Downloads history', 'wporg-plugins' ); ?></h5>
	<table id="plugin-download-history-stats"><tbody></tbody></table>


</div>