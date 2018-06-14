<div id="nav">
	<a href="#" id="bb-menu-icon"></a>
	<?php if ( ! has_nav_menu( 'header-nav-menu' ) ) : ?>
		<ul id="bb-nav" class="menu">
			<li <?php if ( is_page( 'about'    ) ) : ?>class="current"<?php endif; ?>><a href="<?php echo home_url( 'about'   ); ?>"><?php esc_html_e( 'About', 'bborg' ); ?></a></li>
			<li <?php if ( is_page( 'plugins'  ) ) : ?>class="current"<?php endif; ?>><a href="<?php echo home_url( 'plugins' ); ?>"><?php esc_html_e( 'Plugins', 'bborg' ); ?></a></li>
			<li <?php if ( is_page( 'themes'   ) ) : ?>class="current"<?php endif; ?>><a href="<?php echo home_url( 'themes'  ); ?>"><?php esc_html_e( 'Themes', 'bborg' ); ?></a></li>
			<li><a href="<?php echo esc_url( '//codex.' . parse_url( home_url(), PHP_URL_HOST ) . '/' ); ?>"><?php esc_html_e( 'Documentation', 'bborg' ); ?></a></li>
			<li <?php if ( is_post_type_archive( 'post' ) || is_singular( 'post' ) || is_date() || is_tag() || is_category() || is_home() ) : ?>class="current"<?php endif; ?>><a href="<?php echo home_url( 'blog' ); ?>"><?php esc_html_e( 'Blog', 'bborg' ); ?></a></li>
			<?php if ( function_exists( 'is_bbpress' ) ) : ?><li <?php if ( is_bbpress() ) : ?>class="current"<?php endif; ?>><a href="<?php bbp_forums_url(); ?>"><?php esc_html_e( 'Support', 'bborg' ); ?></a></li><?php endif; ?>
			<li class="download<?php if ( is_page( 'download' ) ) : ?> current<?php endif; ?>"><a href="<?php echo home_url( 'download' ); ?>"><?php esc_html_e( 'Download', 'bborg' ); ?></a></li>
		</ul>
	<?php else: ?>
		<?php wp_nav_menu( array(
				'container'      => '',
				'menu_class'     => 'menu',
				'menu_id'        => 'bb-nav',
				'theme_location' => 'header-nav-menu',
			) ); ?>
	<?php endif; ?>
</div>
