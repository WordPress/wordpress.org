<?php if ( !is_front_page() ) : ?>
	<div id="subnav"><div id="subnav-inner">
		<ul id="nav-secondary" class="menu">
			<?php if ( is_user_logged_in() && function_exists( 'bbp_forums_url' ) ) : ?>
				<li><a href="<?php bbp_forums_url( 'new-topic' ); ?>">Create New Topic</a></li>
			<?php endif; ?>
		</ul>

		<ul id="nav-user" class="menu">
			<?php if ( ! is_user_logged_in() ) : ?>
				<li><a href="//wordpress.org/support/register.php">Register</a></li>
				<li><a href="//wordpress.org/support/bb-login.php">Lost Password</a></li>
				<li><a href="<?php echo home_url( 'login' ); ?>">Log In</a></li>
			<?php elseif ( function_exists( 'bbp_favorites_permalink' ) ) : ?>
				<li><a href="<?php bbp_favorites_permalink( bbp_get_current_user_id() ); ?>">Favorites</a></li>
				<li><a href="<?php bbp_subscriptions_permalink( bbp_get_current_user_id() ); ?>">Subscriptions</a></li>
			<?php endif; ?>
		</ul>
	</div></div>
	<hr class="hidden" />
<?php endif; ?>