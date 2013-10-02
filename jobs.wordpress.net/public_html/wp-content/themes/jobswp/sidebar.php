<?php
/**
 * The Sidebar containing the main widget areas.
 *
 * @package jobswp
 */
?>
	<div id="secondary" class="widget-area grid_3" role="complementary">
		<?php do_action( 'before_sidebar' ); ?>

			<aside id="cats" class="widget">
				<h3 class="widget-title"><?php _e( 'Position Types', 'jobswp' ); ?></h3>
				<ul>
					<li class="job-cat-item job-cat-item-all"><a href="/" title="<?php esc_attr_e( 'View all job openings', 'jobswp' ); ?>"><?php _e( 'All Openings', 'jobswp' ) ?></a></li>
				<?php Jobs_Dot_WP::list_job_categories(); ?>
				</ul>
			</aside>

		<?php dynamic_sidebar( 'sidebar-1' ); ?>

	</div><!-- #secondary -->
