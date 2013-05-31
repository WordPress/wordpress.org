<?php
wp_enqueue_style( 'base' );
wp_enqueue_script( 'jquery' );

$pagetitle = gp_title();
require WPORGPATH . 'header.php';
?>
	<script type="text/javascript">document.body.className = document.body.className.replace('no-js','js');</script>
<div id="headline">
	<div class="wrapper">
		<h2><a href="http://make.wordpress.org/polyglots/">Translating WordPress</a></h2>
		<span id="hello">
		<a class="menu-link" href="//make.wordpress.org/polyglots/">Blog</a>
		<a class="menu-link" href="//make.wordpress.org/polyglots/handbook/">Translator Handbook</a>
		<?php
		if (GP::$user->logged_in()):
			$user = GP::$user->current();

			printf( __('Hi, %s.'), '<a href="'.gp_url( '/profile' ).'">'.$user->user_login.'</a>' );
			?>
			<a href="<?php echo gp_url('/logout')?>"><?php _e('Log out'); ?></a>
		<?php else: ?>
			<strong><a href="<?php echo gp_url_login(); ?>"><?php _e('Log in'); ?></a></strong>
		<?php endif; ?>
		<?php do_action( 'after_hello' ); ?>
		</span>
	</div>
</div>
<div class="gp-content">

<div id="gp-js-message"></div>
	
		<?php if (gp_notice('error')): ?>
			<div class="error">
				<?php echo gp_notice( 'error' ); //TODO: run kses on notices ?>
			</div>
		<?php endif; ?>
		<?php if (gp_notice()): ?>
			<div class="notice">
				<?php echo gp_notice(); ?>
			</div>
		<?php endif; ?>
		<?php echo gp_breadcrumb(); ?>
		<?php do_action( 'after_notices' ); ?>

