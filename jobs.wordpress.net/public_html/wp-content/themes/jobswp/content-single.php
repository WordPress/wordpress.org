<?php
/**
 * Template for displaying a single job post.
 *
 * @package jobswp
 */

$fields = array(
	'company'  => __( 'Company', 'jobswp' ),
	'jobtype'  => __( 'Job Type', 'jobswp' ),
	'location' => __( 'Location', 'jobswp' ),
	'budget'   => __( 'Budget', 'jobswp' ),
);

// Build apply URL for the prominent CTA.
$howtoapply_raw    = get_post_meta( get_the_ID(), 'howtoapply', true );
$howtoapply_method = get_post_meta( get_the_ID(), 'howtoapply_method', true );
$apply_url  = '';
$apply_text = __( 'Apply Now', 'jobswp' );

if ( $howtoapply_raw ) {
	if ( ! $howtoapply_method ) {
		if ( 0 < strpos( $howtoapply_raw, '@' ) ) {
			$howtoapply_method = 'email';
		} elseif ( 0 === strpos( $howtoapply_raw, 'http' ) ) {
			$howtoapply_method = 'web';
		}
	}
	if ( 'email' === $howtoapply_method ) {
		$apply_url  = 'mailto:' . sanitize_email( $howtoapply_raw );
		$apply_text = __( 'Apply via Email', 'jobswp' );
	} elseif ( 'web' === $howtoapply_method ) {
		$raw_url = $howtoapply_raw;
		if ( 0 !== strpos( $raw_url, 'http' ) ) {
			$raw_url = 'http://' . $raw_url;
		}
		$apply_url = esc_url( $raw_url );
	}
}
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
			<?php if ( $apply_url ) : ?>
				<div class="job-sidebar__apply">
					<a href="<?php echo esc_attr( $apply_url ); ?>" class="btn btn-primary" rel="nofollow ugc noopener" <?php echo 'web' === $howtoapply_method ? 'target="_blank"' : ''; ?>>
						<?php echo esc_html( $apply_text ); ?>
					</a>
				</div>
			<?php endif; ?>
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

<?php
// "Consider These Candidates" section on single job pages.
	$single_result     = jobswp_get_open_to_work_candidates( 1, 4 );
	$single_candidates = $single_result['candidates'];
if ( ! empty( $single_candidates ) ) :
	?>
<div class="job-detail-candidates">
	<div class="job-detail-candidates__inner">
		<h3><?php esc_html_e( 'WordPress Professionals Open to Work', 'jobswp' ); ?></h3>
		<div class="job-detail-candidates__grid">
		<?php foreach ( $single_candidates as $person ) : ?>
				<?php
				$role    = isset( $person->current_role )    ? $person->current_role    : '';
				$company = isset( $person->current_company ) ? $person->current_company : '';
				?>
				<a href="<?php echo esc_url( $person->profile_url ); ?>" class="candidate-card candidate-card--compact" target="_blank" rel="noopener noreferrer">
					<div class="candidate-card__avatar<?php echo ! empty( $person->show_badge ) ? ' has-badge' : ''; ?>">
						<img src="<?php echo esc_url( 'https://wordpress.org/grav-redirect.php?user=' . urlencode( $person->user_login ) . '&s=64' ); ?>" alt="" width="64" height="64" loading="lazy">
					</div>
					<div class="candidate-card__info">
						<h3 class="candidate-card__name"><?php echo esc_html( $person->display_name ); ?></h3>
						<?php if ( $role || $company ) : ?>
							<p class="candidate-card__role">
								<?php echo esc_html( $role ); ?>
								<?php if ( $company ) : ?>
									<span class="candidate-card__company"><?php echo esc_html( $company ); ?></span>
								<?php endif; ?>
							</p>
						<?php endif; ?>
					</div>
					<span class="candidate-card__arrow">&#8594;</span>
				</a>
			<?php endforeach; ?>
		</div>
		<p class="job-detail-candidates__cta">
			<a href="<?php echo esc_url( home_url( '/#candidates' ) ); ?>"><?php esc_html_e( 'View all candidates &rarr;', 'jobswp' ); ?></a>
		</p>
	</div>
</div>
	<?php
	endif;
?>
