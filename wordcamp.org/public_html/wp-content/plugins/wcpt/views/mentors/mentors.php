<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $mentors */

?>

<h2>Mentors</h2>

<table class="widefat fixed striped">
	<thead>
		<th>Mentor</th>
		<th>Username</th>
		<th>Email Address</th>
		<th># Currently Mentoring</th>
		<th>Camps</th>
	</thead>

	<tbody>
		<?php foreach ( $mentors as $username => $mentor ) : ?>
			<tr>
				<td><?php echo esc_html( $mentor['name'] );         ?></td>
				<td><?php echo esc_html( $username );               ?></td>
				<td><?php echo esc_html( $mentor['email'] );        ?></td>
				<td><?php echo count( $mentor['camps_mentoring'] ); ?></td>
				<td>
					<ul>
						<?php foreach ( $mentor['camps_mentoring'] as $camp_id => $camp_name ) : ?>
							<li>
								<a href="<?php echo esc_url( admin_url( "post.php?post=$camp_id&action=edit#wcpt_mentor_wordpress_org_user_name" ) ); ?>"><?php echo esc_html( $camp_name ); ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>

<p class="description" style="text-align: right">* Camp has a Mentor email address but not a WordPress.org username.</p>