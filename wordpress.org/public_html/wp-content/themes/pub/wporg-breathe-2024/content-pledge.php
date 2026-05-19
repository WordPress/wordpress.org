<?php

namespace WordPressdotorg\Make\Breathe_2024;

defined( 'WPINC' ) || die();

/**
 * Note: There will be a large number of contributors on every page, so don't call any expensive functions like
 * `get_avatar_url( $user_id )` here (which creates a database lookup for the email address). Instead, add the
 * data to `get_team_contributors()` in a performant way.
 *
 * @var array $contributor
 */

$is_sponsored = ! empty( $contributor['sponsored'] );
$status_class = $is_sponsored ? 'sponsored' : 'independent';
$status_label = $is_sponsored ? __( 'Sponsored', 'wporg-5ftf' ) : __( 'Independent', 'wporg-5ftf' );

$active_class  = $contributor['_metrics_active_class'] ?? 'active-stale';
$active_bucket = $contributor['_metrics_active_bucket'] ?? __( 'over 6 months ago', 'wporg-5ftf' );

$weighted    = (int) ( $contributor['_metrics_weighted'] ?? 0 );
$high        = (int) ( $contributor['_metrics_high'] ?? 0 );
$medium      = (int) ( $contributor['_metrics_medium'] ?? 0 );
$top_repos   = $contributor['_metrics_top_repos'] ?? array();
$window_days = (int) ( $contributor['_metrics_window_days'] ?? ContributionMetrics\WINDOW_DAYS_DEFAULT );

// Data attributes used by client-side filter. `data-active` lets the JS exclude
// inactive cards from the "active contributors" result count and toggle the
// inactive divider when filters empty its section.
$row_attrs = array(
	'data-rank'        => (int) ( $contributor['_rank'] ?? 0 ),
	'data-weighted'    => $weighted,
	'data-raw'         => $high + $medium,
	'data-name'        => strtolower( $contributor['name'] ?? '' ),
	'data-sponsorship' => $status_class,
	'data-active'      => $weighted > 0 ? '1' : '0',
);

$attrs_html = '';
foreach ( $row_attrs as $k => $v ) {
	$attrs_html .= sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
}

// Sponsorship filter is URL-driven (see ?sponsorship= handling in page-pledges.php).
// Pre-hide non-matching cards so the browser paints the filtered view immediately;
// otherwise the footer-loaded JS hides them after first paint, producing a visible
// flash of every card. $sponsorship is inherited from the parent template scope via
// `require`; default to 'independent' if this file is ever included standalone.
$active_sponsorship  = $sponsorship ?? 'independent';
$is_initially_hidden = 'all' !== $active_sponsorship && $active_sponsorship !== $status_class;

?>

<article class="pledges-card"<?php echo $is_initially_hidden ? ' hidden' : ''; ?> <?php echo $attrs_html; // phpcs:ignore WordPress.Security.EscapeOutput ?>>
	<span class="pledges-card-rank" aria-hidden="true"><?php echo esc_html( str_pad( (string) $contributor['_rank'], 2, '0', STR_PAD_LEFT ) ); ?></span>

	<div class="pledges-card-avatar">
		<?php echo wp_kses_post( get_avatar( $contributor['email'], 56 ) ); ?>
	</div>

	<div class="pledges-card-body">
		<div class="pledges-card-identity">
			<a class="pledges-card-name" href="<?php echo esc_url( 'https://profiles.wordpress.org/' . $contributor['username'] . '/' ); ?>">
				<?php echo esc_html( $contributor['name'] ); ?>
			</a>
			<span class="pledges-card-handle">@<?php echo esc_html( $contributor['username'] ); ?></span>
			<span class="pledges-card-status pledges-card-status-<?php echo esc_attr( $status_class ); ?>">
				<?php
				if ( $is_sponsored && ! empty( $contributor['pledge_url'] ) ) {
					printf(
						'<a href="%s">%s</a>',
						esc_url( $contributor['pledge_url'] ),
						esc_html( $status_label )
					);
				} else {
					echo esc_html( $status_label );
				}
				?>
			</span>
			<span class="pledges-card-active pledges-card-active-<?php echo esc_attr( $active_class ); ?>">
				<?php
				echo esc_html( sprintf(
					/* translators: %s: relative time bucket, e.g. "3 days ago" */
					__( 'active %s', 'wporg-5ftf' ),
					$active_bucket
				) );
				?>
			</span>
		</div>

		<p class="pledges-card-summary">
			<?php
			if ( $weighted > 0 ) {
				// Build summary from real signals: count breakdown + top repos.
				$parts = array();
				if ( $high > 0 ) {
					$parts[] = sprintf(
						/* translators: 1: <strong> tag, 2: number of high-impact contributions, 3: </strong> tag */
						_n( '%1$s%2$d high-impact%3$s contribution', '%1$s%2$d high-impact%3$s contributions', $high, 'wporg-5ftf' ),
						'<strong>',
						$high,
						'</strong>'
					);
				}
				if ( $medium > 0 ) {
					$parts[] = sprintf(
						/* translators: 1: <strong> tag, 2: number of medium-impact contributions, 3: </strong> tag */
						_n( '%1$s%2$d medium-impact%3$s contribution', '%1$s%2$d medium-impact%3$s contributions', $medium, 'wporg-5ftf' ),
						'<strong>',
						$medium,
						'</strong>'
					);
				}
				$summary = implode( ' &middot; ', $parts );

				if ( ! empty( $top_repos ) ) {
					$repo_short = array_map(
						function ( $r ) {
							$slash = strrpos( $r, '/' );
							return false !== $slash ? substr( $r, $slash + 1 ) : $r;
						},
						array_slice( $top_repos, 0, 2 )
					);
					$repo_html  = array_map(
						function ( $r ) {
							return '<strong>' . esc_html( $r ) . '</strong>';
						},
						$repo_short
					);
					$summary   .= ' &middot; ' . sprintf(
						/* translators: %s: comma-separated list of GitHub repo short names */
						__( 'in %s', 'wporg-5ftf' ),
						implode( ', ', $repo_html )
					);
				}

				$summary .= ' ' . sprintf(
					/* translators: %d: window in days */
					esc_html__( 'in the last %d days.', 'wporg-5ftf' ),
					$window_days
				);

				echo wp_kses_data( $summary );
			} else {
				echo esc_html( sprintf(
					/* translators: %d: window in days */
					__( 'No tracked contributions in the last %d days.', 'wporg-5ftf' ),
					$window_days
				) );
			}
			?>
		</p>
	</div>

	<aside class="pledges-card-meta">
		<span class="pledges-card-weight">
			<strong><?php echo esc_html( number_format_i18n( $weighted ) ); ?></strong>
			<span class="pledges-card-weight-label"><?php esc_html_e( 'impact', 'wporg-5ftf' ); ?></span>
		</span>
	</aside>
</article>
