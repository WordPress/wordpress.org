<?php
/**
 * The user settings block
 *
 * A single table that contains all of the user settings, which is included as part of gp-templates/settings.php.
 *
 * @link http://glotpress.org
 *
 * @package GlotPress
 * @since 2.0.0
 */

$gp_per_page = (int) get_user_option( 'gp_per_page' );
if ( 0 === $gp_per_page ) {
	$gp_per_page = 15;
}

$gp_default_sort = get_user_option( 'gp_default_sort' );
if ( ! is_array( $gp_default_sort ) ) {
	$gp_default_sort = array(
		'by'  => 'priority',
		'how' => 'desc',
	);
}

$gp_external_translations = get_user_option( 'gp_external_translations' );

?>

<table class="form-table">
	<tr>
		<th><label for="per_page"><?php esc_html_e( 'Number of items per page:', 'glotpress' ); ?></label></th>
		<td><input type="number" id="per_page" name="per_page" value="<?php echo esc_attr( $gp_per_page ); ?>"/></td>
	</tr>
	<tr>
		<th><label for="default_sort[by]"><?php esc_html_e( 'Default Sort By:', 'glotpress' ); ?></label></th>
		<td>
			<?php
			$sort_bys = wp_list_pluck( gp_get_sort_by_fields(), 'title' );

			echo gp_radio_buttons( 'default_sort[by]', $sort_bys, gp_array_get( $gp_default_sort, 'by', 'priority' ) );
			?>
		</td>
	</tr>
	<tr>
		<th><label for="default_sort[how]"><?php esc_html_e( 'Default Sort Order:', 'glotpress' ); ?></label></th>
		<td>
			<?php
			echo gp_radio_buttons(
				'default_sort[how]',
				array(
					'asc'  => __( 'Ascending', 'glotpress' ),
					'desc' => __( 'Descending', 'glotpress' ),
				),
				gp_array_get( $gp_default_sort, 'how', 'desc' )
			);
			?>
		</td>
	</tr>
	<!-- Including the "notifications_optin" in the "default_sort" array is a hack.
		 If we include it in the future in the GlotPress core, it would be interesting to put
		 this value in it own option item.
		 I do this because the post values are processed in the GP_Route_Settings->settings_post,
		 and I have to modify the GlotPress core to add a new configuration item. -->
	<tr>
		<th><label for="default_sort[notifications_optin]"><?php esc_html_e( 'I want to receive notifications of discussions:', 'glotpress' ); ?></label></th>
		<td><input type="checkbox" id="default_sort[notifications_optin]" name="default_sort[notifications_optin]" <?php gp_checked( 'on' == gp_array_get( $gp_default_sort, 'notifications_optin', 'off' ) ); ?> /></td>
	</tr>
	<tr>
		<th>
			<h4><?php esc_html_e( 'OpenAI (ChatGPT) settings', 'glotpress' ); ?></h4>
		</th>
	</tr>
	<tr>
		<th><label for="default_sort[openai_api_key]">
				<?php esc_html_e( 'OpenAI API Key', 'glotpress' ); ?>
			</label>
					<?php
					if ( gp_array_get( $gp_external_translations, 'openai_translations_used', 0 ) > 0 ) {
						echo '<br>';
						echo '<small>';
						/* translators: %d: number of OpenAI translations used. */
						echo esc_html( sprintf( _n( '%d OpenAI translation used', '%d OpenAI translations used', 'glotpress' ), gp_array_get( $gp_external_translations, 'openai_translations_used', 0 ) ) );
						if ( gp_array_get( $gp_external_translations, 'openai_same_translations_used', 0 ) > 0 ) {
							/* translators: %d: number of OpenAI translations used. */
							echo ' ' . esc_html( sprintf( _n( '(%d used without modifications)', '(%d used without modifications)', 'glotpress' ), gp_array_get( $gp_external_translations, 'openai_same_translations_used', 0 ) ) );
						}
						echo '</small>';
					}
					?>
				<br>
				<a href="https://platform.openai.com/account/usage" target="_blank"><small>
					<?php
					$openai_tokens_used = gp_array_get( $gp_external_translations, 'openai_tokens_used', 0 );
					if ( $openai_tokens_used > 0 ) {
						/* translators: %d: number of OpenAI tokens used. */
						echo esc_html( sprintf( __( 'OpenAI tokens used: %d', 'glotpress' ), number_format( $openai_tokens_used ) ) );
					}
					?>
				</small></a>
				<br>
				<br>
			</th>
		<td><input type="text" class="openai_api_key" id="default_sort[openai_api_key]" name="default_sort[openai_api_key]" value="<?php echo esc_html( gp_array_get( $gp_default_sort, 'openai_api_key', '' ) ); ?>" placeholder="Enter your OpenAI API key" /></td>
	</tr>
	<tr>
		<th><label for="default_sort[openai_custom_prompt]"><?php esc_html_e( 'Custom Prompt', 'glotpress' ); ?></label></th>
		<td><textarea class="openai_custom_prompt" id="default_sort[openai_custom_prompt]" name="default_sort[openai_custom_prompt]" placeholder="Enter your custom prompt for ChatGPT translation suggestions"><?php echo esc_html( gp_array_get( $gp_default_sort, 'openai_custom_prompt', '' ) ); ?></textarea></td>
	</tr>
	<tr>
		<th><label for="default_sort[openai_temperature]"><?php esc_html_e( 'Temperature', 'glotpress' ); ?></label></th>
		<td><input type="number" min="0" max="2" step=".1" class="openai_temperature" id="default_sort[openai_temperature]" name="default_sort[openai_temperature]" value="<?php echo esc_html( gp_array_get( $gp_default_sort, 'openai_temperature', 0 ) ); ?>" placeholder="Enter your OpenAI key" /></td>
	</tr>
	<tr>
		<th>
			<h4><?php esc_html_e( 'Deepl settings', 'glotpress' ); ?></h4>
		</th>
	</tr>
	<tr>
		<th><label for="default_sort[deepl_api_key]">
				<?php esc_html_e( 'Deepl Free API Key', 'glotpress' ); ?>
			</label>
					<?php
					if ( gp_array_get( $gp_external_translations, 'deepl_translations_used', 0 ) > 0 ) {
						echo '<br>';
						echo '<small>';
						/* translators: %d: number of Deepl translations used. */
						echo esc_html( sprintf( _n( '%d Deepl translation used', '%d Deepl translations used', 'glotpress' ), gp_array_get( $gp_external_translations, 'deepl_translations_used', 0 ) ) );
						if ( gp_array_get( $gp_external_translations, 'deepl_same_translations_used', 0 ) > 0 ) {
							/* translators: %d: number of Deepl translations used. */
							echo ' ' . esc_html( sprintf( _n( '(%d used without modifications)', '(%d used without modifications)', 'glotpress' ), gp_array_get( $gp_external_translations, 'deepl_same_translations_used', 0 ) ) );
						}
						echo '</small>';
					}
					?>
				<br>
				<a href="https://www.deepl.com/account/usage" target="_blank"><small>
						<?php
						$deepl_chars_used = gp_array_get( $gp_external_translations, 'deepl_chars_used', 0 );
						if ( $deepl_chars_used > 0 ) {
							/* translators: %d: number of chars translated with Deepl. */
							echo esc_html( sprintf( __( '(Chars translated with Deepl: %d)', 'glotpress' ), number_format( $deepl_chars_used ) ) );
						}
						?>
					</small></a>
			</th>
		<td><input type="text" class="deepl_api_key" id="default_sort[deepl_api_key]" name="default_sort[deepl_api_key]" value="<?php echo esc_html( gp_array_get( $gp_default_sort, 'deepl_api_key' ) ); ?>" placeholder="Enter your Deepl API key" /></td>
	</tr>
</table>
