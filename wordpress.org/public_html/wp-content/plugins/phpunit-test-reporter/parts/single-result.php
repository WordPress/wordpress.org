<?php
use PTR\Display;

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
} ?>

<?php echo Display::get_display_css(); ?>

<?php
$parent = get_post( $report->post_parent );
if ( $parent ) :
?>
<p><a href="<?php echo esc_url( get_permalink( $parent ) ); ?>">&larr; <?php echo esc_html( $parent->post_name ) . ': ' . apply_filters( 'the_title', get_the_title( $parent ) ); ?></a></p>
<?php endif; ?>

<p><a href="<?php echo esc_url( get_permalink( $report->ID ) ); ?>" title="<?php echo esc_attr( $status_title ); ?>" class="<?php echo esc_attr( 'ptr-status-badge ptr-status-badge-' . strtolower( $status ) ); ?>"><?php echo esc_html( $status ); ?></a></p>

<h2>Environment</h2>

<table>
	<tr>
		<td><strong>Host</strong></td>
		<td><?php echo esc_html( $host ); ?></td>
	</tr>
	<tr>
		<td><strong>PHP Version</strong></td>
		<td><?php echo esc_html( Display::get_display_php_version( $report->ID ) ); ?></td>
	</tr>
	<tr>
		<td><strong>Database Version</strong></td>
		<td><?php echo esc_html( Display::get_display_mysql_version( $report->ID ) ); ?></td>
	</tr>
	<tr>
		<td><strong>Extensions</strong></td>
		<td><?php echo esc_html( Display::get_display_extensions( $report->ID ) ); ?></td>
	</tr>
</table>

<?php if ( ! empty( $results['failures'] ) ) : ?>
	<h2>Errors/Failures</h2>

	<?php
	foreach ( $results['testsuites'] as $suite_name => $testsuite ) :
		if ( empty( $testsuite['failures'] ) && empty( $testsuite['errors'] ) ) {
			continue;
		}
		foreach ( $testsuite['testcases'] as $test_name => $testcase ) :
		?>
		<p><strong><?php echo esc_html( $suite_name . '::' . $test_name ); ?></strong></p>
		<pre><?php echo ! empty( $testcase['failure'] ) ? $testcase['failure'] : $testcase['error']; ?></pre>
		<?php endforeach; ?>
		<?php endforeach; ?>
<?php endif; ?>
