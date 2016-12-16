<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $mentors          */
/** @var array $unmentored_camps */

?>

<div class="wrap">
	<h1>WordCamp Mentors Dashboard</h1>

	<ul class="ul-disc">
		<li>Number of mentors:             <strong><?php echo count( $mentors ); ?></strong></li>
		<li>Active camps being mentored:   <strong><?php echo absint( count_camps_being_mentored( $mentors ) ); ?></strong></li>
		<li>Active camps without a mentor: <strong><?php echo count( $unmentored_camps ); ?></strong></li>
	</ul>

	<?php require_once( __DIR__ . '/mentors.php'          ); ?>
	<?php require_once( __DIR__ . '/unmentored-camps.php' ); ?>
	<?php require_once( __DIR__ . '/manage-mentors.php' ); ?>
</div>
