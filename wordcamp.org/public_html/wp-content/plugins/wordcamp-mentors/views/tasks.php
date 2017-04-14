<?php
/**
 * Template for the Planning Checklist page.
 *
 * @package WordCamp\Mentors
 */

namespace WordCamp\Mentors\Tasks\Dashboard;
defined( 'WPINC' ) || die();

use WordCamp\Mentors\Tasks;

/* @var Tasks\List_Table $list_table */

?>
<div class="wrap">
	<h1><?php esc_html_e( 'Planning Checklist', 'wordcamporg' ); ?></h1>

	<?php $list_table->display(); ?>
</div>
