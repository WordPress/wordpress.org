<?php
/**
 * Reference Template: Description
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;
?>

<hr />
<section class="description">
	<h2><?php _e( 'Description', 'wporg' ); ?></h2>
	<?php echo get_description(); ?>

	<?php if ( $see = get_see_tags() ) : ?>
	<h3><?php _e( 'See also', 'wporg' ); ?></h3>

	<ul>
	<?php
	foreach ( $see as $tag ) {
		$see_ref = '';
		if ( ! empty( $tag['refers'] ) ) {
			$see_ref .= '{@see ' . $tag['refers'] . '}';
		}
		if ( ! empty( $tag['content'] ) ) {
			if ( $see_ref ) {
				$see_ref .= ': ';
			}
			$see_ref .= $tag['content'];
		}
		// Process text for auto-linking, etc.
		remove_filter( 'the_content', 'wpautop' );
		$see_ref = apply_filters( 'the_content', apply_filters( 'get_the_content', $see_ref ) );
		add_filter( 'the_content', 'wpautop' );
	
		echo '<li>' . $see_ref . "</li>\n";
	}
	?>
	</ul>
	<?php endif; ?>
</section>

