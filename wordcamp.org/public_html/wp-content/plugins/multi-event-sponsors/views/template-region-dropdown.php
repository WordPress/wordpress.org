<?php /** @var $field_name      string */ ?>
<?php /** @var $regions         array  */ ?>
<?php /** @var $selected_region int    */ ?>

<select id="<?php echo esc_attr( $field_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
	<option value="null">None</option>

	<?php foreach ( $regions as $region ) : ?>

		<option value="<?php echo esc_attr( $region->term_id ); ?>" <?php selected( $selected_region, $region->term_id ); ?>>
			<?php echo esc_html( $region->name ); ?>
		</option>

	<?php endforeach; ?>
</select>
