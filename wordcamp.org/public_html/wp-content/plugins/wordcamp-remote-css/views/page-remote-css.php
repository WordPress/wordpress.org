<?php

namespace WordCamp\RemoteCSS;
defined( 'WPINC' ) or die();

?>

<div class="wrap">
	<h1><?php _e( 'Remote CSS', 'wordcamporg' ); ?></h1>

	<?php if ( ! $jetpack_custom_css_active ) : ?>
		<div id="message" class="notice notice-error inline">
			<?php
				/*
				 * Jetpack_Custom_CSS is used to sanitize the unsafe CSS, and for removing the theme's stylesheet
				 * in `replace` mode. Methods from Jetpack_Custom_CSS are called throughout this plugin, so we
				 * need it to be active.
				 */
			?>

			<p>
				<?php printf(
					__( 'This tool uses some functionality from Jetpack\'s Custom CSS module,
					but it doesn\'t look like it\'s available.
					Please <a href="%s">activate it</a>.',
					'wordcamporg' ),
					esc_url( $jetpack_modules_url )
				); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php
		if ( is_callable( '\WordCamp\Jetpack_Tweaks\notify_import_rules_stripped' ) ) {
			// This has to be called manually because process_options_page() is called after `admin_notices` fires
			\WordCamp\Jetpack_Tweaks\notify_import_rules_stripped();
		}
	?>

	<?php if ( $notice ) : ?>
		<div id="message" class="notice <?php echo esc_attr( $notice_class ); ?> is-dismissible">
			<?php
			    /*
			     * Typically KSES is discouraged when displaying text because it's expensive, but in this case
			     * it's appropriate because the underlying layers need to pass HTML-formatted error messages, and
			     * this only only runs when the options are updated.
			     */
			?>

			<p><?php echo wp_kses( $notice, wp_kses_allowed_html( 'data' ) ); ?></p>
		</div>
	<?php endif; ?>

	<p>
		<?php _e( 'This tool allows you to develop your CSS in any environment that you choose, and with the tools that you prefer,
		rather than with Jetpack\'s CSS Editor.
		<button type="button" id="wcrcss-open-help-tab" class="button-link">Open the Help tab</button> for detailed instructions.',
		'wordcamporg' ); ?>
	</p>

	<form action="" method="POST">
		<?php wp_nonce_field( 'wcrcss-options-submit', 'wcrcss-options-nonce' ); ?>

		<fieldset <?php disabled( $jetpack_custom_css_active, false ); ?>>
			<p>
				<label>
					<?php _e( 'Remote CSS File:', 'wordcamporg' ); ?><br />
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
		</fieldset>
	</form>
</div>

<script>
	jQuery( '#wcrcss-open-help-tab' ).click( function() {
		jQuery( '#contextual-help-link' ).click();
	} );
</script>
