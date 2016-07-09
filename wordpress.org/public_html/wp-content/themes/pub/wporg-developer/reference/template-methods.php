<?php
/**
 * Reference Template: Class Methods
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( 'wp-parser-class' === get_post_type() ) :
	if ( $children = get_children( array( 'post_parent' => get_the_ID(), 'post_status' => 'publish' ) ) ) :
		usort( $children, __NAMESPACE__ . '\\compare_objects_by_name' );
		?>
		<hr />
		<section class="class-methods">
			<h2><?php _e( 'Methods', 'wporg' ); ?></h2>
			<ul>
				<?php foreach ( $children as $child ) : ?>
					<li><a href="<?php echo get_permalink( $child->ID ); ?>">
							<?php
							$title = get_the_title( $child );
							$pos = ( $i = strrpos( $title, ':' ) ) ? $i + 1 : 0;
							echo substr( $title, $pos );
							?></a>
						<?php if ( $excerpt = apply_filters( 'get_the_excerpt', $child->post_excerpt ) ) {
							echo '&mdash; ' . sanitize_text_field( $excerpt );
						} ?>
						<?php if ( is_deprecated( $child->ID ) ) {
							echo '&mdash; <span class="deprecated-method">' . __( 'deprecated', 'wporg' ) . '</span>';
						} ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</section>
	<?php endif;
endif; ?>
