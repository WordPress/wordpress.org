<?php
use PTR\Display;

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Display::get_display_css() returns the report stylesheet.
echo Display::get_display_css(); ?>

<table class="ptr-test-reporter-table alignwide">
	<thead>
		<tr>
			<th style="width:100px">Revision</th>
			<th style="width:100px">Passed</th>
			<th style="width:100px">Failed</th>
			<th style="width:100px">➡️</th>
		</tr>
	</thead>
	<tbody>
		<?php
		foreach ( $revisions as $revision ) :
			$rev_id = (int) ltrim( $revision->post_name, 'r' );

			$num_passed = ptr_count_test_results( $revision->ID );
			$num_failed = ptr_count_test_results( $revision->ID, 'failed' );
			?>
			<tr>
				<td>
          <a
            href="<?php echo esc_url( sprintf( 'https://core.trac.wordpress.org/changeset/%d', $rev_id ) ); ?>"
            title="<?php echo esc_attr( apply_filters( 'the_title', $revision->post_title ) ); ?>">
			r<?php echo esc_html( $rev_id ); ?>
          </a>
        </td>

        <td>
            <span class="ptr-status-badge ptr-status-badge-passed">
					<?php echo (int) $num_passed; ?>
            </span>
        </td>
        <td>
            <span class="ptr-status-badge ptr-status-badge-failed">
					<?php echo (int) $num_failed; ?>
            </span>
        </td>
        <td>
          <a href="<?php the_permalink( $revision->ID ); ?>">
            View
          </a>
        </td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
