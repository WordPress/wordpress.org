<?php
namespace WordPressdotorg\Theme\Plugins_2024\PluginCard;

use WordPressdotorg\Plugin_Directory\Template;

$post = get_post();
// Simulates wporg/link-wrapper block until this is migrated to a block, or it supports href=permalink.
do_blocks( '<!-- wp:wporg/link-wrapper /-->' ); // Import the styles
?>
<a class="plugin-card wp-block-wporg-link-wrapper is-style-no-underline" href="<?php the_permalink(); ?>">
	<div class="entry">
		<div class="entry-thumbnail">
			<?php echo Template::get_plugin_icon( get_post(), 'html' ); ?>
		</div>

		<header class="entry-header">
			<?php the_title( '<h3 class="entry-title">', '</h3>' ); ?>
		</header><!-- .entry-header -->

		<?php echo wp_kses_post( Template::get_star_rating( $post, false ) ); ?>

		<div class="entry-excerpt">
			<?php the_excerpt(); ?>
		</div><!-- .entry-excerpt -->
	</div>

	<footer>
		<span class="plugin-author">
			<i class="dashicons dashicons-admin-users"></i> <?php echo esc_html( strip_tags( get_post_meta( get_the_ID(), 'header_author', true ) ) ?: get_the_author() ); ?>
		</span>
		<span class="active-installs">
			<i class="dashicons dashicons-chart-area"></i>
			<?php echo esc_html( Template::active_installs() ); ?>
		</span>
		<?php if ( $post->tested ) : ?>
			<span class="tested-with">
				<i class="dashicons dashicons-wordpress-alt"></i>
				<?php
				/* translators: WordPress version. */
				printf( esc_html__( 'Tested with %s', 'wporg-plugins' ), esc_html( $post->tested ) );
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
</a>