<?php echo do_blocks( '<!-- wp:wporg/global-header -->'); ?>
<div class="wrapper">
	<div id="login">
		<?php /* The following translation pulls from the WordPress translations, mimicking wp-login.php intentionally for consistency. */ ?>
		<h1><a href="<?php echo wporg_login_wordpress_url(); ?>" tabindex="-1"><?php echo translate( 'Powered by WordPress' ); ?></a></h1>
