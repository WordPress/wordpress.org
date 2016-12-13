<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $unmentored_camps */

?>

<h2>Active Camps without a Mentor</h2>

<p class="description">Note: This is based on the <strong>Mentor WordPress.org User Name</strong> field. Some of these camps may have Mentor name and/or email data, but have not yet been updated with the WordPress.org username.</p>

<?php if ( $unmentored_camps ) : ?>

	<ul class="ul-disc">
		<?php foreach ( $unmentored_camps as $id => $name ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( "post.php?post=$id&action=edit#wcpt_mentor_wordpress_org_user_name" ) ); ?>">
					<?php echo esc_html( $name ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

	<p class="description">(*) Camp has a Mentor email address but no WordPress.org username.</p>

<?php else : ?>

	<p>All active camps have been assigned a mentor.</p>

<?php endif; ?>
