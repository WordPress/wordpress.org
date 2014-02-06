<?php if ( 'wccsp_enabled_true' == $field['label_for'] ) : ?>
	<input id="wccsp_enabled_true" name="wccsp_settings[enabled]" type="radio" value="on" <?php checked( $this->settings['enabled'], 'on' ); ?> />
	<span class="example"> On</span><br />
	
	<input id="wccsp_enabled_false" name="wccsp_settings[enabled]" type="radio" value="off" <?php checked( $this->settings['enabled'], 'off' ); ?> />
	<span class="example"> Off</span>
<?php endif; ?>


<?php if ( 'wccsp_body_background_color' == $field['label_for'] ) : ?>
	<input id="wccsp_body_background_color" name="wccsp_settings[body_background_color]" type="text" class="short-text" value="<?php echo esc_attr( $this->settings['body_background_color'] ); ?>" />
<?php endif; ?>


<?php if ( 'wccsp_container_background_color' == $field['label_for'] ) : ?>
	<input id="wccsp_container_background_color" name="wccsp_settings[container_background_color]" type="text" class="short-text" value="<?php echo esc_attr( $this->settings['container_background_color'] ); ?>" />
<?php endif; ?>


<?php if ( 'wccsp_text_color' == $field['label_for'] ) : ?>
	<input id="wccsp_text_color" name="wccsp_settings[text_color]" type="text" class="short-text" value="<?php echo esc_attr( $this->settings['text_color'] ); ?>" />
<?php endif; ?>


<?php if ( 'wccsp_image_id' == $field['label_for'] ) : ?>
	<p>
		<input type="hidden" id="wccsp_image_id" name="wccsp_settings[image_id]" value="<?php echo esc_attr( $this->settings['image_id'] ); ?>" />
		<a href="javascript:;" id="wccsp-select-image" class="button insert-media add_media" title="Select Image">Select Image</a>
	</p>
	
	<?php if( $image ) : ?>
		<p>
			Current image preview:<br />
			<img id="wccsp-logo-preview" src="<?php echo esc_attr( $image[0] ); ?>" alt="Image Preview" />
		</p>
	<?php endif; ?>
<?php endif; ?>
