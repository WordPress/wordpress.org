<?php
use PTR\Display;

echo Display::get_display_css(); ?>

<table>
	<thead>
		<tr>
			<th style="width:100px">Revision</th>
			<th>Host</th>
			<th>PHP Version</th>
			<th>Database Version</th>
			<th>Extensions</th>
		</tr>
	</thead>
	<tbody>
		<?php
		$total_cols = 5;
		foreach ( $revisions as $revision ) :
			$rev_id = (int) ltrim( $revision->post_name, 'r' );
		?>
			<tr>
				<th><a href="<?php echo esc_url( sprintf( 'https://core.trac.wordpress.org/changeset/%d', $rev_id ) ); ?>"><?php echo (int) $rev_id; ?></a></th>
				<th colspan="<?php echo (int) $total_cols - 1; ?>"><?php echo wp_kses_post( apply_filters( 'the_title', $revision->post_title ) ); ?></th>
			</tr>
			<?php
			$query_args   = array(
				'posts_per_page' => 10,
				'post_type'      => 'result',
				'post_parent'    => $revision->ID,
				'orderby'        => 'post_title',
				'order'          => 'ASC',
			);
			$report_query = new WP_Query( $query_args );
			if ( ! empty( $report_query->posts ) ) :
				foreach ( $report_query->posts as $report ) :
					$status       = 'Errored';
					$status_title = 'No results found for test.';
					$results      = get_post_meta( $report->ID, 'results', true );
					if ( isset( $results['failures'] ) ) {
						$status       = 0 === (int) $results['failures'] && 0 === (int) $results['errors'] ? 'Passed' : 'Failed';
						$status_title = (int) $results['tests'] . ' tests, ' . (int) $results['failures'] . ' failed, ' . (int) $results['errors'] . ' errors';
					}
					$host = 'Unknown';
					$user = get_user_by( 'id', $report->post_author );
					if ( $user ) {
						$host = $user->display_name;
					}
					?>
				<tr>
					<td><a href="<?php echo esc_url( get_permalink( $report->ID ) ); ?>" title="<?php echo esc_attr( $status_title ); ?>" class="<?php echo esc_attr( 'ptr-status-badge ptr-status-badge-' . strtolower( $status ) ); ?>"><?php echo esc_html( $status ); ?></a></td>
					<td><?php echo esc_html( $host ); ?></td>
					<td><?php echo esc_html( Display::get_display_php_version( $report->ID ) ); ?></td>
					<td><?php echo esc_html( Display::get_display_mysql_version( $report->ID ) ); ?></td>
					<td><?php echo esc_html( Display::get_display_extensions( $report->ID ) ); ?></td>
				</tr>
			<?php
				endforeach;
			else :
			?>
				<tr>
					<td></td>
					<td colspan="<?php echo (int) $total_cols - 1; ?>">
						No reports for changeset.
					</td>
				</tr>
			<?php endif; ?>
		<?php endforeach; ?>
	</tbody>
</table>
