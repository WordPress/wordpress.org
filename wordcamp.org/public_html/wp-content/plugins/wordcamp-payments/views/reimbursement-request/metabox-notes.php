<?php

namespace WordCamp\Budgets\Reimbursement_Requests;
defined( 'WPINC' ) or die();

?>

<?php if ( empty ( $existing_notes ) ) : ?>

	<?php _e( 'There are no notes yet.', 'wordcamporg' ); ?>

<?php else : ?>

	<?php foreach ( $existing_notes as $note ) : ?>
		<div class="wcbrr-note">
			<span class="wcbrr-note-meta">
				<?php echo esc_html( date( 'Y-m-d', $note['timestamp'] ) ); ?>
				<?php echo esc_html( \WordCamp_Budgets::get_requester_name( $note['author_id'] ) ); ?>:
			</span>

			<?php echo esc_html( $note['message'] ); ?>
		</div>
	<?php endforeach; ?>

<?php endif; ?>

<div>
	<h3>
		<label for="wcbrr_new_note">
			<?php _e( 'Add a Note', 'wordcamporg' ); ?>
		</label>
	</h3>

	<textarea id="wcbrr_new_note" name="wcbrr_new_note" class="large-text"></textarea>

	<?php submit_button(
		esc_html__( 'Add Note', 'wordcamporg' ),
		'secondary',
		'wcbrr_add_note'
	); ?>
</div>
