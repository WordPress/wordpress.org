<?php
/**
 * Render the release commits block.
 *
 * @package wporg-plugins
 */

use WordPressdotorg\Plugin_Directory\Readme\Validator as Readme_Validator;
use function WordPressdotorg\Plugin_Directory\Theme\{get_plugin_slug, get_revision_log_link};

if ( ! $block->context['postId'] ) {
	return;
}

$commits = get_post_meta( $block->context['postId'], 'release_commit_log', true );

if ( empty( $commits ) ) {
	return '<p>' . __( 'No commits found.', 'wporg-plugins' ) . '</p>';
}

// Newest commits first.
usort(
	$commits,
	function ( $a, $b ) {
		return $b['date'] <=> $a['date'];
	}
);

$maximum_commits = 5;
$sliced_commits  = array_slice( $commits, 0, $maximum_commits );

?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php

	$heading = sprintf(
		'<!-- wp:heading {"level":4,"fontSize":"normal", "style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0"}}}} -->
			<h4 class="wp-block-heading has-normal-font-size" style="margin-right:0;margin-bottom:0;margin-left:0">%s</h4>
		<!-- /wp:heading -->',
		esc_attr__( 'Commits', 'wporg-plugins' )
	);

	echo do_blocks( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	?>

	<ul class="wp-block-wporg-release-commit-list">
		<?php
		foreach ( $sliced_commits as $commit ) {
			$user = get_user_by( 'login', $commit['author'] );
			?>
			<li>
				<span>
				<?php
				printf(
					'<a href="%1$s">%2$s</a>',
					esc_url(
						sprintf(
							'https://plugins.trac.wordpress.org/changeset/%1$s/%2$s/trunk',
							$commit['revision'],
							get_plugin_slug()
						)
					),
					esc_html( wp_trim_words( $commit['message'], 7 ) )
				);
				?>
				</span>

				<span class="wp-block-wporg-release-commit-by-line">
				<?php
					$user = get_user_by( 'login', $commit['author'] );
					printf(
						/* translators: 1: time since commit, 2: author name */
						__( '%1$s ago by %2$s', 'wporg-plugins' ),
						esc_attr( human_time_diff( $commit['date'], current_time( 'timestamp' ) ) ),
						sprintf(
							'<a href="%1$s">%2$s</a>',
							esc_url( 'https://profiles.wordpress.org/' . $user->user_nicename ),
							esc_html( $user->display_name )
						)
					);
				?>
				</span>
	
			</li>
		<?php } ?>
		<?php if ( count( $commits ) > $maximum_commits ) : ?>
			<li>
				<a href="<?php echo esc_url( get_revision_log_link( $commits ) ); ?>">
					<?php
						/* translators: %s: number of commits */
						printf( esc_html__( 'View all %s commits', 'wporg-plugins' ), count( $commits ) )
					?>
				</a>
			</li>
		<?php endif; ?>
	</ul>
</div>
