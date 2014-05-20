<?php /** @var $contribution_per_attendee float */ ?>

<label for="mes_contribution_per_attendee"><?php _e( 'Amount:', 'wordcamporg' ); ?></label>
&nbsp;$<input
	id="mes_contribution_per_attendee"
	name="mes_contribution_per_attendee"
	type="number"
	step="0.01"
	value="<?php echo esc_attr( number_format_i18n( $contribution_per_attendee, 2 ) ); ?>"
	class="small-text"
/>