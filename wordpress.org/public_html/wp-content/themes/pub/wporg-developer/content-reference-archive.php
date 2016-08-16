<?php namespace DevHub;
/**
 * The template used for displaying reference content in archive.php & search.php
 *
 * @package wporg-developer
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>

	<h1><a href="<?php the_permalink() ?>"><?php the_title(); ?></a></h1>

	<div class="description">
		<?php the_excerpt(); ?>
	</div>
	<div class="sourcefile">
		<p>Source: <?php echo get_source_file(); ?>:<?php echo get_line_number(); ?></p>
	</div>


	<?php
	if ( show_usage_info() ) :

		$used_by = ( $q = get_used_by() ) ? $q->post_count : 0;
		$uses    = ( $q = get_uses()    ) ? $q->post_count : 0;
	?>
	<div class="meta">
		<?php printf(
			_n( 'Used by <a href="%s">1 function</a>', 'Used by <a href="%s">%d functions</a>', $used_by, 'wporg' ),
			esc_url( apply_filters( 'the_permalink', get_permalink() ) ) . '#usage',
			$used_by
		); ?>
		|
		<?php printf(
			_n( 'Uses <a href="%s">1 function</a>', 'Uses <a href="%s">%d functions</a>', $uses, 'wporg' ),
			esc_url( apply_filters( 'the_permalink', get_permalink() ) ) . '#usage',
			$uses
		); ?>
	</div>

	<?php endif;?>

</article>
