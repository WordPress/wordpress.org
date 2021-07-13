<?php
/**
 * Template for search form.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Pattern_Directory\Theme
 */

?>
<form role="search" method="get" action="<?php echo esc_url( home_url( '/' ) ); ?>" class="pattern-search">
	<label for="s" class="screen-reader-text"><?php echo esc_html( _x( 'Search for:', 'label', 'wporg-patterns' ) ); ?></label>
	<input
		type="search"
		id="s"
		value="<?php echo get_search_query(); ?>"
		name="s"
		placeholder="<?php esc_html_e( 'Search patterns', 'wporg-patterns' ); ?>"
		required
	/>
	<button type="submit" class="pattern-search__button">
		<span class="screen-reader-text">
			<?php esc_html_e( 'Search', 'wporg-patterns' ); ?>
		</span>
		<i class="dashicons dashicons-search"></i>
	
	</button>
</form>
