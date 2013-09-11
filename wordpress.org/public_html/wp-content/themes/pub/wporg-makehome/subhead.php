
<nav class="subhead">
	<div class="wrapper">
		<?php wp_nav_menu( array( 'theme_location' => 'primary', 'container_class' => 'nav-menu' ) ); ?>

		<?php if( false && class_exists( 'Jetpack' ) && Jetpack::is_module_active( 'subscriptions' ) ) : ?>
		<?php /* @todo: switch this form over to the Jetpack Subscriptions shortcode */ ?>
		<?php /* jetpack_do_subscription_form( $args = array() ); */ ?>
		<form action="#" method="post">
			<fieldset>
				<label for="signup-email">Get news updates in your email:</label>
				<input type="email" name="email" class="text" id="signup-email" />
				<button type="submit" class="button button-primary">Sign Up</button>
			</fieldset>
		</form>
		<?php endif; ?>
	</div>
</nav>
