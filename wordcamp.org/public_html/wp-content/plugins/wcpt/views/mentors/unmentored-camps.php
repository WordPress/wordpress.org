<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $unmentored_camps */

?>

<div class="card">

	<?php if ( $unmentored_camps ) : ?>

		<h2>Active Camps without a Mentor</h2>

		<?php if ( ! empty( $unmentored_camps['yesdate'] ) ) : ?>

			<ul class="ul-disc">
				<?php foreach ( $unmentored_camps['yesdate'] as $id => $camp ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( "post.php?post=$id&action=edit#wcpt_mentor_wordpress_org_user_name" ) ); ?>"><?php echo esc_html( $camp['name'] ); ?></a> (<?php echo esc_html( $camp['date'] ); ?>)<?php if ( $camp['has_email'] ) : echo ' *'; $unmentored_camps['footnote'] = true; endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php endif; ?>
		<?php if ( ! empty( $unmentored_camps['nodate'] ) ) : ?>

			<p><strong>No start date yet</strong></p>

			<ul class="ul-disc">
				<?php foreach ( $unmentored_camps['nodate'] as $id => $camp ) : ?>
					<li>
						<a href="<?php echo esc_url( admin_url( "post.php?post=$id&action=edit#wcpt_mentor_wordpress_org_user_name" ) ); ?>"><?php echo esc_html( $camp['name'] ); ?></a><?php if ( $camp['has_email'] ) : echo ' *'; $unmentored_camps['footnote'] = true; endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php endif; ?>
		<?php if ( isset( $unmentored_camps['footnote'] ) ) : ?>

			<p><em>* Camp has a Mentor email address but not a WordPress.org username matching an active Mentor.</em></p>

		<?php endif; ?>

	<?php else : ?>

		<p>All active camps have been assigned a mentor.</p>

	<?php endif; ?>

</div>