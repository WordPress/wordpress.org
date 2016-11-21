<?php
/**
 * Template part for displaying plugins.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Template;


?><article id="post-<?php the_ID(); ?>" <?php post_class( 'plugin-card' ); ?>>
	<div class="entry-thumbnail">
		<a href="<?php the_permalink(); ?>" rel="bookmark">
			<?php echo Template::get_plugin_icon( get_post(), 'html' ); ?>
		</a>
	</div><div class="entry">
		<header class="entry-header">
			<?php the_title( '<h2 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h2>' ); ?>
		</header><!-- .entry-header -->

		<?php echo Template::get_star_rating(); ?>

		<div class="entry-excerpt">
			<?php the_excerpt(); ?>
		</div><!-- .entry-excerpt -->
	</div>
	<hr>
	<footer>
		<span class="plugin-author">
			<i class="dashicons dashicons-admin-users"></i> <?php echo esc_html( get_post_meta( get_the_ID(), 'header_author', true ) ); ?>
		</span>
		<span class="active-installs">
			<i class="dashicons dashicons-chart-area"></i> <?php printf( __( '%s Active Installs', 'wporg-plugins' ), Template::active_installs(false) ); ?>
		</span>
		<span class="tested-with">
			<?php if ( $tested_up_to = (string) get_post_meta( $post->ID, 'tested', true ) ) { ?>
				<i class="dashicons dashicons-wordpress-alt"></i> <?php printf( __( 'Tested with %s', 'wporg-plugins' ), $tested_up_to ); ?></span>
			<?php } ?>
		</span>
		<span class="last-updated">
			<i class="dashicons dashicons-calendar"></i> <?php
				/* Translators: Plugin modified time. */
				printf( __( 'Updated %s ago', 'wporg-plugins' ), human_time_diff( get_post_modified_time() ) ); ?>
		</span>
		</span>
	</footer>
</article><!-- #post-## -->