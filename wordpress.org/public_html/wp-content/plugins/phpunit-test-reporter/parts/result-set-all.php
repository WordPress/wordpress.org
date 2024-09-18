<?php
use PTR\Display;

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
            r<?php echo $rev_id; ?>
          </a>
        </td>

        <td>
            <span class="ptr-status-badge ptr-status-badge-passed">
			        <?php echo $num_passed; ?>
            </span>
        </td>
        <td>
            <span class="ptr-status-badge ptr-status-badge-failed">
			        <?php echo $num_failed; ?>
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
