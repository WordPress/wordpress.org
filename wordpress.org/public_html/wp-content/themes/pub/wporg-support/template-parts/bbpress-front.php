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
	<h2 class="has-heading-5-font-size"><?php _e( 'Topics', 'wporg-forums' ); ?></h2>

	<div class="wp-block-group is-style-cards-grid has-small-font-size is-layout-grid wp-block-group-is-layout-grid">
		<?php wporg_support_get_views(); ?>
	</div>
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

<section class="forum-home-footer has-text-color has-white-color has-background has-charcoal-2-background-color has-small-font-size">
	<h2 class="has-heading-5-font-size"><?php _e( 'More resources', 'wporg-forums' ); ?></h2>
	<div>
		<a href="https://wordpress.org/documentation/">
			<h3 class="has-blueberry-2-color has-text-color has-link-color has-inter-font-family has-normal-font-size">
				<?php _e( 'Documentation', 'wporg-forums' ); ?>
			</h3>
			<p><?php _e( "Your first stop where you'll find information on everything.", 'wporg-forums' ); ?></p>
		</a>
		<a href="https://make.wordpress.org/support/handbook/">
			<h3 class="has-blueberry-2-color has-text-color has-link-color has-inter-font-family has-normal-font-size">
				<?php _e( 'Support Handbook', 'wporg-forums' ); ?>
			</h3>
			<p><?php _e( 'Great for tips, tricks, and advice regarding giving the best support.', 'wporg-forums' ); ?></p>
		</a>
	</div>
</section>

<?php do_action( 'bbp_after_main_content' ); ?>
