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

	<ul class="three-up cards-grid" id="views">
		<?php wporg_support_get_views(); ?>
	</ul>

	<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>
</section>

<section class="forum-home-footer">
	<div class="two-up">
		<h2>Related</h2>
		<div class="two-up cards-grid">
			<a href="<?php esc_url( __( 'https://wordpress.org/documentation/', 'wporg-forums' ) ) ?>">
				<h3>Documentation</h3>
				<p>Your first stop where you'll find information on everything from installing to creating plugins.</p>
			</a>
			<a href="<?php esc_url( __( 'https://make.wordpress.org/support/handbook/', 'wporg-forums' ) ) ?>">
				<h3>See the Support Handbook</h3>
				<p>The Support Handbook is great for tips, tricks, and advice regarding giving the best support possible.</p>
			</a>
		</div>
	</div>
	<div class="two-up">
		<h2>Other Forums</h2>
		<div class="two-up cards-grid">
			<a href="<?php esc_url( __( 'https://wordpress.org/themes/', 'wporg-forums' ) ) ?>">
				<h3>Themes Forum</h3>
				<p>[TBD] Head to the theme's page and find the "View support forum" link to visit the theme's individual forum.</p>
			</a>
			<a href="<?php esc_url( __( 'https://wordpress.org/plugins/', 'wporg-forums' ) ) ?>">
				<h3>Plugins Forum</h3>
				<p>[TBD] Head to the plugin's page and find the "View support forum" link to visit the plugin's individual forum.</p>
			</a>
		</div>
	</div>
</section>

<?php do_action( 'bbp_after_main_content' ); ?>
