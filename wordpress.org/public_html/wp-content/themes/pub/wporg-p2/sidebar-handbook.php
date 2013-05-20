<?php
/**
 * Sidebar template.
 *
 * @package P2
 */


if ( is_active_sidebar( 'handbook' ) ) : ?>
	<div id="sidebar">
		<?php do_action( 'before_sidebar' ); ?>
		<ul>
			<?php dynamic_sidebar( 'handbook' ); ?>
		</ul>
		<div class="clear"></div>
	</div> <!-- // sidebar -->
<?php
else :
	get_sidebar();
endif;

