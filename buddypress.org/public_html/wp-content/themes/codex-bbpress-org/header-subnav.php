
	<div id="subnav"><div id="subnav-inner">
		<ul id="nav-secondary" class="menu">
			<?php if ( is_user_logged_in() ) : ?>
				<li><a href="<?php bloginfo( 'url' ); ?>/wp-admin/post-new.php?post_type=page">Create New Page</a></li>
				<li><?php edit_post_link( __( 'Edit This Page', 'bborg' ) ); ?></li>
			<?php endif; ?>
		</ul>

		<ul id="nav-user" class="menu">
			<?php if ( ! is_user_logged_in() ) : ?>
				<li><a href="//wordpress.org/support/register.php">Register</a></li>
				<li><a href="//wordpress.org/support/bb-login.php">Lost Password</a></li>
				<li><a href="//bbpress.org/login/">Log In</a></li>
			<?php endif; ?>
		</ul>
	</div></div>
	<hr class="hidden" />
