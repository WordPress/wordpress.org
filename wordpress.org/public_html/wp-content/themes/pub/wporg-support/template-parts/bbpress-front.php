<?php
/**
 * Template part for displaying bbPress topics on the front page.
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package WPBBP
 */

?>

<?php do_action( 'bbp_before_main_content' ); ?>

<?php do_action( 'bbp_template_notices' ); ?>

<section>
	<p><?php _e( 'Our community-based Support Forums are a great place to learn, share, and troubleshoot. <a href="https://wordpress.org/support/welcome/">Get started!</a>', 'wporg-forums' ); ?></p>

	<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>
</section>

<section>
	<h2><?php _e( 'Topics', 'wporg-forums' ); ?></h2>

	<ul class="three-up cards-grid" id="views">
		<?php wporg_support_get_views(); ?>
	</ul>
</section>

<section>
	<p><?php
		/* translators: 1: Theme Directory URL, 2: Plugin Directory URL */
		printf( __( 'Looking for help with a specific <a href="%1$s">Theme</a> or <a href="%2$s">Plugin</a>?', 'wporg-forums' ),
			esc_url( __( 'https://wordpress.org/themes/', 'wporg-forums' ) ),
			esc_url( __( 'https://wordpress.org/plugins/', 'wporg-forums' ) ),
		);
	?></p>

	<p><?php _e( 'Every theme and plugin has their own. Head to their individual pages and click "View support forum".', 'wporg-forums' ); ?></p>
</section>

<section class="forum-home-footer">
	<div>
		<h2>More resources</h2>
		<div class="four-up">
			<a href="https://wordpress.org/documentation/">
				<h3>Documentation</h3>
				<p>Your first stop where you'll find information on everything.</p>
			</a>
			<a href="https://make.wordpress.org/support/handbook/">
				<h3>Support Handbook</h3>
				<p>Great for tips, tricks, and advice regarding giving the best support.</p>
			</a>
		</div>
	</div>
</section>

<?php do_action( 'bbp_after_main_content' ); ?>
