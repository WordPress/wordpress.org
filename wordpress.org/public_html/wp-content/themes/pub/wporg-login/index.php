<?php
/**
 * The main template file.
 *
 * @package wporg-login
 */

get_header();
?>

<div id="pagebody">
	<div id="login-welcome" class="wrapper">
		<h1><?php _e( 'Welcome!', 'wporg-login' ); ?></h1>
		<ul>
			<?php if ( is_user_logged_in() ) : ?>
				<li class="button"><a href="<?php echo wp_logout_url(); ?>"><?php _e( 'Logout', 'wporg-login' ); ?></a></li>
			<?php else : ?>
				<li class="button"><a href="<?php echo wp_login_url(); ?>"><?php _e( 'Login', 'wporg-login' ); ?></a></li>
				<li class="button"><a href="https://wordpress.org/support/register.php"><?php _e( 'Register', 'wporg-login' ); ?></a></li>
			<?php endif; ?>
		</ul>
		<?php /* Could display wp_login_form(); */ ?>
	</div>
</div>

<?php get_footer(); ?>
