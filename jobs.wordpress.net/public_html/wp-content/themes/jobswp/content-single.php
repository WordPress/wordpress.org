<?php
/**
 * Template for displaying a single job post.
 *
 * @package jobswp
 */

$fields = array(
	'company'    => __( 'Company', 'jobswp' ),
	'jobtype'    => __( 'Job Type', 'jobswp' ),
	'location'   => __( 'Location', 'jobswp' ),
	'budget'     => __( 'Budget', 'jobswp' ),
	'howtoapply' => __( 'How to Apply', 'jobswp' ),
);
?>

<div class="breadcrumb">
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Jobs', 'jobswp' ); ?></a>
	<span class="separator">/</span>
	<?php the_title(); ?>
</div>

<div class="job-detail">
	<div class="job-detail__main">
		<div class="job-detail__header">
			<h1><?php the_title(); ?></h1>

			<div class="job-detail__meta">
				<?php jobswp_posted_on(); ?>

				<span class="job-categories">
					<?php
					$job_cats = get_the_terms( get_the_ID(), 'job_category' );
					if ( $job_cats && ! is_wp_error( $job_cats ) ) :
						foreach ( $job_cats as $job_cat ) :
							?>
							<span class="job-card__badge"><?php echo esc_html( $job_cat->name ); ?></span>
							<?php
						endforeach;
					endif;
					?>
				</span>
			</div>
		</div>

		<div class="job-detail__body">
			<?php the_content(); ?>
		</div>

		<footer>
			<?php
			if ( ! is_page() ) {
				edit_post_link( __( 'Edit', 'jobswp' ), '<span class="edit-link">', '</span>' );
			}
			?>
		</footer>
	</div>

	<aside class="job-sidebar">
		<div class="job-sidebar__card">
			<h3><?php esc_html_e( 'Job Details', 'jobswp' ); ?></h3>
			<?php
			foreach ( $fields as $fname => $flabel ) :
				$val = jobswp_get_job_meta( get_the_ID(), $fname );
				if ( $val ) :
					?>
					<div class="job-sidebar__detail">
						<span class="job-sidebar__detail-label"><?php echo esc_html( $flabel ); ?></span>
						<span class="job-sidebar__detail-value">
						<?php
						echo wp_kses(
							$val,
							array(
								'a' => array(
									'href' => array(),
									'rel'  => array(),
								),
							)
						);
						?>
					</span>
					</div>
					<?php
				endif;
			endforeach;
			?>
		</div>
	</aside>
</div>
