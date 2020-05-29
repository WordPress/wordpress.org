
	<div id="subnav"><div id="subnav-inner">
		<ul id="nav-secondary" class="menu">
			<?php if ( is_user_logged_in() ) : ?>
				<li><a href="<?php bloginfo( 'url' ); ?>/wp-admin/post-new.php?post_type=page">Create New Page</a></li>
				<li><?php edit_post_link( __( 'Edit This Page', 'bborg' ) ); ?></li>
			<?php endif; ?>
		</ul>

		<ul id="nav-user" class="menu">
			<?php if ( ! is_user_logged_in() ) : ?>
				<li><a href="<?php echo wp_login_url(); ?>">Log In</a></li>
				<li><a href="<?php echo wp_registration_url(); ?>">Register</a></li>
			<?php endif; ?>
		</ul>
	</div></div>
	<hr class="hidden" />
