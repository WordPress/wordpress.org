<?php

/*
 * Template Name: Pledges
 *
 * Note: Pledges is a simulated page, rather than actually existing in the database; see `mu-plugins/make-network/team-pledges.php`.
 *
 * Page 1 of the Five for the Future redesign — team contributor directory.
 * Output over intent: ranks contributors by recent shipped work, decays inactive contributors.
 */

namespace WordPressdotorg\Make\Breathe_2024;
use WordPressdotorg\Make\Pledges;

defined( 'WPINC' ) || die();

require_once __DIR__ . '/inc/contribution-metrics.php';

get_header();

$current_team = Pledges\get_current_team();
$contributors = Pledges\get_team_contributors(
	$current_team->post_name,
	$current_team->post_title
);

// ------------------------------------------------------------------
// Real contribution metrics from Trac props + GitHub activity.
// One bulk DB call per signal source, hour-cached.
// ------------------------------------------------------------------
$window_days = ContributionMetrics\resolve_window_days(
	isset( $_GET['window'] ) ? $_GET['window'] : ContributionMetrics\WINDOW_DAYS_DEFAULT
);

$metrics = ContributionMetrics\get_team_contribution_metrics(
	array_keys( $contributors ),
	$current_team->post_name,
	$window_days
);

foreach ( $contributors as $uid => &$c ) {
	$user_metrics                = isset( $metrics[ $uid ] ) ? $metrics[ $uid ] : ContributionMetrics\empty_metrics();
	$bucket                      = ContributionMetrics\format_active_bucket( $user_metrics['last_activity'] );
	$c['_metrics_high']          = (int) $user_metrics['high_count'];
	$c['_metrics_medium']        = (int) $user_metrics['medium_count'];
	$c['_metrics_low']           = (int) $user_metrics['low_count'];
	$c['_metrics_weighted']      = (int) $user_metrics['weighted_volume'];
	$c['_metrics_active_bucket'] = $bucket['bucket'];
	$c['_metrics_active_class']  = $bucket['class'];
	$c['_metrics_top_repos']     = array_keys( $user_metrics['top_repos'] );
	$c['_metrics_window_days']   = $window_days;
}
unset( $c );

uasort(
	$contributors,
	function ( $a, $b ) {
		return $b['_metrics_weighted'] - $a['_metrics_weighted'];
	}
);

// Split the list into active (verified output in window) vs inactive.
// The spec is explicit: directory ranks by recent shipped work and decays
// contributors who stopped. Inactive ones are hidden from the default view.
$active_contributors   = array();
$inactive_contributors = array();
foreach ( $contributors as $uid => $c ) {
	if ( (int) $c['_metrics_weighted'] > 0 ) {
		$active_contributors[ $uid ] = $c;
	} else {
		$inactive_contributors[ $uid ] = $c;
	}
}

// `?show_inactive=1` opt-in to render inactive ones too (still beneath the active list).
$show_inactive = ! empty( $_GET['show_inactive'] );

$total_count       = count( $contributors );
$active_count      = count( $active_contributors );
$inactive_count    = count( $inactive_contributors );
$independent_count = 0;
$sponsored_count   = 0;
foreach ( $contributors as $c ) {
	if ( ! empty( $c['sponsored'] ) ) {
		$sponsored_count++;
	} else {
		$independent_count++;
	}
}

wp_enqueue_style(
	'wporg-breathe-page-pledges',
	get_stylesheet_directory_uri() . '/css/page-pledges.css',
	array( 'wporg-breathe' ),
	filemtime( __DIR__ . '/css/page-pledges.css' )
);
wp_enqueue_script(
	'wporg-breathe-page-pledges',
	get_stylesheet_directory_uri() . '/js/page-pledges.js',
	array(),
	filemtime( __DIR__ . '/js/page-pledges.js' ),
	true
);

?>

<div id="primary" class="content-area">
	<div class="site-content template-pledges template-pledges-v2" role="main">

		<header class="page-header pledges-hero">
			<h1 class="page-title">
				<?php echo esc_html( sprintf( __( 'People contributing to %s', 'wporg-5ftf' ), $current_team->post_title ) ); ?>
			</h1>
			<p class="pledges-subtitle">
				<?php esc_html_e( 'Ranked by recent shipped work, not by pledged hours.', 'wporg-5ftf' ); ?>
			</p>
		</header>

		<article id="post-pledges" class="page type-page status-publish hentry pledges-article">
			<div class="entry-content">

				<?php if ( $contributors ) : ?>

					<div class="pledges-howitworks" id="pledges-howitworks" role="note">
						<div class="pledges-howitworks-icon" aria-hidden="true">i</div>
						<div class="pledges-howitworks-body">
							<strong><?php esc_html_e( 'How this page works', 'wporg-5ftf' ); ?></strong>
							<p>
								<?php esc_html_e( 'Contributors are ranked by weighted recent activity, not by pledged hours. Signals come from Trac props (release credits) and GitHub activity (merged PRs, pushes, closed issues), bucketed into high, medium, and low weight. Low-weight contributions (typo fixes, whitespace) don\'t factor into the ranking. Inactive contributors decay down the list automatically.', 'wporg-5ftf' ); ?>
							</p>
						</div>
						<button type="button" class="pledges-howitworks-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'wporg-5ftf' ); ?>">&times;</button>
					</div>

					<div class="pledges-health" role="status">
						<span class="pledges-health-num"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></span>
						<span class="pledges-health-text">
							<?php
							echo wp_kses_data( sprintf(
								/* translators: 1: team name, 2: window in days */
								__( 'opted-in contributors to %1$s in the last %2$d days.', 'wporg-5ftf' ),
								'<strong>' . esc_html( $current_team->post_title ) . '</strong>',
								$window_days
							) );
							?>
						</span>
						<span class="pledges-health-split">
							<span class="pledges-health-chip independent"><strong><?php echo esc_html( number_format_i18n( $independent_count ) ); ?></strong> <?php esc_html_e( 'independent', 'wporg-5ftf' ); ?></span>
							<span class="pledges-health-chip sponsored"><strong><?php echo esc_html( number_format_i18n( $sponsored_count ) ); ?></strong> <?php esc_html_e( 'sponsored', 'wporg-5ftf' ); ?></span>
						</span>
					</div>

					<div class="pledges-toolbar">
						<div class="pledges-filters" role="group" aria-label="<?php esc_attr_e( 'Filter contributors', 'wporg-5ftf' ); ?>">
							<div class="pledges-filter-group">
								<span class="pledges-filter-label"><?php esc_html_e( 'Time window', 'wporg-5ftf' ); ?></span>
								<?php
								// home_url('/pledges/') already includes the team site path, so no REQUEST_URI concat needed.
								$pledges_url   = home_url( '/pledges/' );
								$default_w     = ContributionMetrics\WINDOW_DAYS_DEFAULT;
								$url_for_30    = 30 === $default_w ? $pledges_url : add_query_arg( 'window', 30, $pledges_url );
								$url_for_90    = 90 === $default_w ? $pledges_url : add_query_arg( 'window', 90, $pledges_url );
								$url_for_180   = 180 === $default_w ? $pledges_url : add_query_arg( 'window', 180, $pledges_url );
								?>
								<a class="pledges-chip<?php echo 30 === $window_days ? ' is-on' : ''; ?>" href="<?php echo esc_url( $url_for_30 ); ?>"><?php esc_html_e( '30 days', 'wporg-5ftf' ); ?></a>
								<a class="pledges-chip<?php echo 90 === $window_days ? ' is-on' : ''; ?>" href="<?php echo esc_url( $url_for_90 ); ?>"><?php esc_html_e( '90 days', 'wporg-5ftf' ); ?></a>
								<a class="pledges-chip<?php echo 180 === $window_days ? ' is-on' : ''; ?>" href="<?php echo esc_url( $url_for_180 ); ?>"><?php esc_html_e( '6 months', 'wporg-5ftf' ); ?></a>
							</div>
							<div class="pledges-filter-group">
								<span class="pledges-filter-label"><?php esc_html_e( 'Sponsorship', 'wporg-5ftf' ); ?></span>
								<button type="button" class="pledges-chip" data-filter="sponsorship" data-value="all"><?php esc_html_e( 'All', 'wporg-5ftf' ); ?></button>
								<button type="button" class="pledges-chip is-on" data-filter="sponsorship" data-value="independent"><?php esc_html_e( 'Independent', 'wporg-5ftf' ); ?></button>
								<button type="button" class="pledges-chip" data-filter="sponsorship" data-value="sponsored"><?php esc_html_e( 'Sponsored', 'wporg-5ftf' ); ?></button>
							</div>
						</div>
					</div>

					<p class="pledges-sort-label">
						<strong>
							<?php
							echo esc_html( sprintf(
								/* translators: %d: window in days */
								__( 'Ranked by weighted volume, last %d days.', 'wporg-5ftf' ),
								$window_days
							) );
							?>
						</strong>
						<span class="pledges-result-count">
							<span id="pledges-result-count"><?php echo esc_html( $active_count ); ?></span>
							<?php
							echo esc_html( sprintf(
								/* translators: 1: active count, 2: total count */
								_n( 'active contributor (of %2$d total)', 'active contributors (of %2$d total)', $active_count, 'wporg-5ftf' ),
								$active_count,
								$total_count
							) );
							?>
						</span>
					</p>

					<div class="pledges-grid" id="pledges-grid">
						<?php
						$rank = 0;
						foreach ( $active_contributors as $contributor ) {
							$rank++;
							$contributor['_rank'] = $rank;
							require __DIR__ . '/content-pledge.php';
						}

						if ( $show_inactive && $inactive_contributors ) {
							$inactive_label = sprintf(
								/* translators: 1: count, 2: window in days */
								_n( '%1$d contributor with no verified contributions in the last %2$d days', '%1$d contributors with no verified contributions in the last %2$d days', $inactive_count, 'wporg-5ftf' ),
								$inactive_count,
								$window_days
							);
							echo '<div class="pledges-inactive-divider"><span>' . esc_html( $inactive_label ) . '</span></div>';

							foreach ( $inactive_contributors as $contributor ) {
								$rank++;
								$contributor['_rank'] = $rank;
								require __DIR__ . '/content-pledge.php';
							}
						}
						?>
					</div>

					<?php if ( $inactive_contributors ) : ?>
						<p class="pledges-inactive-toggle">
							<?php if ( $show_inactive ) : ?>
								<a href="<?php echo esc_url( remove_query_arg( 'show_inactive' ) ); ?>">
									<?php
									echo esc_html( sprintf(
										/* translators: %d: count */
										_n( 'Hide %d inactive contributor', 'Hide %d inactive contributors', $inactive_count, 'wporg-5ftf' ),
										$inactive_count
									) );
									?>
								</a>
							<?php else : ?>
								<a href="<?php echo esc_url( add_query_arg( 'show_inactive', '1' ) ); ?>">
									<?php
									echo esc_html( sprintf(
										/* translators: 1: count, 2: window in days */
										_n( 'Show %1$d contributor with no verified contributions in the last %2$d days', 'Show %1$d contributors with no verified contributions in the last %2$d days', $inactive_count, 'wporg-5ftf' ),
										$inactive_count,
										$window_days
									) );
									?>
								</a>
							<?php endif; ?>
						</p>
					<?php endif; ?>

					<div class="pledges-empty" id="pledges-empty" hidden>
						<p><?php esc_html_e( 'No contributors match the selected filters.', 'wporg-5ftf' ); ?></p>
						<button type="button" class="pledges-empty-reset"><?php esc_html_e( 'Clear filters', 'wporg-5ftf' ); ?></button>
					</div>

				<?php else : ?>

					<p>
						<?php
						echo wp_kses_post( sprintf(
							/* translators: %s: profile edit URL */
							__( 'Nobody has indicated that they\'re sponsored to contribute to this team. If you are, please <a href="%s">update your profile</a> to indicate that.', 'wporg-5ftf' ),
							'https://profiles.wordpress.org/me/profile/edit/group/5/'
						) );
						?>
					</p>

				<?php endif; ?>

			</div>
		</article>

	</div>
</div>

<!-- A fake o2 content area -->
<div style="display: none;"><div id="content"></div></div>

<?php

get_sidebar();
get_footer();
