<?php /** @var $field_name      string */ ?>
<?php /** @var $regions         array  */ ?>
<?php /** @var $selected_region int    */ ?>
<?php /** @var $cb_push_name    string */ ?>
<?php /** @var $site_id         int    */ ?>

<select id="<?php echo esc_attr( $field_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
	<option value="">None</option>

	<?php foreach ( $regions as $region ) : ?>

		<option value="<?php echo esc_attr( $region->term_id ); ?>" <?php selected( $selected_region, $region->term_id ); ?>>
			<?php echo esc_html( $region->name ); ?>
		</option>

	<?php endforeach; ?>
</select>

<?php if ( $site_id ) : ?>
	<label>
		<input type="checkbox" name="<?php echo esc_attr( $cb_push_name ); ?>" value="1" />
		Push sponsors to site
	</label>
<?php endif; ?>
