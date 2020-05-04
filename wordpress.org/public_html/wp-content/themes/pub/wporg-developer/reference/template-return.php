<?php
/**
 * Reference Template: Return Information
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

$return = get_return();
if ( ! empty( $return ) ) :
	?>
	<hr />
	<section class="return">
		<h2><?php _e( 'Return', 'wporg' ); ?></h2>
		<p><?php echo $return; ?></p>
	</section>
<?php endif; ?>
