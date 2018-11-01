<?php
/**
 * Forums Loop Homepage
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_forums_loop' ); ?>

<div id="forums-list-<?php bbp_forum_id(); ?>" class="bbp-forums three-up">


		<?php
		while ( bbp_forums() ) :
			bbp_the_forum();
			bbp_get_template_part( 'loop', 'single-forum-homepage' );
		endwhile;
		?>

</div><!-- .forums-directory -->

<div class="themes-plugins">

	<h3><?php esc_html_e( 'Themes and Plugins', 'wporg-forums' ); ?></h3>
	<p>
		<?php
		printf(
			/* translators: 1: Theme Directory URL, 2: Appearance icon, 3: Plugin Directory URL, 4: Plugins icon */
			esc_html__( 'Looking for help with a specific <a href="%1$s">%2$s theme</a> or <a href="%3$s">%4$s plugin</a>? Head to the theme or plugin\'s page and find the "View support forum" link to visit the theme or plugin\'s individual forum.', 'wporg-forums' ),
			esc_url( esc_html__( 'https://wordpress.org/themes/', 'wporg-forums' ) ),
			'<span class="dashicons dashicons-admin-appearance"></span>',
			esc_url( esc_html__( 'https://wordpress.org/plugins/', 'wporg-forums' ) ),
			'<span class="dashicons dashicons-admin-plugins"></span>'
		);
		?>
	</p>

</div>

<?php do_action( 'bbp_template_after_forums_loop' ); ?>
