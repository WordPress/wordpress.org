<?php /** @var $field_name      string */ ?>
<?php /** @var $regions         array  */ ?>
<?php /** @var $selected_region int    */ ?>
<?php /** @var $cb_push_name    string */ ?>
<?php /** @var $site_id         int    */ ?>
<?php /** @var $protected       bool   */ ?>

<select id="<?php echo esc_attr( $field_name ); ?>" name="<?php echo esc_attr( $field_name ); ?>">
	<?php if ( ! $protected ) : ?>
		<option value="">None</option>
		<?php foreach ( $regions as $region ) : ?>
			<option value="<?php echo esc_attr( $region->term_id ); ?>" <?php selected( $selected_region, $region->term_id ); ?>>
				<?php echo esc_html( $region->name ); ?>
			</option>
		<?php endforeach; ?>
	<?php else : ?>
		<option value="<?php echo esc_attr( $selected_region ); ?>" selected>
			<?php echo esc_html( get_term( $selected_region, MES_Region::TAXONOMY_SLUG )->name ); ?>
		</option>
	<?php endif; ?>
</select>

<?php if ( $site_id && ! $protected ) : ?>
	<label>
		<input type="checkbox" name="<?php echo esc_attr( $cb_push_name ); ?>" value="1" />
		Push sponsors to site
	</label>
<?php endif;
