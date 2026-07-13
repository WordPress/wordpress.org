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

<?php
// "Open to Work" candidates section.
	$candidates_per_page = 10;
	$candidates_page     = isset( $_GET['cp'] ) ? max( 1, absint( $_GET['cp'] ) ) : 1;
	$candidates_result   = jobswp_get_open_to_work_candidates( $candidates_page, $candidates_per_page );
	$paged_candidates    = $candidates_result['candidates'];
	$total_candidates    = $candidates_result['total'];
	$total_pages         = $candidates_result['pages'];
if ( ! empty( $paged_candidates ) ) :
	?>
<!-- Open to Work Section -->
<section class="candidates-section" id="candidates">
	<div class="candidates-section__inner">
		<div class="candidates-section__header">
			<div>
				<h2><?php esc_html_e( 'People Open to Work', 'jobswp' ); ?></h2>
				<p><?php esc_html_e( 'WordPress community members who are looking for their next opportunity.', 'jobswp' ); ?></p>
			</div>
			<span class="candidates-section__count">
			<?php
			printf(
				/* translators: %s: number of candidates */
				esc_html( _n( '%s person', '%s people', $total_candidates, 'jobswp' ) ),
				esc_html( number_format_i18n( $total_candidates ) )
			);
			?>
			</span>
		</div>
		<div class="candidates-grid">
			<?php foreach ( $paged_candidates as $candidate ) : ?>
				<?php
				$current_role    = isset( $candidate->current_role )    ? $candidate->current_role    : '';
				$current_company = isset( $candidate->current_company ) ? $candidate->current_company : '';
				?>
				<a href="<?php echo esc_url( $candidate->profile_url ); ?>" class="candidate-card" target="_blank" rel="noopener noreferrer">
					<div class="candidate-card__avatar<?php echo ! empty( $candidate->show_badge ) ? ' has-badge' : ''; ?>">
						<img src="<?php echo esc_url( 'https://wordpress.org/grav-redirect.php?user=' . urlencode( $candidate->user_login ) . '&s=80' ); ?>" alt="" width="80" height="80" loading="lazy">
					</div>
					<div class="candidate-card__info">
						<h3 class="candidate-card__name"><?php echo esc_html( $candidate->display_name ); ?></h3>
						<?php if ( $current_role || $current_company ) : ?>
							<p class="candidate-card__role">
								<?php echo esc_html( $current_role ); ?>
								<?php if ( $current_company ) : ?>
									<span class="candidate-card__company"><?php echo esc_html( $current_company ); ?></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>
					<span class="candidate-card__arrow">&#8594;</span>
				</a>
			<?php endforeach; ?>
		</div>
		<?php if ( $total_pages > 1 ) : ?>
			<nav class="candidates-pagination" aria-label="<?php esc_attr_e( 'Candidates pagination', 'jobswp' ); ?>">
				<?php if ( $candidates_page > 1 ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'cp', $candidates_page - 1, home_url( '/' ) ) . '#candidates' ); ?>" class="candidates-pagination__link">&larr; <?php esc_html_e( 'Previous', 'jobswp' ); ?></a>
				<?php else : ?>
					<span class="candidates-pagination__link candidates-pagination__link--disabled">&larr; <?php esc_html_e( 'Previous', 'jobswp' ); ?></span>
				<?php endif; ?>

				<span class="candidates-pagination__info">
					<?php
					printf(
						/* translators: 1: current page, 2: total pages */
						esc_html__( 'Page %1$s of %2$s', 'jobswp' ),
						esc_html( $candidates_page ),
						esc_html( $total_pages )
					);
					?>
				</span>

				<?php if ( $candidates_page < $total_pages ) : ?>
					<a href="<?php echo esc_url( add_query_arg( 'cp', $candidates_page + 1, home_url( '/' ) ) . '#candidates' ); ?>" class="candidates-pagination__link"><?php esc_html_e( 'Next', 'jobswp' ); ?> &rarr;</a>
				<?php else : ?>
					<span class="candidates-pagination__link candidates-pagination__link--disabled"><?php esc_html_e( 'Next', 'jobswp' ); ?> &rarr;</span>
				<?php endif; ?>
			</nav>
		<?php endif; ?>
		<p class="candidates-section__cta">
			<?php
			echo wp_kses(
			sprintf(
				/* translators: %s: URL to update WordPress.org profile */
				__( 'Want to appear here? <a href="%s">Update your WordPress.org profile</a> and toggle "Open to Work" in the Jobs section.', 'jobswp' ),
				'https://profiles.wordpress.org/me/profile/edit/'
			),
			array( 'a' => array( 'href' => array() ) )
			);
			?>
		</p>
	</div>
</section>
	<?php
	endif;
?>
