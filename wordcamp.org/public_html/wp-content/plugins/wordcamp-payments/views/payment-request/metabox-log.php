<?php if ( ! empty( $log ) ) : ?>
	<table class="wcp-request-log striped">
		<?php foreach ( $log as $entry ) : ?>
		<tr>
			<td class="timestamp">
				<?php echo gmdate( 'Y-m-d H:i:s', absint( $entry['timestamp'] ) ); ?>
			</td>
			<td>
				<?php echo esc_html( $entry['message'] ); ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
<?php else : ?>
	<p><?php _e( 'Nothing in the log yet.', 'wordcamporg' ); ?></p>
<?php endif; ?>