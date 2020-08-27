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

$tested_up_to = (string) get_post_meta( $post->ID, 'tested', true );
?>
<article <?php post_class( 'plugin-card' ); ?>>
	<div class="entry-thumbnail">
		<a href="<?php the_permalink(); ?>" rel="bookmark">
			<?php
			// phpcs:ignore WordPress.XSS.EscapeOutput.OutputNotEscaped
			echo Template::get_plugin_icon( get_post(), 'html' );
			?>
		</a>
	</div><div class="entry">
		<header class="entry-header">
			<?php the_title( '<h3 class="entry-title"><a href="' . esc_url( get_permalink() ) . '" rel="bookmark">', '</a></h3>' ); ?>
		</header><!-- .entry-header -->

		<?php echo wp_kses_post( Template::get_star_rating() ); ?>

		<div class="entry-excerpt">
			<?php the_excerpt(); ?>
		</div><!-- .entry-excerpt -->
	</div>
	<hr>
	<footer>
		<span class="plugin-author">
			<i class="dashicons dashicons-admin-users"></i> <?php echo esc_html( strip_tags( get_post_meta( get_the_ID(), 'header_author', true ) ) ?: get_the_author() ); ?>
		</span>
		<span class="active-installs">
			<i class="dashicons dashicons-chart-area"></i>
			<?php echo esc_html( Template::active_installs() ); ?>
		</span>
		<?php if ( $tested_up_to ) : ?>
			<span class="tested-with">
				<i class="dashicons dashicons-wordpress-alt"></i>
				<?php
				/* translators: WordPress version. */
				printf( esc_html__( 'Tested with %s', 'wporg-plugins' ), esc_html( $tested_up_to ) );
				?>
			</span>
		<?php endif; ?>
		<span class="last-updated">
			<i class="dashicons dashicons-calendar"></i>
			<?php
			/* Translators: Plugin modified time. */
			printf( esc_html__( 'Updated %s ago', 'wporg-plugins' ), esc_html( human_time_diff( get_post_modified_time() ) ) );
			?>
		</span>
	</footer>
	<?php /* This file must not end with a new line */ ?>
</article>