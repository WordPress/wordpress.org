			</div>
		</div>
		<hr class="hidden" />

		<?php if ( is_singular( 'page' ) ) : ?>

			<div class="footer-meta-wrap">
				<div class="footer-meta">
					<div class="col-half">
						<h3 class="title">Article Contributors</h3>

						<?php

						global $codex_contributors;

						if ( count( $codex_contributors ) ) : ?>

							<div class="contributors">

								<?php
								$codex_contributors = array_slice( $codex_contributors, 0, 3, true );
								foreach ( (array) $codex_contributors as $contributor_id => $count ) :
									$userdata = get_userdata( $contributor_id ); ?>

									<div class="contributor">
										<a href="#">
											<div class="contributor-avatar float-left">
												<?php echo get_avatar( $contributor_id, 48 ); ?>
												<div class="revision-count"><?php echo esc_html( $count ); ?></div>
												<div class="contributor-name"><span><?php echo esc_html( $userdata->display_name ); ?></span></div>
											</div>
										</a>
									</div>

								<?php endforeach; ?>

							</div>

						<?php endif; ?>

						<p class="date">Updated <strong><?php echo human_time_diff( get_the_modified_time( 'U', get_queried_object_id() ) ); ?></strong> ago / Published <strong><?php echo human_time_diff( get_the_time( 'U', get_queried_object_id() ) ); ?></strong> ago</p>
					</div>

					<div class="col-half">
						<h3 class="title">Want to help?</h3>
						<p>The bbPress Codex is volunteer-powered, which means you can contribute too! If you're interested in updating existing articles or creating entirely new ones, please read our <a href="http://codex.bbpress.org/participate-and-contribute/codex-standards-guidelines/">Codex Standards & Guidelines</a>.</p>
					</div>
				</div>
			</div>

		<?php endif; ?>

		<div id="footer">
			<div class="links">
				<p>
					See also: 
					<a href="http://wordpress.org"><?php _e( 'WordPress.org', 'bbporg'); ?></a> &bull;
					<a href="http://bbpress.org"><?php _e( 'bbPress.org', 'bbporg'); ?></a> &bull;
					<a href="http://buddypress.org"><?php _e( 'BuddyPress.org', 'bbporg'); ?></a> &bull;
					<a href="http://ma.tt"><?php _e( 'Matt', 'bbporg' ); ?></a> &bull;
					<a href="<?php bloginfo( 'rss2_url' ); ?>" title="<?php esc_attr_e( 'RSS Feed for Articles', 'bbporg' ); ?>"><?php _e( 'Blog RSS', 'bbporg' ); ?></a>
				</p>
			</div>
			<div class="details">
				<p>
					<a href="http://twitter.com/bbpress" class="twitter"><?php _e( 'Follow bbPress on Twitter', 'bbporg'); ?></a> &bull;
					<a href="http://bbpress.org/about/gpl/"><?php _e('GPL', 'bbporg'); ?></a> &bull;
					<a href="http://bbpress.org/contact/"><?php _e('Contact Us', 'bbporg'); ?></a> &bull; 
					<a href="http://bbpress.org/terms/"><?php _e('Terms of Service', 'bbporg'); ?></a>
				</p>
			</div>
		</div>
		<?php wp_footer(); ?>
	</body>
</html>