<?php
/**
 * @package jobswp
 */
?>

<div class="entry-article">

	<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
	<header class="entry-header">
		<h1 class="entry-title"><?php the_title(); ?></h1>

		<div class="entry-meta">
			<?php jobswp_posted_on(); ?>

			<span class="job-categories">
				<?php echo get_the_category_list(); ?>
				<?php foreach ( get_the_terms( get_the_ID(), 'job_category' ) as $job_cat ) : ?>
					<span class="job-cat-item job-cat-item-<?php echo esc_attr( $job_cat->slug ); ?>"><?php echo esc_html( $job_cat->name ); ?></span>
				<?php endforeach; ?>
			</span>
		</div><!-- .entry-meta -->
	</header><!-- .entry-header -->

	<div class="clear"></div>

	<div class="entry-main grid_6 alpha">

	<div class="entry-content">
		<?php the_content(); ?>
		<?php
			wp_link_pages( array(
				'before' => '<div class="page-links">' . __( 'Pages:', 'jobswp' ),
				'after'  => '</div>',
			) );
		?>
	</div><!-- .entry-content -->

	<footer class="entry-meta grid_6">
		<?php
			// Don't show edit link when template is used to embed post in a page.
			if ( ! is_page() )
				edit_post_link( __( 'Edit', 'jobswp' ), '<span class="edit-link">', '</span>' );
		?>
	</footer><!-- .entry-meta -->
	</div>

	<div class="job-meta grid_3 omega">
		<?php
			$fields = array(
				'company'    => __( 'Company', 'jobswp' ),
				'jobtype'    => __( 'Job Type', 'jobswp' ),
				'location'   => __( 'Location', 'jobswp' ),
				'budget'     => __( 'Budget', 'jobswp' ),
				'howtoapply' => __( 'How to Apply', 'jobswp' ),
			);
			foreach ( $fields as $fname => $flabel ) :
				$val = jobswp_get_job_meta( get_the_ID(), $fname );
				if ( $val ) :
		?>
					<dl class="job-<?php echo $fname; ?>">
						<dt><?php echo $flabel; ?></dt>
						<dd><?php echo $val; ?></dd>
					</dl>
		<?php
				endif;
			endforeach;
		?>
	</div>

	<div class="clear"></div>

	</article><!-- #post-## -->

</div>
