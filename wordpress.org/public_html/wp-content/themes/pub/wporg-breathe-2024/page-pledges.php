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

// Enqueue BEFORE get_header() so wp_print_styles (priority 8 on wp_head)
// flushes the <link> into <head>. Enqueuing after get_header() would miss
// that flush and force the late-styles fallback to emit the <link> near
// </body>, causing a visible FOUC on the redesigned grid.
//
// /pledges/ is a virtual route registered by the team-pledges mu-plugin, so
// is_page_template() never reports it — we can't gate via wp_enqueue_scripts
// in functions.php. Template-level enqueue is the correct seam.
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

get_header();

$current_team = Pledges\get_current_team();

// ------------------------------------------------------------------
// Teams without wired-up tracking get an invitation page instead of
// the directory. Renders the legacy /pledges/ context as an invite to
// help bring contribution tracking to the team.
// ------------------------------------------------------------------
if ( ! ContributionMetrics\team_has_tracking_data( $current_team->post_name ) ) {
	$team_slug    = $current_team->post_name;
	$handbook_url = 'https://make.wordpress.org/' . $team_slug . '/handbook/';
	/* translators: %s: team name */
	$handbook_label = sprintf( __( 'Open the %s Handbook', 'wporg-5ftf' ), $current_team->post_title );
	?>
	<div id="primary" class="content-area">
		<div class="site-content template-pledges template-pledges-guide" role="main">

			<header class="page-header pledges-hero">
				<h1 class="page-title">
					<?php echo esc_html( sprintf( __( 'People contributing to %s', 'wporg-5ftf' ), $current_team->post_title ) ); ?>
				</h1>
				<p class="pledges-subtitle">
					<?php esc_html_e( 'Help bring contribution tracking to this team.', 'wporg-5ftf' ); ?>
				</p>
			</header>

			<article id="post-pledges" class="page type-page status-publish hentry pledges-article">
				<div class="entry-content">

					<div class="pledges-guide-intro">
						<p>
							<?php
							echo wp_kses_data( sprintf(
								/* translators: %s: team name */
								__( 'We don\'t yet have automated contribution tracking for the <strong>%s</strong> team. Sponsors, recruiters, and team reps can\'t see who is shipping tracked work — only a list of people who opted in.', 'wporg-5ftf' ),
								esc_html( $current_team->post_title )
							) );
							?>
						</p>
						<p>
							<strong><?php esc_html_e( 'You could lead this. Here is the path:', 'wporg-5ftf' ); ?></strong>
						</p>
					</div>

					<ol class="pledges-guide-steps">
						<li class="pledges-guide-step">
							<h3><?php esc_html_e( 'Review the team handbook', 'wporg-5ftf' ); ?></h3>
							<p><?php esc_html_e( 'Read it cover to cover. Understand what this team works on, how decisions get made, who the team reps are, and what existing recognition looks like.', 'wporg-5ftf' ); ?></p>
							<p><a class="pledges-guide-link" href="<?php echo esc_url( $handbook_url ); ?>"><?php echo esc_html( $handbook_label ); ?></a></p>
						</li>

						<li class="pledges-guide-step">
							<h3><?php esc_html_e( 'Join the team on Slack', 'wporg-5ftf' ); ?></h3>
							<p>
								<?php
								echo wp_kses_data( sprintf(
									/* translators: %s: team channel name e.g. #core */
									__( 'Most coordination happens in <code>#%s</code> on the Make WordPress Slack workspace.', 'wporg-5ftf' ),
									esc_html( $team_slug )
								) );
								?>
							</p>
							<p><a class="pledges-guide-link" href="https://make.wordpress.org/chat/"><?php esc_html_e( 'Get a Slack account', 'wporg-5ftf' ); ?></a></p>
						</li>

						<li class="pledges-guide-step">
							<h3><?php esc_html_e( 'Attend one or two team meetings', 'wporg-5ftf' ); ?></h3>
							<p><?php esc_html_e( 'Meeting times are in the handbook. Show up, introduce yourself, listen. Do not propose anything yet — just get a sense of the rhythm and the open work.', 'wporg-5ftf' ); ?></p>
						</li>

						<li class="pledges-guide-step">
							<h3><?php esc_html_e( 'Propose a metrics discussion', 'wporg-5ftf' ); ?></h3>
							<p><?php esc_html_e( 'Once you have earned standing in the team, propose a discussion (in a meeting or async) to define what counts as a tracked contribution. What types of work? What impact levels? Which signals should be tracked, and which intentionally ignored?', 'wporg-5ftf' ); ?></p>
						</li>

						<li class="pledges-guide-step">
							<h3><?php esc_html_e( 'Collect, store, and cache the agreed metrics', 'wporg-5ftf' ); ?></h3>
							<p><?php esc_html_e( 'Wire the agreed signals into the wp.org data pipeline alongside the existing Trac and GitHub aggregation. Add per-source ingest, indexed storage, and hour-cached aggregation matching the existing pattern.', 'wporg-5ftf' ); ?></p>
						</li>

						<li class="pledges-guide-step">
							<h3><?php esc_html_e( 'Wire this page up', 'wporg-5ftf' ); ?></h3>
							<p>
								<?php
								echo wp_kses_data( sprintf(
									/* translators: 1: file path, 2: constant name */
									__( 'Update <code>%1$s</code> to include the team and its new data source, then add the team\'s slug to <code>%2$s</code>. This page will switch from this guide to the contributor directory automatically.', 'wporg-5ftf' ),
									'inc/contribution-metrics.php',
									'TEAMS_WITH_DATA'
								) );
								?>
							</p>
						</li>
					</ol>

				</div>
			</article>

		</div>
	</div>
	<div style="display: none;"><div id="content"></div></div>
	<?php
	get_sidebar();
	get_footer();
	return;
}

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

// Sponsorship filter is URL-driven so the view is bookmarkable and survives
// time-window navigations (the window chips are full-page reloads). Default
// matches the historical JS default so existing links don't shift meaning.
$sponsorship_default = 'independent';
$sponsorship_allowed = array( 'all', 'independent', 'sponsored' );
// is_string() guard before sanitize_key(): a URL like ?sponsorship[]=foo makes
// $_GET['sponsorship'] an array, which would TypeError sanitize_key() on PHP 8
// and 500 the page.
$sponsorship_raw = isset( $_GET['sponsorship'] ) ? wp_unslash( $_GET['sponsorship'] ) : '';
$sponsorship     = is_string( $sponsorship_raw ) ? sanitize_key( $sponsorship_raw ) : '';
if ( ! in_array( $sponsorship, $sponsorship_allowed, true ) ) {
	$sponsorship = $sponsorship_default;
}

$total_count       = count( $contributors );
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

// Visible active count under the current sponsorship filter, so the initial
// render's "N active contributors" matches what the user actually sees and
// doesn't twitch when the JS recomputes after hydration.
$visible_active_count   = 0;
$visible_inactive_count = 0;
foreach ( $active_contributors as $c ) {
	$row_sponsorship = empty( $c['sponsored'] ) ? 'independent' : 'sponsored';
	if ( 'all' === $sponsorship || $row_sponsorship === $sponsorship ) {
		$visible_active_count++;
	}
}
foreach ( $inactive_contributors as $c ) {
	$row_sponsorship = empty( $c['sponsored'] ) ? 'independent' : 'sponsored';
	if ( 'all' === $sponsorship || $row_sponsorship === $sponsorship ) {
		$visible_inactive_count++;
	}
}

// Chip URL builder. home_url('/pledges/') already includes the team site path,
// so no REQUEST_URI concat needed. Each filter group's links carry the *other*
// groups' state forward so switching one dimension doesn't silently reset the
// others (see issue #374).
$pledges_url = home_url( '/pledges/' );
$default_w   = ContributionMetrics\WINDOW_DAYS_DEFAULT;

$build_pledges_url = function ( $window, $sponsorship_value ) use ( $pledges_url, $default_w, $show_inactive, $sponsorship_default ) {
	$args = array();
	if ( $window !== $default_w ) {
		$args['window'] = $window;
	}
	if ( $sponsorship_value !== $sponsorship_default ) {
		$args['sponsorship'] = $sponsorship_value;
	}
	if ( $show_inactive ) {
		$args['show_inactive'] = 1;
	}
	return $args ? add_query_arg( $args, $pledges_url ) : $pledges_url;
};

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
								<?php esc_html_e( 'Contributors are ranked by recent contribution impact, not by pledged hours. Activity is tracked from release credits in Trac and GitHub work (merged PRs, pushes, closed issues), sorted into high, medium, and low impact. Low-impact contributions (typo fixes, whitespace) don\'t factor into the ranking. Inactive contributors gradually drop down the list.', 'wporg-5ftf' ); ?>
							</p>
						</div>
						<button type="button" class="pledges-howitworks-dismiss" aria-label="<?php esc_attr_e( 'Dismiss', 'wporg-5ftf' ); ?>">&times;</button>
					</div>

					<div class="pledges-health" role="status">
						<span class="pledges-health-num"><?php echo esc_html( number_format_i18n( $total_count ) ); ?></span>
						<span class="pledges-health-text">
							<?php
							// $total_count is the static opt-in roster (active + inactive); the window
							// scopes the rank line below, not this banner. Don't conflate the two.
							echo wp_kses_data( sprintf(
								/* translators: %s: team name */
								__( 'contributors to %s.', 'wporg-5ftf' ),
								'<strong>' . esc_html( $current_team->post_title ) . '</strong>'
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
								<a class="pledges-chip<?php echo 30 === $window_days ? ' is-on' : ''; ?>"<?php echo 30 === $window_days ? ' aria-current="true"' : ''; ?> data-filter="window" data-value="30" href="<?php echo esc_url( $build_pledges_url( 30, $sponsorship ) ); ?>"><?php esc_html_e( '30 days', 'wporg-5ftf' ); ?></a>
								<a class="pledges-chip<?php echo 90 === $window_days ? ' is-on' : ''; ?>"<?php echo 90 === $window_days ? ' aria-current="true"' : ''; ?> data-filter="window" data-value="90" href="<?php echo esc_url( $build_pledges_url( 90, $sponsorship ) ); ?>"><?php esc_html_e( '90 days', 'wporg-5ftf' ); ?></a>
								<a class="pledges-chip<?php echo 180 === $window_days ? ' is-on' : ''; ?>"<?php echo 180 === $window_days ? ' aria-current="true"' : ''; ?> data-filter="window" data-value="180" href="<?php echo esc_url( $build_pledges_url( 180, $sponsorship ) ); ?>"><?php esc_html_e( '6 months', 'wporg-5ftf' ); ?></a>
							</div>
							<div class="pledges-filter-group">
								<span class="pledges-filter-label"><?php esc_html_e( 'Sponsorship', 'wporg-5ftf' ); ?></span>
								<a class="pledges-chip<?php echo 'all' === $sponsorship ? ' is-on' : ''; ?>"<?php echo 'all' === $sponsorship ? ' aria-current="true"' : ''; ?> data-filter="sponsorship" data-value="all" href="<?php echo esc_url( $build_pledges_url( $window_days, 'all' ) ); ?>"><?php esc_html_e( 'All', 'wporg-5ftf' ); ?></a>
								<a class="pledges-chip<?php echo 'independent' === $sponsorship ? ' is-on' : ''; ?>"<?php echo 'independent' === $sponsorship ? ' aria-current="true"' : ''; ?> data-filter="sponsorship" data-value="independent" href="<?php echo esc_url( $build_pledges_url( $window_days, 'independent' ) ); ?>"><?php esc_html_e( 'Independent', 'wporg-5ftf' ); ?></a>
								<a class="pledges-chip<?php echo 'sponsored' === $sponsorship ? ' is-on' : ''; ?>"<?php echo 'sponsored' === $sponsorship ? ' aria-current="true"' : ''; ?> data-filter="sponsorship" data-value="sponsored" href="<?php echo esc_url( $build_pledges_url( $window_days, 'sponsored' ) ); ?>"><?php esc_html_e( 'Sponsored', 'wporg-5ftf' ); ?></a>
							</div>
						</div>
					</div>

					<p class="pledges-sort-label">
						<strong>
							<?php
							echo esc_html( sprintf(
								/* translators: %d: window in days */
								__( 'Ranked by impact, last %d days.', 'wporg-5ftf' ),
								$window_days
							) );
							?>
						</strong>
						<?php
						// The result phrase is a JS-controlled template so the singular/plural
						// form follows the visible-card count after client-side filtering, instead
						// of being baked at render time and drifting (e.g. "1 active contributors").
						?>
						<span class="pledges-result-count" aria-live="polite">
							<span id="pledges-result-count"><?php echo esc_html( $visible_active_count ); ?></span>
							<span
								id="pledges-result-phrase"
								data-singular="<?php echo esc_attr__( 'active contributor (of %d total)', 'wporg-5ftf' ); ?>"
								data-plural="<?php echo esc_attr__( 'active contributors (of %d total)', 'wporg-5ftf' ); ?>"
								data-total="<?php echo esc_attr( $total_count ); ?>"
							><?php
								echo esc_html( sprintf(
									/* translators: %d: total count */
									_n( 'active contributor (of %d total)', 'active contributors (of %d total)', $visible_active_count, 'wporg-5ftf' ),
									$total_count
								) );
								?></span>
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

						if ( $show_inactive && $inactive_contributors ) :
							// Label reflects the *visible* inactive count under the current
							// sponsorship filter, otherwise "10 contributors with no tracked
							// contributions" sits above 2 cards when the filter hides the rest.
							$inactive_label = sprintf(
								/* translators: 1: count, 2: window in days */
								_n( '%1$d contributor with no tracked contributions in the last %2$d days', '%1$d contributors with no tracked contributions in the last %2$d days', $visible_inactive_count, 'wporg-5ftf' ),
								$visible_inactive_count,
								$window_days
							);
							// Hide the divider when the sponsorship filter has emptied its
							// section so JS-disabled visitors don't see an orphan label above
							// nothing. JS apply() will toggle this back on if the filter changes.
							?>
							<div class="pledges-inactive-divider"<?php echo 0 === $visible_inactive_count ? ' hidden' : ''; ?>>
								<span><?php echo esc_html( $inactive_label ); ?></span>
							</div>
							<?php
							foreach ( $inactive_contributors as $contributor ) {
								$rank++;
								$contributor['_rank'] = $rank;
								require __DIR__ . '/content-pledge.php';
							}
						endif;
						?>
					</div>

					<?php
					// Pre-show the empty state when the sponsorship filter has hidden every
					// card the server would have rendered. Otherwise a JS-disabled visitor
					// with ?sponsorship=sponsored on an all-independent team would see a
					// blank grid and no explanation. JS apply() will hide this again if a
					// later filter change brings cards back.
					$visible_inactive_for_empty = $show_inactive ? $visible_inactive_count : 0;
					$empty_initially_visible    = 0 === ( $visible_active_count + $visible_inactive_for_empty );
					?>
					<div class="pledges-empty" id="pledges-empty"<?php echo $empty_initially_visible ? '' : ' hidden'; ?>>
						<p><?php esc_html_e( 'No contributors match the selected filters.', 'wporg-5ftf' ); ?></p>
						<a class="pledges-empty-reset" href="<?php echo esc_url( $build_pledges_url( $window_days, 'all' ) ); ?>"><?php esc_html_e( 'Clear filters', 'wporg-5ftf' ); ?></a>
					</div>

					<?php
					// Empty-state companion CTA: only render when the empty state itself is
					// showing (visible_active_count === 0 && !show_inactive). Without the
					// visible_active_count gate this would also render alongside the
					// standalone inactive toggle below when the grid has visible cards,
					// producing two "Show inactive" CTAs on the same page.
					$show_empty_extra = $inactive_contributors && ! $show_inactive && 0 === $visible_active_count;
					?>
					<?php if ( $show_empty_extra ) : ?>
						<p class="pledges-empty-extra">
							<a href="<?php echo esc_url( add_query_arg( 'show_inactive', '1' ) ); ?>">
								<?php
								echo esc_html( sprintf(
									/* translators: 1: count, 2: window in days */
									_n( 'Show %1$d contributor with no tracked contributions in the last %2$d days', 'Show %1$d contributors with no tracked contributions in the last %2$d days', $inactive_count, 'wporg-5ftf' ),
									$inactive_count,
									$window_days
								) );
								?>
							</a>
						</p>
					<?php endif; ?>

					<?php
					// Standalone inactive toggle: only renders when there's a visible active
					// section to anchor it to (or when inactive is already shown, so the user
					// can hide it). When the visible active count is 0 the empty state above
					// already carries the "Show inactive" suggestion via pledges-empty-extra,
					// so showing the standalone toggle below would just duplicate the CTA.
					$show_standalone_toggle = $inactive_contributors && ( $visible_active_count > 0 || $show_inactive );
					?>
					<?php if ( $show_standalone_toggle ) : ?>
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
										_n( 'Show %1$d contributor with no tracked contributions in the last %2$d days', 'Show %1$d contributors with no tracked contributions in the last %2$d days', $inactive_count, 'wporg-5ftf' ),
										$inactive_count,
										$window_days
									) );
									?>
								</a>
							<?php endif; ?>
						</p>
					<?php endif; ?>

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
