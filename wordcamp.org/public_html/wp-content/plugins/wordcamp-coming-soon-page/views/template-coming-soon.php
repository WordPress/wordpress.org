<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>
	
	<?php wp_head(); ?>
	<?php extract( $GLOBALS['WordCamp_Coming_Soon_Page']->get_template_variables() ); ?>
</head>

<body <?php body_class(); ?>>
	<div id="wccsp-container">
		
		<h1><?php echo esc_attr( get_bloginfo( 'name' ) ); ?></h1>
		
		<?php if ( $image_url ) : ?>
			<img id="wccsp-image" src="<?php echo esc_attr( $image_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
		<?php endif; ?>

		<?php if ( $dates ) : ?>
			<h2><?php echo esc_html( $dates ); ?></h2>
		<?php endif; ?>
		
		<p id="wccsp-introduction">
			<?php echo esc_attr( get_bloginfo( 'name' ) ); ?> is in the early planning stages. In the meantime, you can subscribe to be notified when the site goes live, or contact the organizers to get involved.
		</p>

		<?php if ( in_array( 'subscriptions', $active_modules ) ) : ?>
			<div class="wccsp-box">
				<?php echo do_shortcode( '[jetpack_subscription_form title="Subscribe for Updates"]' ); ?>
			</div>
		<?php endif; ?>

		<?php if ( in_array( 'contact-form', $active_modules ) && $contact_form_shortcode ) : ?>
			<div class="wccsp-box">
				<h2>Contact the Organizers</h2>
				
				<?php echo $contact_form_shortcode; // intentionally not escaping because it's the output of do_shortcode() ?>
			</div>
		<?php endif; ?>
		
	</div><!-- #wccsp_container -->
	
	<?php wp_footer(); ?>
</body>
</html>
