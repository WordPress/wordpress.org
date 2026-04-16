<?php
/**
 * Homepage template — hero, filters, and job cards grid.
 *
 * @package jobswp
 */

$job_categories = Jobs_Dot_WP::get_job_categories();

// Collect all published jobs across categories.
$all_jobs       = array();
$category_map   = array(); // job ID => category slug
$total_jobs     = 0;
$remote_count   = 0;
$categories_with_jobs = 0;

if ( $job_categories ) {
	foreach ( $job_categories as $job_cat ) {
		$jobs = Jobs_Dot_WP::get_jobs_for_category( $job_cat );
		if ( ! empty( $jobs ) ) {
			$categories_with_jobs++;
			foreach ( $jobs as $job ) {
				if ( ! isset( $category_map[ $job->ID ] ) ) {
					$all_jobs[]               = $job;
					$category_map[ $job->ID ] = $job_cat->slug;
					$total_jobs++;

					$location = get_post_meta( $job->ID, 'location', true );
					if ( empty( $location ) || 'N/A' === $location || stripos( $location, 'remote' ) !== false || stripos( $location, 'anywhere' ) !== false ) {
						$remote_count++;
					}
				}
			}
		}
	}
}

// Sort all jobs in reverse chronological order by date posted, making it easier to scan for newly posted jobs.
usort(
	$all_jobs,
	static function ( $a, $b ) {
		return array( $b->post_date, $b->ID ) <=> array( $a->post_date, $a->ID );
	}
);

$remote_pct = $total_jobs > 0 ? round( ( $remote_count / $total_jobs ) * 100 ) : 0;
?>

<!-- Hero Section -->
<section class="hero">
	<div class="hero__inner">
		<h1>
			<?php
			printf(
				/* translators: %s: highlighted word "Opportunity" */
				esc_html__( 'Find Your Next WordPress %s', 'jobswp' ),
				'<span class="hero__highlight">' . esc_html__( 'Opportunity', 'jobswp' ) . '</span>'
			);
			?>
		</h1>
		<p><?php esc_html_e( 'Browse open positions across the WordPress ecosystem — from development to design, support to community.', 'jobswp' ); ?></p>
		<div class="hero__actions">
			<a href="#jobs" class="btn btn-primary"><?php esc_html_e( 'Browse Jobs', 'jobswp' ); ?></a>
			<a href="<?php echo esc_url( home_url( '/post-a-job/' ) ); ?>" class="btn btn-outline"><?php esc_html_e( 'Post a Job', 'jobswp' ); ?></a>
		</div>
		<div class="hero__stats">
			<div class="hero__stat">
				<span class="hero__stat-number"><?php echo esc_html( $total_jobs ); ?></span>
				<span class="hero__stat-label"><?php esc_html_e( 'Open Positions', 'jobswp' ); ?></span>
			</div>
			<div class="hero__stat">
				<span class="hero__stat-number"><?php echo esc_html( $categories_with_jobs ); ?></span>
				<span class="hero__stat-label"><?php esc_html_e( 'Categories', 'jobswp' ); ?></span>
			</div>
			<div class="hero__stat">
				<span class="hero__stat-number"><?php echo esc_html( $remote_pct . '%' ); ?></span>
				<span class="hero__stat-label"><?php esc_html_e( 'Remote Friendly', 'jobswp' ); ?></span>
			</div>
		</div>
	</div>
</section>

<!-- Filter Pills -->
<section class="filters" id="jobs">
	<p class="filters__label"><?php esc_html_e( 'Filter by category', 'jobswp' ); ?></p>
	<div class="filters__pills">
		<button class="filter-pill active" data-category="all"><?php esc_html_e( 'All', 'jobswp' ); ?></button>
		<?php if ( $job_categories ) : ?>
			<?php foreach ( $job_categories as $job_cat ) : ?>
				<button class="filter-pill" data-category="<?php echo esc_attr( $job_cat->slug ); ?>"><?php echo esc_html( $job_cat->name ); ?></button>
			<?php endforeach; ?>
		<?php endif; ?>
	</div>
</section>

<!-- Job Cards Grid -->
<section class="jobs-section">
	<div class="jobs-grid">
		<?php if ( ! empty( $all_jobs ) ) : ?>
			<?php foreach ( $all_jobs as $job ) : ?>
				<?php
				$cat_slug = isset( $category_map[ $job->ID ] ) ? $category_map[ $job->ID ] : '';
				$cat_term = get_term_by( 'slug', $cat_slug, 'job_category' );
				$cat_name = $cat_term ? $cat_term->name : '';
				$company  = get_post_meta( $job->ID, 'company', true );
				$location = get_post_meta( $job->ID, 'location', true );
				$jobtype  = jobswp_get_job_meta( $job->ID, 'jobtype' );

				$is_remote        = empty( $location ) || 'N/A' === $location || stripos( $location, 'remote' ) !== false || stripos( $location, 'anywhere' ) !== false;
				$location_display = $is_remote ? __( 'Remote', 'jobswp' ) : $location;
				$location_icon    = $is_remote ? '&#127758;' : '&#128205;';

				$type_icon = '&#128188;';
				if ( 'Contract' === $jobtype || 'Project' === $jobtype ) {
					$type_icon = '&#128196;';
				} elseif ( 'Part Time' === $jobtype ) {
					$type_icon = '&#9201;';
				}
				?>
				<a href="<?php echo esc_url( get_permalink( $job->ID ) ); ?>" class="job-card" data-category="<?php echo esc_attr( $cat_slug ); ?>">
					<?php if ( $cat_name ) : ?>
						<span class="job-card__badge"><?php echo esc_html( $cat_name ); ?></span>
					<?php endif; ?>
					<h2 class="job-card__title"><?php echo esc_html( get_the_title( $job->ID ) ); ?></h2>
					<?php if ( $company ) : ?>
						<p class="job-card__company"><?php echo esc_html( $company ); ?></p>
					<?php endif; ?>
					<div class="job-card__meta">
						<span><?php echo wp_kses( $location_icon, array() ) . ' ' . esc_html( $location_display ); ?></span>
						<?php if ( $jobtype && 'N/A' !== $jobtype ) : ?>
							<span><?php echo wp_kses( $type_icon, array() ) . ' ' . esc_html( $jobtype ); ?></span>
						<?php endif; ?>
					</div>
					<p class="job-card__date">
						<?php
						printf(
							/* translators: %s: date the job was posted */
							esc_html__( 'Posted %s', 'jobswp' ),
							esc_html( get_the_date( 'F j, Y', $job->ID ) )
						);
						?>
					</p>
				</a>
			<?php endforeach; ?>
		<?php endif; ?>
		<div class="jobs-empty" <?php echo ! empty( $all_jobs ) ? 'style="display:none"' : ''; ?>>
			<p>
				<?php
				echo wp_kses(
					sprintf(
						/* translators: %s: URL to post a job */
						__( 'There are no jobs in this category. If you are hiring, you can <a href="%s">post a new job</a>.', 'jobswp' ),
						esc_url( home_url( '/post-a-job/' ) )
					),
					array( 'a' => array( 'href' => array() ) )
				);
				?>
			</p>
		</div>
	</div>
</section>
