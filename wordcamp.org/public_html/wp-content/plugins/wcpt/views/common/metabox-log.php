<?php defined( 'WPINC' ) or die(); ?>

<table class="widefat striped">
	<thead>
		<tr>
			<th>Timestamp</th>
			<th>Type</th>
			<th>User</th>
			<th>Message</th>
		</tr>
	</thead>

	<tbody>
		<?php if ( $entries ) : ?>

			<?php foreach ( $entries as $entry ) : ?>
				<tr>
					<th><?php echo esc_html( date( 'Y-m-d h:ia', $entry['timestamp'] ) );          ?></th>
					<th><?php echo esc_html( ucwords( str_replace( '_', ' ', $entry['type'] ) ) ); ?></th>
					<th><?php echo esc_html( $entry['user_display_name'] );                        ?></th>
					<th><?php echo wp_kses(  $entry['message'], wp_kses_allowed_html( 'data') );   ?></th>
				</tr>
			<?php endforeach; ?>

		<?php else : ?>

			<tr>
				<td colspan="4">
					There aren't any log entries yet.
				</td>
			</tr>

		<?php endif; ?>
	</tbody>
</table>
