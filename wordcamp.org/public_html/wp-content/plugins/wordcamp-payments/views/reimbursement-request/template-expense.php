<?php

namespace WordCamp\Budgets\Reimbursement_Requests;
defined( 'WPINC' ) or die();

?>

<script type="text/html" id="tmpl-wcbrr-expense">
	<h3>
		<?php _e( 'Expense', 'wordcamporg' ); ?> #{{data.id}}
	</h3>

	<ul class="wcb-form">
		<li>
			<label for="_wcbrr_category_{{data.id}}">
				<?php _e( 'Category:', 'wordcamporg' ) ?>
			</label>

			<select id="_wcbrr_category_{{data.id}}" name="_wcbrr_category_{{data.id}}">
				<option value="null-select-one">
					<?php _e( '-- Select a Category --', 'wordcamporg' ); ?>
				</option>
				<option value="null-separator1"></option>

				<# _.each( wcbPaymentCategories, function( categoryName, categoryKey ) { #>
					<# selected = data._wcbrr_category === categoryKey ? 'selected' : ''; #>

					<option value="{{categoryKey}}"	{{selected}}>
						{{categoryName}}
					</option>
				<# } );	#>
			</select>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<# var otherCategoryClasses = 'other' === data._wcbrr_category ? '' : 'hidden'; #>
		<li id="_wcbrr_category_other_container" class="{{otherCategoryClasses}}">
			<label for="_wcbrr_category_other_{{data.id}}">
				<?php _e( 'Other Category:', 'wordcamporg' ); ?>
			</label>

			<input
				type="text"
				class="regular-text"
				id="_wcbrr_category_other_{{data.id}}"
				name="_wcbrr_category_other_{{data.id}}"
				value="{{data._wcbrr_category_other}}"
			/>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<li>
			<label for="_wcbrr_vendor_name_{{data.id}}">
				<?php _e( 'Vendor Name:', 'wordcamporg' ); ?>
			</label>

			<input
				type="text"
				class="regular-text"
				id="_wcbrr_vendor_name_{{data.id}}"
				name="_wcbrr_vendor_name_{{data.id}}"
				value="{{data._wcbrr_vendor_name}}"
			    required
			/>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<li>
			<label for="_wcbrr_description_{{data.id}}">
				<?php _e( 'Description:', 'wordcamporg' ); ?>
			</label>

			<textarea
				rows="2"
				cols="38"
				id="_wcbrr_description_{{data.id}}"
				name="_wcbrr_description_{{data.id}}"
				maxlength="75"
			    required
			>{{data._wcbrr_description}}</textarea>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<li>
			<label for="_wcbrr_date_{{data.id}}">
				<?php _e( 'Date:', 'wordcamporg' ); ?>
			</label>

			<input
				type="date"
				class="regular-text"
				id="_wcbrr_date_{{data.id}}"
				name="_wcbrr_date_{{data.id}}"
				value="{{data._wcbrr_date}}"
			    required
			/>

			<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
		</li>

		<li>
			<label for="_wcbrr_amount_{{data.id}}">
				<?php _e( 'Amount:', 'wordcamporg' ); ?>
			</label>

			<div class="wcb-form-input-wrapper">
				<input
					type="text"
					class="regular-text"
					id="_wcbrr_amount_{{data.id}}"
					name="_wcbrr_amount_{{data.id}}"
					value="{{data._wcbrr_amount}}"
				    required
				/>

				<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>

				<p class="description">
					<?php _e( 'No commas, thousands separators or currency symbols. Ex. 1234.56', 'wordcamporg' ); ?>
				</p>
			</div>
		</li>

		<li>
			<label>
				<?php _e( 'Vendor Location:', 'wordcamporg' ); ?>
			</label>

			<div class="wcb-form-input-wrapper">
				<# var checked = 'local' === data._wcbrr_vendor_location ? 'checked' : ''; #>
				<label>
					<input
						type="radio"
						id="_wcbrr_vendor_location_local_{{data.id}}"
						name="_wcbrr_vendor_location_{{data.id}}"
						value="local"
						required
					    {{checked}}
					/>
			       <?php _e( 'Local', 'wordcamporg' ); ?>
				</label>

				<br />

				<# var checked = 'online' === data._wcbrr_vendor_location ? 'checked' : ''; #>
				<label>
					<input
						type="radio"
						id="_wcbrr_vendor_location_online_{{data.id}}"
						name="_wcbrr_vendor_location_{{data.id}}"
						value="online"
						required
					    {{checked}}
					/>
					<?php _e( 'Not Local / Online', 'wordcamporg' ); ?>
				</label>

				<?php \WordCamp_Budgets::render_form_field_required_indicator(); ?>
			</div>
		</li>
	</ul>

	<button class="wcbrr-delete-expense button-secondary" data-expense-id="{{data.id}}">
		<?php _e( 'Delete Expense', 'wordcamporg' ); ?> #{{data.id}}
	</button>

	<hr />

</script>
