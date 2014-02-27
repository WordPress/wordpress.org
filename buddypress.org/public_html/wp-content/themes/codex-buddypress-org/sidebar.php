</div>
<div class="sidebar">

	<?php if ( is_page() || is_front_page() ): ?>

		<?php if ( $toc = Codex_Loader::create_page_toc() ): ?>
		<div class="widget table-of-contents-widget listified">
			<?php echo $toc; ?>
		</div>
		<?php endif; ?>

		<?php
		$show_related = false;
		if ( !empty( $post->post_parent ) ) {
			/*

			Commented out "Similar" Pages

			$children = wp_list_pages('title_li=&echo=0&child_of=' . $post->post_parent . '&exclude=' . $exclude_pages);
			$rel = '<ul>' . $children . '</ul>';
			echo '<div class="related-content-widget widget listified"><h3 class="widgettitle">Similar</h3>' . $rel . '</div>';
			$show_related = false;
			*/

		} else {
			$children = wp_list_pages('title_li=&echo=0&child_of=' . $post->ID);
			if ( $children ) {
				$rel = '<ul>' . $children . '</ul>';
				echo '<div class="related-content-widget widget listified"><h3 class="widgettitle">Subpages</h3>' . $rel . '</div>';
				$show_related = false;
			}
		} ?>

		<?php
			if ( !is_page( 'home' ) && $show_related ) {
				$rel = '';
				$cat = get_the_category();
				$relateds = get_posts('nopaging=1&post_type=page&post_parent=0&orderby=title&order=ASC&cat=' . $cat[0]->term_id . '&exclude=' . $post->ID . ',');
				if ( $relateds ) {
					foreach ($relateds as $related) {
						$title = apply_filters('the_title', $related->post_title);
						$rel .= '<li><a href="' . get_permalink($related->ID) . '" title="' . $title . '">' . $title . '</a></li>';
					}
					$rel = '<ul>' . $rel . '</ul>';
					echo '<div class="related-content-widget widget listified"><h3 class="widgettitle">Related</h3>' . $rel . '</div>';
				}
			}
		?>

	<?php endif; ?>


	<div class="search-widget">
		<form method="get" id="searchform" action="<?php bloginfo( 'url' ); ?>/">
			<input type="text" value="<?php the_search_query(); ?>" name="s" id="s" placeholder="Search" />
			<!-- <input type="submit" id="searchsubmit" value="Search" /> -->
		</form>
	</div>

	<div class="user-widget">
		<ul>
			<?php if ( is_user_logged_in() ) : ?>
				<li><a href="<?php bloginfo( 'url' ); ?>/wp-admin/post-new.php?post_type=page">Create New Page</a></li>
				<li><?php edit_post_link( __( 'Edit This Page', 'bborg' ) ); ?></li>
			<?php endif; ?>
			<?php if ( ! is_user_logged_in() ) : ?>
				<li><a href="//wordpress.org/support/register.php">Register</a></li>
				<li><a href="//wordpress.org/support/bb-login.php">Lost Password</a></li>
				<li><a href="<?php bloginfo( 'url' ); ?>/login/">Log In</a></li>
			<?php endif; ?>
		</ul>
	</div>


	<?php if ( is_tax() ) {
		global $codex_contributors;

		if ( !empty( $codex_contributors ) ) {
			echo '<div class="section-contributors widget">';
			echo '<h3 class="widgettitle">Top Authors</h3>';
			$codex_contributors = array_slice( $codex_contributors, 0, 5, true );
			foreach( (array)$codex_contributors as $contributor_id => $count ) {
				$userdata = get_userdata( $contributor_id );
				echo '<div class="section-contributor">';
				echo '<div class="contributor-mug float-left">';
				echo get_avatar( $contributor_id, 48 );
				echo '</div>';
				echo '<div class="inner">';
				echo '<h5>' . esc_html( $userdata->display_name ) . '</h5>';
				echo '<p>' . esc_html( $count ) . ' document';
				if ( $count > 1 ) 
					echo 's';
				echo '</p>';
				echo '</div>';
				echo '<div class="clear-left"></div>';
				echo '</div>';
			}
			echo '</div>';
		}
	} ?>

</div>
