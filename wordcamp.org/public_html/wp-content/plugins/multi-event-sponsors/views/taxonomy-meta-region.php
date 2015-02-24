<?php

/** @var $wp_list_table WP_Terms_List_Table */
global $wp_list_table;

if ( 'edit' == $wp_list_table->current_action() ) : ?>

	<?php wp_nonce_field( "mes_edit_region_{$region_id}_meta", 'mes_edit_region_meta_nonce' ); ?>

	<tr class="form-field term-camera-wrangler-email-wrap">
		<th scope="row">
			<label for="camera-wrangler-email"><?php _e( 'Camera Wrangler E-mail Address', 'wordcamporg' ); ?></label>
		</th>

		<td>
			<input name="camera-wrangler-email" id="camera-wrangler-email" type="text" value="<?php echo esc_attr( $camera_wrangler_email ); ?>" size="40" />
		</td>
	</tr>

<?php else : ?>

	<?php wp_nonce_field( 'mes_add_region_meta', 'mes_add_region_meta_nonce' ); ?>

	<div class="form-field term-camera-wrangler-email-wrap">
		<label for="tag-camera-wrangler-email"><?php _e( 'Camera Wrangler E-mail Address', 'wordcamporg' ); ?></label>
		<input name="camera-wrangler-email" id="tag-camera-wrangler-email" type="text" value="" size="40" />
	</div>

<?php endif; ?>
