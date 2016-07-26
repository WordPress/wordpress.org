<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $unmentored_camps */

?>

<h2>Active Camps without a Mentor</h2>

<?php if ( $unmentored_camps ) : ?>

	<ul class="ul-disc">
		<?php foreach ( $unmentored_camps as $id => $name ) : ?>
			<li>
				<a href="<?php echo esc_url( admin_url( "post.php?post=$id&action=edit" ) ); ?>">
					<?php echo esc_html( $name ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>

<?php else : ?>

	<p>All active camps have been assigned a mentor.</p>

<?php endif; ?>
