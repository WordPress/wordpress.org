<?php
/**
 * Reference Template: User Contributed Notes
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( comments_open() || '0' != get_comments_number() ) :
	?>
	<hr />
	<section class="user-notes">
		<h2><?php _e( 'User Contributed Notes', 'wporg' ); ?></h2>
		<?php comments_template(); /* TODO: add '/user-notes.php' */ ?>
	</section>
<?php endif; ?>
