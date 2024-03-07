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
	<p><?php printf(
		/* Translators: forums URL */
		__( 'Our community-based Support Forums are a great place to learn, share, and troubleshoot. <a href="%s">Get started!</a>', 'wporg-forums' ),
		esc_url( site_url( '/welcome/' ) )
	) ?></p>
</section>

<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>

<?php do_action( 'bbp_after_main_content' ); ?>
