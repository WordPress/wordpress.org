<?php

namespace WordCamp\Mentors_Dashboard;
defined( 'WPINC' ) or die();

/** @var array $mentors          */
/** @var array $unmentored_camps */

?>

<div class="wrap">
	<h1>WordCamp Mentors Dashboard</h1>

	<ul>
		<li>Number of mentors:             <?php echo count( $mentors );                                ?></li>
		<li>Active camps being mentored:   <?php echo absint( count_camps_being_mentored( $mentors ) ); ?></li>
		<li>Active camps without a mentor: <?php echo count( $unmentored_camps );                       ?></li>
	</ul>

	<?php require_once( __DIR__ . '/mentors.php'          ); ?>
	<?php require_once( __DIR__ . '/unmentored-camps.php' ); ?>
	<?php require_once( __DIR__ . '/manage-mentors.php' ); ?>
</div>
