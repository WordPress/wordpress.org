
	<div id="subnav"><div id="subnav-inner">
		<ul id="nav-secondary" class="menu">
			<?php if ( current_user_can( 'edit_pages' ) ) : ?>
				<li><a href="<?php bloginfo( 'url' ); ?>/wp-admin/post-new.php?post_type=page">Create New Page</a></li>
			<?php endif; ?>
			<?php if ( is_user_logged_in() ) : ?>
				<li><?php edit_post_link( esc_html__( 'Edit This Page', 'bborg' ) ); ?></li>
			<?php endif; ?>
		</ul>

		<ul id="nav-user" class="menu">
			<?php if ( ! is_user_logged_in() ) : ?>
				<li><a href="<?php echo esc_url( wp_login_url() ); ?>">Log In</a></li>
				<li><a href="<?php echo esc_url( wp_registration_url() ); ?>">Register</a></li>
			<?php endif; ?>
		</ul>
	</div></div>
	<hr class="hidden" />
