<?php
/**
 * Reference Template: "More Information"
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( $explanation = get_explanation_content( get_the_ID() ) ) :
	?>
	<hr />
	<section class="explanation">
		<h2><?php _e( 'More Information', 'wporg' ); ?></h2>

		<?php echo $explanation; // Already escaped. ?>
	</section>
<?php endif; ?>
