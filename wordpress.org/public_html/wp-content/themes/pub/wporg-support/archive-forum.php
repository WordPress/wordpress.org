<?php

/**
 * Template Name: bbPress - Support (Index)
 *
 * @package bbPress
 * @subpackage Theme
 */

get_header(); ?>


	<main id="main" class="site-main" role="main">

		<?php get_template_part( 'template-parts/bbpress', 'front' ); ?>

	</main>

	<div class="forum-home-footer has-text-color has-white-color has-background has-charcoal-2-background-color has-small-font-size">
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
	</div>

<?php
get_footer();
