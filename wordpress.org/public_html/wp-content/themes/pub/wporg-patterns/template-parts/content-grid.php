<?php
/**
 * The template part for displaying content
 *
 * @package WordPressdotorg\Pattern_Directory\Theme;
 */

namespace WordPressdotorg\Pattern_Directory\Theme;

?>

<div id="post-<?php the_ID(); ?>" <?php post_class( 'pattern-grid__pattern' ); ?>>
	<?php /* This link only wraps the "preview" container to avoid nesting the buttons inside the link. */ ?>
	<a href="<?php echo esc_url( get_permalink() ); ?>" rel="bookmark">
		<?php the_title( '<span class="screen-reader-text">', '</span>' ); ?>
		<div class="pattern-grid__preview" style="height:<?php echo esc_attr( rand( 100, 300 ) ); ?>px">
			Pattern ID: <?php the_ID(); ?>
		</div>
	</a>
	<div class="pattern-grid__actions">
		<?php the_title( '<h2 class="pattern-grid__title">', '</h2>' ); ?>
		<button class="button button-link pattern-favorite-button">
			<svg class="pattern__favorite-outline" width="24" height="24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path fill-rule="evenodd" clip-rule="evenodd" d="M12 4.915c1.09-1.28 2.76-2.09 4.5-2.09 3.08 0 5.5 2.42 5.5 5.5 0 3.777-3.394 6.855-8.537 11.518l-.013.012-1.45 1.32-1.45-1.31-.04-.036C5.384 15.17 2 12.095 2 8.325c0-3.08 2.42-5.5 5.5-5.5 1.74 0 3.41.81 4.5 2.09zm0 13.56l.1-.1c4.76-4.31 7.9-7.16 7.9-10.05 0-2-1.5-3.5-3.5-3.5-1.54 0-3.04.99-3.56 2.36h-1.87c-.53-1.37-2.03-2.36-3.57-2.36-2 0-3.5 1.5-3.5 3.5 0 2.89 3.14 5.74 7.9 10.05l.1.1z" fill="#000"></path>
			</svg>
			<svg class="pattern__favorite-filled" width="24" height="24" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M11.941 21.175l-1.443-1.32c-5.124-4.67-8.508-7.75-8.508-11.53 0-3.08 2.408-5.5 5.473-5.5 1.732 0 3.394.81 4.478 2.09 1.085-1.28 2.747-2.09 4.478-2.09 3.065 0 5.473 2.42 5.473 5.5 0 3.78-3.383 6.86-8.508 11.54l-1.443 1.31z" fill="#000"></path>
			</svg>
		</button>
		<button class="button button-primary pattern-copy-button is-small"><?php esc_html_e( 'Copy', 'wporg-patterns' ); ?></button>
	</div>
</div>
