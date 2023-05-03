<?php
/**
 * The template for displaying search forms.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="s" class="screen-reader-text"><?php echo esc_html_x( 'Search for:', 'label', 'wporg-plugins' ); ?></label>
	<input type="search" id="s" class="search-field" placeholder="<?php echo esc_attr_x( 'Search plugins', 'placeholder', 'wporg-plugins' ); ?>" value="<?php the_search_query(); ?>" name="s" />
	<?php if ( get_query_var( 'block_search' ) || 'block' === get_query_var( 'browse' ) ) : ?>
		<input type="hidden" value="1" name="block_search" />
	<?php endif; ?>
	<button class="button button-primary button-search">
		<svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path fill-rule="evenodd" clip-rule="evenodd" d="M17.5 11.5a4 4 0 1 1-8 0 4 4 0 0 1 8 0Zm1.5 0a5.5 5.5 0 0 1-9.142 4.121l-3.364 2.943-.988-1.128 3.373-2.952A5.5 5.5 0 1 1 19 11.5Z" /></svg>
		<span class="screen-reader-text"><?php esc_html_e( 'Search plugins', 'wporg-plugins' ); ?></span>
	</button>
</form>

