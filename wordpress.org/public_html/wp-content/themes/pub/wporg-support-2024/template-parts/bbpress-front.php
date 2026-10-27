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

<?php bbp_get_template_part( 'content', 'archive-forum' ); ?>

<?php do_action( 'bbp_after_main_content' ); ?>
