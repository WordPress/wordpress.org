<?php

namespace WordCamp\RemoteCSS;
defined( 'WPINC' ) or die();

/**
 * @var string $notice
 * @var string $notice_class
 * @var string $remote_css_url
 * @var string $output_mode
 */

?>

<div class="wrap">
	<h1><?php _e( 'Remote CSS', 'wordcamporg' ); ?></h1>

	<?php
		if ( is_callable( '\WordCamp\Jetpack_Tweaks\notify_import_rules_stripped' ) ) {
			// This has to be called manually because process_options_page() is called after `admin_notices` fires
			\WordCamp\Jetpack_Tweaks\notify_import_rules_stripped();
		}
	?>

	<?php if ( $notice ) : ?>
		<div id="message" class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<p><?php echo wp_kses( $notice, wp_kses_allowed_html( 'data' ) ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php _e(
			'Remote CSS allows you to develop your CSS in any environment that you choose, and with whatever tools that you prefer.
			<button type="button" id="wcrcss-open-help-tab" class="button-link">Open the Help tab</button> for detailed instructions.',
			'wordcamporg'
		); ?>
	</p>

	<form action="" method="POST">
		<?php wp_nonce_field( 'wcrcss-options-submit', 'wcrcss-options-nonce' ); ?>

			<p>
				<label>
					<?php _e( 'Remote CSS URL:', 'wordcamporg' ); ?><br />
					<input type="text" name="wcrcss-remote-css-url" class="large-text" value="<?php echo esc_url( $remote_css_url ); ?>" />
				</label>
			</p>

			<div>
				<?php _e( 'Output Mode:', 'wordcamporg' ); ?>

				<ul>
					<li>
						<label>
							<input type="radio" name="wcrcss-output-mode" value="add-on" <?php checked( $output_mode, 'add-on' ); ?> />
							<?php _e( "Add-on: The theme's stylesheet will remain, and your custom CSS will be added after it.", 'wordcamporg' ); ?>
						</label>
					</li>

					<li>
						<label>
							<input type="radio" name="wcrcss-output-mode" value="replace" <?php checked( $output_mode, 'replace' ); ?> />
							<?php _e( "Replace: The theme's stylesheet will be removed, so that only your custom CSS is present.", 'wordcamporg' ); ?>
						</label>
					</li>
				</ul>
			</div>

			<?php submit_button( __( 'Update', 'wordcamporg' ) ); ?>
	</form>
</div>

<script>
	jQuery( '#wcrcss-open-help-tab' ).click( function() {
		jQuery( '#contextual-help-link' ).click();
	} );
</script>
