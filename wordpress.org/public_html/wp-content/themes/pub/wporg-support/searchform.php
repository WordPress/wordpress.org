<?php
namespace WordPressdotorg\Forums;
?>

<?php if ( bb_is_intl_forum() ) : ?>

<form role="search" method="get" class="search-form" action="<?php bbp_search_url(); ?>">
	<label for="s" class="screen-reader-text"><?php _ex( 'Search for:', 'label', 'wporg-forums' ); ?></label>
	<input type="hidden" name="action" value="bbp-search-request" />
	<input type="search" id="s" class="search-field" placeholder="<?php echo esc_attr_x( 'Search forums', 'placeholder', 'wporg-forums' ); ?>" value="<?php echo esc_attr( bbp_get_search_terms() ); ?>" name="bbp_search" />
	<button class="button button-primary button-search"><i class="dashicons dashicons-search"></i><span class="screen-reader-text"><?php _e( 'Search forums', 'wporg-forums' ); ?></span></button>
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
	<button class="button button-primary button-search"><i class="dashicons dashicons-search"></i><span class="screen-reader-text"><?php _e( 'Search forums', 'wporg-forums' ); ?></span></button>
</form>

<?php endif; ?>
