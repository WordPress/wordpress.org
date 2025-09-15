<?php
/**
 * Template part for displaying page content in page.php.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 *
 * @package WordPressdotorg\Theme
 */

 use WordPressdotorg\Photo_Directory\Registrations;

$tax_name = Registrations::get_taxonomy( 'categories' );
$terms = get_terms( [ 'taxonomy' => $tax_name, 'hide_empty' => false ] );
?>

<header class="page-header">
	<h2 class="page-title">
		<?php _e( 'Categories', 'wporg-photo' ); ?>
	</h2>
</header><!-- .page-header -->


<?php the_content(); ?>

<?php foreach ( $terms as $term ) :	?>

	<article class="taxonomy-<?php echo esc_attr( $tax_name ); ?> taxonomy-<?php echo esc_attr( $tax_name ); ?>-<?php echo esc_attr( $term->slug ); ?>">
		<header class="entry-header">
		</header><!-- .entry-header -->

		<div class="entry-content">
			<span></span>
			<a href="<?php echo esc_url( get_term_link( $term->term_id ) ); ?>"><?php echo $term->name; ?> (<?php echo number_format_i18n( $term->count ); ?>)</a>
		</div><!-- .entry-content -->

		<footer class="entry-footer">
		</footer><!-- .entry-footer -->

	</article><!-- #post-## -->

<?php endforeach; ?>
