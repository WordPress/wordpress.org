<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width">
	<title><?php echo esc_html( get_bloginfo( 'name' ) ); ?></title>

	<?php extract( $GLOBALS['WordCamp_Coming_Soon_Page']->get_template_variables() ); ?>
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
	<div id="wccsp-container">
		<div class="wccsp-header <?php echo $background_url ? 'overlay' : ''; ?>">
			<div class="wccsp-container">
				<?php if ( $image_url ) : ?>
					<div class="wccsp-image">
						<img id="wccsp-image" src="<?php echo esc_attr( $image_url ); ?>" alt="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>" />
					</div>
				<?php endif; ?>

				<h1><?php echo esc_html( get_bloginfo( 'name' ) ); ?></h1>

				<?php if ( $dates ) : ?>
					<h2 class="wccsp-dates">
						<?php echo esc_html( $dates ); ?>
					</h2>
				<?php endif; ?>

				<?php if ( in_array( 'subscriptions', $active_modules ) ) : ?>
					<div class="wccsp-subscription">
						<?php echo do_shortcode( sprintf(
							'[jetpack_subscription_form subscribe_text="" title="" subscribe_button="%s"]',
							esc_html__( 'Send me updates!', 'wordcamporg' )
						) ); ?>
					</div>
				<?php endif; ?>
			</div><!-- .wccsp-container -->
		</div><!-- .wccsp-header -->

		<div class="wccsp-container">
			<div class="wccsp-introduction">
				<p id="wccsp-introduction">
					<?php printf(
						// translators: %s is the name of the blog
						__(
							'%s is in the early planning stages.
							 In the meantime, you can subscribe to be notified when the site goes live, or contact the organizers to get involved.',
							'wordcamporg'
						),
						esc_html( get_bloginfo( 'name' ) )
					); ?>
				</p>
			</div><!-- .wccsp-introduction -->

			<?php if ( in_array( 'contact-form', $active_modules ) && $contact_form_shortcode ) : ?>
				<div class="wccsp-contact">
					<h2><?php esc_html_e( 'Contact the Organizers' , 'wordcamporg' ); ?></h2>

					<?php echo $contact_form_shortcode; // intentionally not escaping because it's the output of do_shortcode() ?>
				</div>
			<?php endif; ?>
		</div><!-- .wccsp-container -->

	</div><!-- #wccsp_container -->

	<div class="wccsp-footer">
		<p>
			<a href="https://central.wordcamp.org/schedule/">
				<?php esc_html_e( 'See all upcoming events at WordCamp Central', 'wordcamporg' ); ?>
			</a>
		</p>
	</div>

	<?php wp_footer(); ?>
</body>
</html>
