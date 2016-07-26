<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $mentors */

?>

<h2>Mentors</h2>

<table class="widefat fixed striped">
	<thead>
		<th>Mentor</th>
		<th>Email Address</th>
		<th># Currently Mentoring</th>
		<th>Currently Mentoring</th>
	</thead>

	<tbody>
		<?php foreach ( $mentors as $email_address => $mentor ) : ?>
			<tr>
				<td><?php echo esc_html( $mentor['name'] );         ?></td>
				<td><?php echo esc_html( $email_address );          ?></td>
				<td><?php echo count( $mentor['camps_mentoring'] ); ?></td>
				<td>
					<ul>
						<?php foreach ( $mentor['camps_mentoring'] as $camp ) : ?>
							<li><?php echo esc_html( $camp ); ?></li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
