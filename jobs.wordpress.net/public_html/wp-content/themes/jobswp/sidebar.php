<?php
/**
 * The Sidebar containing the main widget areas.
 *
 * @package jobswp
 */
?>
	<div id="secondary" class="widget-area" role="complementary">
		<?php do_action( 'before_sidebar' ); ?>

			<aside id="cats" class="widget">
				<h3 class="widget-title"><?php _e( 'Position Types', 'jobswp' ); ?></h3>
				<a href="#" class="menu-jobs-toggle"></a>
				<ul class="menu-jobs">
					<li class="job-cat-item job-cat-item-all"><a href="/" title="<?php esc_attr_e( 'View all job openings', 'jobswp' ); ?>"><?php _e( 'All Openings', 'jobswp' ) ?></a></li>
				<?php Jobs_Dot_WP::list_job_categories(); ?>
				</ul>
			</aside>

		<?php dynamic_sidebar( 'sidebar-1' ); ?>

		<?php
		// "Open to Work" sidebar widget.
		$sidebar_result     = jobswp_get_open_to_work_candidates( 1, 5 );
		$sidebar_candidates = $sidebar_result['candidates'];
		if ( ! empty( $sidebar_candidates ) ) :
			?>
			<aside class="widget widget-open-to-work">
				<h3 class="widget-title"><?php esc_html_e( 'Open to Work', 'jobswp' ); ?></h3>
				<ul class="open-to-work-list">
					<?php foreach ( $sidebar_candidates as $person ) : ?>
						<li>
							<a href="<?php echo esc_url( $person->profile_url ); ?>" target="_blank" rel="noopener noreferrer">
								<img src="<?php echo esc_url( 'https://wordpress.org/grav-redirect.php?user=' . urlencode( $person->user_login ) . '&s=32' ); ?>" alt="" width="32" height="32" loading="lazy">
								<span><?php echo esc_html( $person->display_name ); ?></span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
				<a href="<?php echo esc_url( home_url( '/#candidates' ) ); ?>" class="widget-open-to-work__more"><?php esc_html_e( 'View all &rarr;', 'jobswp' ); ?></a>
			</aside>
		<?php endif; ?>

	</div><!-- #secondary -->
