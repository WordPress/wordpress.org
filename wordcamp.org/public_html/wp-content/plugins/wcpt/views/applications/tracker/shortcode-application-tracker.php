<?php

namespace WordPress_Community\Applications\Tracker;
defined( 'WPINC' ) or die();

?>

<table class="application-tracker striped">
	<thead>
		<tr>
			<th class="city"       >City</th>
			<th class="applicant"  >Applicant</th>
			<th class="milestone"  >Milestone</th>
			<th class="status"     >Status</th>
			<th class="last-update">Last Update</th>
		</tr>
	</thead>

	<tbody>
		<?php foreach ( $posts as $post ) : ?>
			<tr>
				<td class="city"       ><?php echo esc_html( $post->post_title );                                  ?></td>
				<td class="applicant"  ><?php echo esc_html( get_post_meta( $post->ID, 'Organizer Name', true ) ); ?></td>
				<td class="milestone"  ><?php echo esc_html( $milestones[ $post->post_status ] );                  ?></td>
				<td class="status"     ><?php echo esc_html( $statuses[ $post->post_status ] );                    ?></td>
				<td class="last-update"><?php echo esc_html( get_last_status_update_time_diff( $post->ID ) );      ?></td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>
