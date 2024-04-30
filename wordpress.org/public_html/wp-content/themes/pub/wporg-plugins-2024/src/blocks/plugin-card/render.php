<?php
namespace WordPressdotorg\Theme\Plugins_2024\PluginCard;

use WordPressdotorg\Plugin_Directory\Template;

$post = get_post();
// Simulates wporg/link-wrapper block until this is migrated to a block, or it supports href=permalink.
do_blocks( '<!-- wp:wporg/link-wrapper /-->' ); // Import the styles
?>
<div class="plugin-card wp-block-wporg-link-wrapper is-style-no-underline">
	<div class="entry">
		<div class="entry-thumbnail">
			<?php echo Template::get_plugin_icon( get_post(), 'html' ); ?>
		</div>

		<header class="entry-header">
			<h3 class="entry-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
		</header><!-- .entry-header -->

		<?php echo wp_kses_post( Template::get_star_rating( $post, false ) ); ?>

		<div class="entry-excerpt">
			<?php the_excerpt(); ?>
		</div><!-- .entry-excerpt -->
	</div>

	<footer>
		<span class="plugin-author">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M15.5 9.5a1 1 0 100-2 1 1 0 000 2zm0 1.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5zm-2.25 6v-2a2.75 2.75 0 00-2.75-2.75h-4A2.75 2.75 0 003.75 15v2h1.5v-2c0-.69.56-1.25 1.25-1.25h4c.69 0 1.25.56 1.25 1.25v2h1.5zm7-2v2h-1.5v-2c0-.69-.56-1.25-1.25-1.25H15v-1.5h2.5A2.75 2.75 0 0120.25 15zM9.5 8.5a1 1 0 11-2 0 1 1 0 012 0zm1.5 0a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z" fill-rule="evenodd"></path></svg>
			<span><?php echo esc_html( strip_tags( get_post_meta( get_the_ID(), 'header_author', true ) ) ?: get_the_author() ); ?></span>
		</span>
		<span class="active-installs">
			<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path fill-rule="evenodd" d="M11.25 5h1.5v15h-1.5V5zM6 10h1.5v10H6V10zm12 4h-1.5v6H18v-6z" clip-rule="evenodd"></path></svg>
			<span><?php echo esc_html( Template::active_installs() ); ?></span>
		</span>
		<?php if ( $post->tested ) : ?>
			<span class="tested-with">
				<svg xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 24 24" width="24" height="24" aria-hidden="true" focusable="false"><path d="M20 10c0-5.51-4.49-10-10-10C4.48 0 0 4.49 0 10c0 5.52 4.48 10 10 10 5.51 0 10-4.48 10-10zM7.78 15.37L4.37 6.22c.55-.02 1.17-.08 1.17-.08.5-.06.44-1.13-.06-1.11 0 0-1.45.11-2.37.11-.18 0-.37 0-.58-.01C4.12 2.69 6.87 1.11 10 1.11c2.33 0 4.45.87 6.05 2.34-.68-.11-1.65.39-1.65 1.58 0 .74.45 1.36.9 2.1.35.61.55 1.36.55 2.46 0 1.49-1.4 5-1.4 5l-3.03-8.37c.54-.02.82-.17.82-.17.5-.05.44-1.25-.06-1.22 0 0-1.44.12-2.38.12-.87 0-2.33-.12-2.33-.12-.5-.03-.56 1.2-.06 1.22l.92.08 1.26 3.41zM17.41 10c.24-.64.74-1.87.43-4.25.7 1.29 1.05 2.71 1.05 4.25 0 3.29-1.73 6.24-4.4 7.78.97-2.59 1.94-5.2 2.92-7.78zM6.1 18.09C3.12 16.65 1.11 13.53 1.11 10c0-1.3.23-2.48.72-3.59C3.25 10.3 4.67 14.2 6.1 18.09zm4.03-6.63l2.58 6.98c-.86.29-1.76.45-2.71.45-.79 0-1.57-.11-2.29-.33.81-2.38 1.62-4.74 2.42-7.1z"></path></svg>
				<span><?php
				/* translators: WordPress version. */
				printf( esc_html__( 'Tested with %s', 'wporg-plugins' ), esc_html( $post->tested ) );
				?></span>
			</span>
		<?php endif; ?>
	</footer>
</div>