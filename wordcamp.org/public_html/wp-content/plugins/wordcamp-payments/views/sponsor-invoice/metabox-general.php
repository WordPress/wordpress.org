<?php

namespace WordCamp\Budgets\Sponsor_Invoices;
defined( 'WPINC' ) or die();

?>

<ul class="wcb-form">
	<li>
		<label for="_wcbsi_sponsor_id">
			<?php _e( 'Sponsor:', 'wordcamporg' ) ?>
		</label>

		<div class="wcb-form-input-wrapper">
			<?php // todo add selected attribute to select and change first option value to empty string. do for other selects too ?>
			<select id="_wcbsi_sponsor_id" name="_wcbsi_sponsor_id">
				<option value="null-select-one">
					<?php _e( '-- Select a Sponsor --', 'wordcamporg' ); ?>
				</option>

				<option value="null-separator1"></option>

				<?php foreach ( $available_sponsors as $sponsor_id => $sponsor ) : ?>
					<option
						value="<?php echo esc_attr( $sponsor_id ); ?>"
						<?php selected( $sponsor_id, $selected_sponsor_id ); ?>

						<?php foreach ( $sponsor['data_attributes'] as $attribute_key => $attribute_value ) : ?>
							data-<?php echo esc_attr( $attribute_key ); ?>="<?php echo esc_attr( $attribute_value ); ?>"
						<?php endforeach; ?>
					>
						<?php echo esc_html( $sponsor['name'] ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>

			[<a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=wcb_sponsor') ); ?>"><?php
				_e( 'Add New Sponsor', 'wordcamporg' );
			?></a>]

			<div id="wcbsi-sponsor-information" class="loading-content">
				<span class="spinner is-active"></span>
			</div>

			<?php require( __DIR__ . '/template-sponsor-information.php'        ); ?>
			<?php require( __DIR__ . '/template-required-fields-incomplete.php' ); ?>
		</div>
	</li>

	<li>
		<label for="_wcbsi_qbo_class_id">
			<?php _e( 'Community:', 'wordcamporg' ) ?>
		</label>

		<div class="wcb-form-input-wrapper">
			<select id="_wcbsi_qbo_class_id" name="_wcbsi_qbo_class_id">
				<option value="null-select-one">
					<?php _e( '-- Select a Community --', 'wordcamporg' ); ?>
				</option>

				<option value="null-separator1"></option>

				<?php foreach ( $available_classes as $class_id => $class ) : ?>
					<option
						value="<?php echo esc_attr( $class_id ); ?>"
						<?php selected( $class_id, $selected_class_id ); ?>
					>
						<?php echo esc_html( $class ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</div>
	</li>

	<li>
		<label for="_wcbsi_description">
			<?php _e( 'Description:', 'wordcamporg' ); ?>
		</label>

		<input
			type="text"
			class="regular-text"
			id="_wcbsi_description"
			name="_wcbsi_description"
			value="<?php echo esc_attr( $description ); ?>"
			required
		/>

		<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
	</li>

	<li>
		<label for="_wcbsi_currency">
			<?php _e( 'Currency:', 'wordcamporg' ) ?>
		</label>

		<select id="_wcbsi_currency" name="_wcbsi_currency">
			<option value="null-select-one">
				<?php _e( '-- Select a Currency --', 'wordcamporg' ); ?>
			</option>
			<option value="null-separator1"></option>

			<?php foreach ( $available_currencies as $currency_key => $currency_name ) : ?>
				<option value="<?php echo esc_attr( $currency_key ); ?>" <?php selected( $currency_key, $selected_currency ); ?> >
					<?php echo esc_html( $currency_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
	</li>

	<li>
		<label for="_wcbsi_amount">
			<?php _e( 'Amount:', 'wordcamporg' ); ?>
		</label>

		<div class="wcb-form-input-wrapper">
			<input
				type="text"
				class="regular-text"
				id="_wcbsi_amount"
				name="_wcbsi_amount"
				value="<?php echo esc_attr( $amount ); ?>"
				required
			/>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>

			<p class="description">
				<?php _e( 'No commas, thousands separators or currency symbols. Ex. 1234.56', 'wordcamporg' ); ?>
			</p>
		</div>
	</li>

</ul>

<span class="wcb-form-required">
	<?php _e( '* required', 'wordcamporg' ); ?>
</span>
