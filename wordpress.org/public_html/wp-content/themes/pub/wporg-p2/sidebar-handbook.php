<?php
/**
 * Sidebar template.
 *
 * @package P2
 */

if ( is_active_sidebar( wporg_get_current_handbook() ) ) : ?>
	<div id="sidebar">
		<?php do_action( 'before_sidebar' ); ?>
		<ul>
			<?php dynamic_sidebar( wporg_get_current_handbook() ); ?>
		</ul>
		<div class="clear"></div>
	</div> <!-- // sidebar -->
<?php
else :
	get_sidebar();
endif;

