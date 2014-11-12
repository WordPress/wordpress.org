</div>
<div class="sidebar">

	<?php if ( is_page() || is_front_page() ) :

		$toc = Codex_Loader::create_page_toc();

		if ( !empty( $toc ) ) : ?>

		<div class="widget table-of-contents-widget listified">
			<?php echo $toc; ?>
		</div>

		<?php endif;

		global $post;

		$show_related = true;

		if ( !empty( $post->post_parent ) ) {
			$children = wp_list_pages('title_li=&echo=0&child_of=' . $post->post_parent . '&exclude=' . $exclude_pages);
			$rel      = '<ul>' . $children . '</ul>';
			echo '<div class="related-content-widget widget listified"><h3 class="widgettitle">Similar</h3>' . $rel . '</div>';
			$show_related = false;
		} else {
			$children = wp_list_pages('title_li=&echo=0&child_of=' . $post->ID . '&exclude=' . $exclude_pages);
			if ( !empty( $children ) ) {
				$rel = '<ul>' . $children . '</ul>';
				echo '<div class="related-content-widget widget listified"><h3 class="widgettitle">Subpages</h3>' . $rel . '</div>';
				$show_related = false;
			}
		}

		if ( ! is_page( 'home' ) && !empty( $show_related ) ) {
			$rel      = '';
			$cat      = get_the_category();
			$relateds = get_posts( 'nopaging=1&post_type=page&post_parent=0&orderby=title&order=ASC&cat=' . $cat[0]->term_id . '&exclude=' . $post->ID . ',' . $exclude_pages );
			if ( !empty( $relateds ) ) {
				foreach ( $relateds as $related ) {
					$title = apply_filters( 'the_title', $related->post_title );
					$rel .= '<li><a href="' . get_permalink( $related->ID ) . '" title="' . $title . '">' . $title . '</a></li>';
				}
				$rel = '<ul>' . $rel . '</ul>';
				echo '<div class="related-content-widget widget listified"><h3 class="widgettitle">Related</h3>' . $rel . '</div>';
			}
		} ?>

	<?php endif; ?>

	<div class="search-widget">
		<form method="get" id="searchform" action="<?php bloginfo( 'url' ); ?>/">
			<input type="text" value="<?php the_search_query(); ?>" name="s" id="s" placeholder="Search" />
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
</div>
