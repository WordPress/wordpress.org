<?php
/**
 * Plugin name: WP-CLI: WordPress.org Customizations
 * Description: Provides general customizations for WP-CLI's presence on WordPress.org
 * Version:     0.1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */

require_once dirname( __FILE__ ) . '/inc/class-markdown-import.php';
require_once dirname( __FILE__ ) . '/inc/class-handbook.php';

/**
 * Registry of actions and filters
 */
add_action( 'init', array( 'WPOrg_Cli\Markdown_Import', 'action_init' ) );
add_action( 'wporg_cli_markdown_import', array( 'WPOrg_Cli\Markdown_Import', 'action_wporg_cli_markdown_import' ) );
add_action( 'load-post.php', array( 'WPOrg_Cli\Markdown_Import', 'action_load_post_php' ) );
add_action( 'edit_form_after_title', array( 'WPOrg_Cli\Markdown_Import', 'action_edit_form_after_title' ) );
add_action( 'save_post', array( 'WPOrg_Cli\Markdown_Import', 'action_save_post' ) );
add_filter( 'cron_schedules', array( 'WPOrg_Cli\Markdown_Import', 'filter_cron_schedules' ) );
add_filter( 'the_title', array( 'WPOrg_Cli\Handbook', 'filter_the_title_edit_link' ) );
add_filter( 'get_edit_post_link', array( 'WPOrg_Cli\Handbook', 'redirect_edit_link_to_github' ), 10, 3 );
add_filter( 'o2_filter_post_actions', array( 'WPOrg_Cli\Handbook', 'redirect_o2_edit_link_to_github' ), 11, 2 );

add_action( 'wp_head', function(){
	?>
	<style>
		pre code {
			line-height: 16px;
		}
		a.github-edit {
			margin-left: .5em;
			font-size: .5em;
			vertical-align: top;
			display: inline-block;
			border: 1px solid #eeeeee;
			border-radius: 2px;
			background: #eeeeee;
			padding: .5em .6em .4em;
			color: black;
			margin-top: 0.1em;
		}
		a.github-edit > * {
			opacity: 0.6;
		}
		a.github-edit:hover > * {
			opacity: 1;
			color: black;
		}
		a.github-edit img {
			height: .8em;
		}
	</style>
	<?php
});
