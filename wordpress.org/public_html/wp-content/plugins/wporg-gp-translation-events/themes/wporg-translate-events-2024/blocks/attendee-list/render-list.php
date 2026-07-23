<?php
/**
 * List view for the attendee-list block.
 *
 * @package wporg-translate-events-2024
 */

if ( empty( $attendees_not_contributing ) || ! current_user_can( 'edit_translation_event', $event_id ) ) {
	return;
}

?>
<!-- wp:heading {"style":{"typography":{"fontStyle":"normal","fontWeight":"700"}},"fontSize":"medium","fontFamily":"inter"} -->
<h4 class="wp-block-heading has-inter-font-family has-medium-font-size" style="font-style:normal;font-weight:700">
	<?php
		// translators: %d is the number of contributors.
		echo esc_html( sprintf( __( 'Attendees (%d)', 'wporg-translate-events-2024' ), number_format_i18n( count( $attendees_not_contributing ) ) ) );
	?>
</h4>
<!-- /wp:heading -->

<!-- wp:group {"layout":{"type":"grid","columnCount":3,"minimumColumnWidth":null}} -->
<div class="wp-block-group">
	<?php
	foreach ( $attendees_not_contributing as $attendee ) :
		?>
		<!-- wp:group -->
		<div class="wp-block-group">
			<!-- wp:wporg-translate-events-2024/attendee-avatar-name
			<?php
			echo wp_json_encode(
				array(
					'user_id'            => $attendee->user_id(),
					'is_new_contributor' => $attendee->is_new_contributor(),
				)
			);
			?>
			/-->
			<?php if ( $attendee->is_remote() ) : ?>
				<!-- wp:wporg-translate-events-2024/remote-attendance-icon <?php echo wp_json_encode( array( 'css_class' => 'video-icon-on-gravatar' ) ); ?> /-->
			<?php endif; ?>
		</div>
		<!-- /wp:group -->
		<?php
	endforeach;
	?>
</div>
<!-- /wp:group -->
