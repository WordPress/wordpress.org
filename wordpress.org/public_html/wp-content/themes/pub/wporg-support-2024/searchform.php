<?php
namespace WordPressdotorg\Forums;
?>

<?php if ( bb_is_intl_forum() ) : ?>

<form role="search" method="get" class="search-form" action="<?php bbp_search_url(); ?>">
	<label for="s" class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg-forums' ); ?></label>
	<input type="hidden" name="action" value="bbp-search-request" />
	<input type="search" id="s" class="search-field" placeholder="<?php echo esc_attr_x( 'Search forums', 'placeholder', 'wporg-forums' ); ?>" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" />
	<button class="button button-search">
		<svg class="search-icon" viewBox="0 0 24 24" width="24" height="24">
			<path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z"></path>
		</svg>
		<span class="screen-reader-text"><?php _e( 'Search forums', 'wporg-forums' ); ?></span>
	</button>
</form>

<?php else : ?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label for="s" class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg-forums' ); ?></label>
	<?php
		$tab = null;
		if ( in_array( current_action(), [ 'bbp_template_before_pagination_loop', 'wporg_compat_before_single_view' ] ) ) {
			$placeholder = _x( 'Search this forum', 'placeholder', 'wporg-forums' );
			$project     = wporg_support_get_compat_object();
		} elseif ( is_front_page() ) {
			$placeholder = _x( 'Search documentation', 'placeholder', 'wporg-forums' );
			$project     = null;
			$tab         = 'docs';
		} else {
			$placeholder = _x( 'Search forums', 'placeholder', 'wporg-forums' );
			$project     = null;
		}
	?>
	<input type="search" id="s" class="search-field" placeholder="<?php echo esc_attr( $placeholder ); ?>" value="<?php the_search_query(); ?>" name="s" />
	<?php if ( $project ) : ?>
	<input type="hidden" name="intext" value="<?php echo esc_attr( $project->search_prefix ); ?>" />
	<?php endif; ?>
	<?php if ( $tab ) : ?>
	<input type="hidden" name="tab" value="<?php echo esc_attr( $tab ); ?>" />
	<?php endif; ?>
	<button class="button button-search">
		<svg class="search-icon" viewBox="0 0 24 24" width="24" height="24">
			<path d="M13 5c-3.3 0-6 2.7-6 6 0 1.4.5 2.7 1.3 3.7l-3.8 3.8 1.1 1.1 3.8-3.8c1 .8 2.3 1.3 3.7 1.3 3.3 0 6-2.7 6-6S16.3 5 13 5zm0 10.5c-2.5 0-4.5-2-4.5-4.5s2-4.5 4.5-4.5 4.5 2 4.5 4.5-2 4.5-4.5 4.5z"></path>
		</svg>
		<span class="screen-reader-text"><?php _e( 'Search forums', 'wporg-forums' ); ?></span>
	</button>
</form>

<?php endif; ?>
