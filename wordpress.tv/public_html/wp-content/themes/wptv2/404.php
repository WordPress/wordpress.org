<?php
/*
 * WordCamp.tv Index Fallback
 *
 * It will be weird if somebody sees this (but okay if 404)
 */

get_header();
global $wp_query, $post, $wptv;
?>
	<div class="wptv-hero">
		<h2 class="page-title"><?php esc_html_e( 'Whoops!', 'wptv' ); ?></h2>
	</div>
	<div class="container">
		<div class="primary-content">
			<div class="baron-von-pick">
				<img src="<?php echo get_stylesheet_directory_uri(); ?>/i/michael-pick-stashes-a-guinness.gif" alt="" /><br />
				<?php printf( __( 'Photo animation credit: %s.', 'wptv' ), '<a href="http://markjaquith.com/">Mark Jaquith</a>' ); ?>
			</div>
			<div class="message-404">
				<h2><?php esc_html_e( 'Uh oh, someone made a mistake!', 'wptv' ); ?></h2>
				<p><?php esc_html_e( 'These sorts of things happen&hellip;', 'wptv' ); ?></p>
				<p><?php esc_html_e( 'Try searching for what you were looking for.', 'wptv' ); ?></p>
				<p><?php echo get_search_form(); ?></p>
				<p><?php printf( __( 'Or, <a href="%s">visit the homepage</a> to start a fresh journey.', 'wptv' ), '/' ); ?></p>
			</div>
		</div>
	</div><!-- container -->
<?php
get_footer();
