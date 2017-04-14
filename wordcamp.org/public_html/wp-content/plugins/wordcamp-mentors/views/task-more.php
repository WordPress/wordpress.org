<?php
/**
 * Template for the "more" row that displays additional information about a task.
 *
 * @package WordCamp\Mentors
 */

namespace WordCamp\Mentors\Tasks\Dashboard;
defined( 'WPINC' ) || die();

use WordCamp\Mentors;

/* @var int $columns */

?>
<# if ( data.excerpt.rendered || data.helpLink.text ) { #>
	<td class="task column-task">
		{{ data.excerpt.rendered }}
		<# if ( data.helpLink.text && data.helpLink.url ) { #>
			<br /><br />
			<a href="{{ data.helpLink.url }}" target="_blank" class="<?php echo esc_attr( Mentors\PREFIX ); ?>-help-link">
				{{ data.helpLink.text }}
				<span class="dashicons dashicons-external" aria-hidden="true"></span>
			</a>
		<# } #>
	</td>
	<td class="" colspan="<?php echo esc_attr( $columns - 1 ); ?>"></td>
<# } #>