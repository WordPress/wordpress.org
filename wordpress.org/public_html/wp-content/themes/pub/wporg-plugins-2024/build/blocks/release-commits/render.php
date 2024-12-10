<?php
/**
 * Render the release commits block.
 *
 * @package wporg-plugins
 */

use WordPressdotorg\Plugin_Directory\Readme\Validator as Readme_Validator;

if ( ! current_user_can( 'plugin_admin_edit', $post ) ) {
	return;
}

if ( ! $block->context['postId'] ) {
	return;
}

$commits = get_post_meta( $block->context['postId'], 'release_commit_log', true );

if ( empty( $commits ) ) {
	return '<p>' . __( 'No commits found.', 'wporg-plugins' ) . '</p>';
}

/**
 * Count the number of files edited, added, and deleted in the given commits.
 *
 * @param array $commits Array of commits.
 * @return array Associative array with keys 'edited', 'added', and 'deleted'.
 */
function count_file_changes( $commits ) {
	$file_actions = array();

	ksort( $commits );

	foreach ( $commits as $commit ) {
		if ( ! isset( $commit['actions'] ) || ! is_array( $commit['actions'] ) ) {
			continue;
		}

		foreach ( $commit['actions'] as $file_path => $action ) {
			$file_actions[ $file_path ] = $action;
		}
	}

	$edited_files  = array();
	$added_files   = array();
	$deleted_files = array();

	foreach ( $file_actions as $file_path => $action ) {
		switch ( $action ) {
			case 'M':
				$edited_files[ $file_path ] = true;
				break;
			case 'A':
				$added_files[ $file_path ] = true;
				break;
			case 'D':
				$deleted_files[ $file_path ] = true;
				break;
		}
	}

	return array(
		'edited'  => count( $edited_files ),
		'added'   => count( $added_files ),
		'deleted' => count( $deleted_files ),
	);
}

/**
 * Generate a summary of the changes made in the given commits.
 *
 * @param array $change_counts Associative array with keys 'edited', 'added', and 'deleted'.
 * @return string Summary of the changes.
 */
function generate_summary( $change_counts ) {
	$parts = array();

	if ( $change_counts['added'] > 0 ) {
		$parts[] = sprintf( '%d added', $change_counts['added'] );
	}

	if ( $change_counts['deleted'] > 0 ) {
		$parts[] = sprintf( '%d deleted', $change_counts['deleted'] );
	}

	if ( $change_counts['edited'] > 0 ) {
		$parts[] = sprintf( '%d edited', $change_counts['edited'] );
	}

	if ( empty( $parts ) ) {
		return '';
	}

	return implode( ', ', $parts );
}

?>

<div <?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>>
	<?php

	$heading = sprintf(
		'<!-- wp:heading {"level":4,"fontSize":"normal", "style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0"}}}} -->
			<h4 class="wp-block-heading has-normal-font-size" style="margin-right:0;margin-bottom:0;margin-left:0">%s</h4>
		<!-- /wp:heading -->',
		esc_attr__( 'Commits', 'wporg-plugins' )
	);

	$change_counts = count_file_changes( $commits );
	$summary       = generate_summary( $change_counts );

	$subheading = sprintf(
		'<!-- wp:heading {"level":5,"style":{"elements":{"link":{"color":{"text":"var:preset|color|charcoal-4"}}},"spacing":{"margin":{"top":"0","bottom":"var:preset|spacing|10"}}},"textColor":"charcoal-4","fontSize":"extra-small"} -->
		<h5 class="wp-block-heading has-charcoal-4-color has-text-color has-link-color has-extra-small-font-size" style="margin-top:0;margin-bottom:var(--wp--preset--spacing--10)">%s</h5>
		<!-- /wp:heading -->',
		$summary
	);

	echo do_blocks( $heading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	echo do_blocks( $subheading ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped

	?>

	<ul>
		<?php
		foreach ( $commits as $commit ) {
			$user = get_user_by( 'login', $commit['author'] );
			?>
			<li>
				<span class="wp-block-wporg-release-commit-author">
					<?php echo get_avatar( $user->ID, 20 ); ?>
					<?php echo esc_html( $user->display_name ); ?>
				</span>
	
				<span>
				<?php
					$release_post = get_post( $block->context['postId'] );
					$parent_post  = get_post( $release_post->post_parent );

				if ( $parent_post ) {
					printf(
						'<a href="%1$s">%2$s</a>',
						esc_url(
							sprintf(
								'https://plugins.trac.wordpress.org/changeset/%1$s/%2$s/trunk',
								$commit['revision'],
								$parent_post->post_name
							)
						),
						esc_html( $commit['message'] )
					);
				}

				?>
				</span>
			</li>	
		<?php } ?>
	</ul>
</div>
