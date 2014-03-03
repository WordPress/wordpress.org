			</div>

			<?php if ( function_exists( 'is_bbpress' ) && is_bbpress() ) locate_template( array( 'bbpress-sidebar.php' ), true ); ?>

		</div>
		<hr class="hidden" />

		<?php if ( is_singular( 'page' ) ) { ?>

		<div class="footer-meta-wrap">

			<div class="footer-meta">

			<div class="col-half">

			<h3 class="title">Article Contributors</h3>
			
			<?php

			global $codex_contributors, $post;

			if ( count( $codex_contributors ) ) {

				/*

				TODO: Noel Tock
				Comment out Owner (as per WCLDN discussion)

				echo '<div class="page-contributors widget">';
				echo '<h3 class="widgettitle">Owner</h3>';
				$userdata = get_userdata( $post->post_author );
					echo '<div class="page-contributor">';
						echo '<div class="contributor-mug float-left">';
						echo get_avatar( $post->post_author, 48 );
						echo '</div>';
						echo '<div class="inner">';
						echo '<h5>' . esc_html( $userdata->display_name ) . '</h5>';
						echo '<p>' . esc_html( $codex_contributors[$post->post_author] ) . ' revision';
						if ( $codex_contributors[$post->post_author] > 1 ) 
							echo 's';
						echo '</p>';
						echo '</div>';
					echo '</div>';
				echo '</div>';
				unset( $codex_contributors[$post->post_author] );
				*/

				if ( count( $codex_contributors ) ) {
					echo '<div class="contributors">';
					$codex_contributors = array_slice( $codex_contributors, 0, 3, true );
					foreach( (array)$codex_contributors as $contributor_id => $count ) {
						$userdata = get_userdata( $contributor_id );
						echo '<div class="contributor">';
							echo '<a href="#"><div class="contributor-avatar float-left">';
								echo get_avatar( $contributor_id, 48 );
								echo '<div class="revision-count">' . esc_html( $count ) . '</div>';
								echo '<div class="contributor-name"><span>' . esc_html( $userdata->display_name ) . '</span></div>';
							echo '</div></a>';
						echo '</div>';
					}
					echo '</div>';
				}
			}
		?>

			<p class="date">Updated <strong><?php echo human_time_diff( get_the_modified_time( 'U', get_queried_object_id() ) ); ?></strong> ago / Published <strong><?php echo human_time_diff( get_the_time( 'U', get_queried_object_id() ) ); ?></strong> ago</p>

			</div>

			<div class="col-half">

			<h3 class="title">Want to help?</h3>

			<p>The BuddyPress Codex is volunteer-powered, which means you can contribute too! If you're interested in updating existing articles or creating entirely new ones, please read our <a href="http://codex.buddypress.org/participate-and-contribute/codex-standards-guidelines/">Codex Standards & Guidelines</a>.</p>

			</div>

			</div>

		</div>

		<?php } ?>

		<div id="footer">

			<div class="links">
				<p>
					See also: 
					<a href="http://wordpress.org"><?php _e( 'WordPress.org', 'bporg'); ?></a> &bull;
					<a href="http://bbpress.org"><?php _e( 'bbPress.org', 'bporg'); ?></a> &bull;
					<a href="http://buddypress.org"><?php _e( 'BuddyPress.org', 'bporg'); ?></a> &bull;
					<a href="http://ma.tt"><?php _e( 'Matt', 'bporg' ); ?></a> &bull;
					<a href="<?php bloginfo( 'rss2_url' ); ?>" title="<?php esc_attr_e( 'RSS Feed for Articles', 'bporg' ); ?>"><?php _e( 'Blog RSS', 'bporg' ); ?></a>
				</p>
			</div>

			<div class="details">
				<p>
					<a href="http://twitter.com/buddypressdev" class="twitter"><?php _e( 'Follow BuddyPress on Twitter', 'bporg'); ?></a> &bull;
					<a href="http://buddypress.org/about/gpl/"><?php _e('GPL', 'bporg'); ?></a> &bull;
					<a href="http://buddypress.org/contact/"><?php _e('Contact Us', 'bporg'); ?></a> &bull; 
					<a href="http://buddypress.org/terms/"><?php _e('Terms of Service', 'bporg'); ?></a>
				</p>
			</div>
			
		</div>
		<?php wp_footer(); ?>
	</body>
</html>