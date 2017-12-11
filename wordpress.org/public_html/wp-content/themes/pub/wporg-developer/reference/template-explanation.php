<?php
/**
 * Reference Template: "More Information"
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( $explanation = get_explanation_field( 'post_content', get_the_ID() ) ) :
	?>
	<hr />
	<section class="explanation">
		<h2><?php _e( 'More Information', 'wporg' ); ?></h2>

		<?php
		// Output the explanation. Passed through 'the_content' on retrieval, thus no escaping.
		remove_filter( 'the_content', array( 'DevHub_Formatting', 'fix_unintended_markdown' ), 1 );
		global $post;
		$orig = $post;
		$post = get_explanation( get_the_ID() );
		echo apply_filters( 'the_content', apply_filters( 'get_the_content', $explanation ) );
		$post = $orig;
		add_filter( 'the_content', array( 'DevHub_Formatting', 'fix_unintended_markdown' ), 1 );
		?>
	</section>
<?php endif; ?>
