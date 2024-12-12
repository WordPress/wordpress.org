<?php
/**
 * Block Name: Release Publish
 * Description: A block to display release publish view.
 *
 * @package wporg
 */

namespace WordPressdotorg\Theme\Plugins_2024\ReleasePublish;

use function WordPressdotorg\Plugin_Directory\Theme\{get_blueprint_url, get_latest_release, get_plugin_slug, get_plugin, get_revision_changeset_link};

add_action( 'init', __NAMESPACE__ . '\init' );

/**
 * Registers the block using the metadata loaded from the `block.json` file.
 * Behind the scenes, it registers also all assets so they can be enqueued
 * through the block editor in the corresponding context.
 *
 * @see https://developer.wordpress.org/reference/functions/register_block_type/
 */
function init() {
	register_block_type( __DIR__ . '/../../../build/blocks/release-publish' );
}


/**
 * Returns whether the tested_up_to value is recent.
 *
 * @param string $tested_up_to The tested up to value.
 *
 * @return bool Whether the tested up to value is recent.
 */
function has_recently_been_tested( $tested_up_to ) {
	global $wp_version;

	// If the tested up to value is empty, it's not recent.
	if ( empty( $tested_up_to ) ) {
		return false;
	}

	$latest_release = $wp_version;

	if ( defined( 'WP_CORE_STABLE_BRANCH' ) ) {
		$latest_release = WP_CORE_STABLE_BRANCH;
	}

	$tested_major = (int) explode( '.', $tested_up_to )[0];
	$latest_major = (int) explode( '.', $latest_release )[0];

	return $tested_major >= $latest_major;
}

/**
 * Generate the HTML for a release item content block.
 *
 * @param string $label   The label for the item.
 * @param string $content The additional content or description.
 * @return string The formatted HTML content for the release item.
 */
function get_release_item_content( $label, $content ) {
	return sprintf(
		'<div><strong>%1$s</strong><div>%2$s</div></div>',
		esc_html( $label ),
		wp_kses_post( $content )
	);
}

/**
 * Generate a release check item block with a specific status.
 *
 * @param string $status  The status of the check item ('success' or 'error').
 * @param string $content The content to display inside the block.
 * @return string The formatted block content.
 */
function get_release_check_item( $status, $content ) {
	return do_blocks(
		sprintf(
			'<!-- wp:wporg/release-result-item {"status":"%1$s"} -->%2$s<!-- /wp:wporg/release-result-item -->',
			esc_attr( $status ),
			$content
		)
	);
}

/**
 * Generate the block content for the version number check item.
 *
 * @return string The block content.
 */
function get_changelog_check_item() {
	$label     = __( 'Update your changelog with key changes.', 'wporg-plugins' );
	$info_text = sprintf(
		'<a target="_blank" href="%s">Learn more</a> about writing useful changelogs.',
		esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/#what-should-be-in-my-changelog' ),
	);

	$content = get_release_item_content( $label, $info_text );

	return get_release_check_item( 'default', $content );
}

/**
 * Generate the block content for the version number check item.
 *
 * @param bool $post Release post.
 *
 * @return string The block content.
 */
function get_view_diff_check_item( $post ) {
	$label     = __( 'Review your changes.', 'wporg-plugins' );
	$commits   = get_post_meta( $post->ID, 'release_commit_log', true );
	$info_text = sprintf(
		/* translators: %s: URL to the plugin in the Plugin Directory */
		__( 'Double-check <a target="_blank" href="%s">your changeset</a> before publishing.', 'wporg-plugins' ),
		esc_url( get_revision_changeset_link( $commits ) )
	);
	$content = get_release_item_content( $label, $info_text );

	return get_release_check_item( 'default', $content );
}

/**
 * Generate the block content for the version number check item.
 *
 * @param bool   $verdict Whether the version number is valid.
 * @param string $value The current version number.
 *
 * @return string The block content.
 */
function get_version_number_check_item( $verdict, $value ) {
	$label = __( 'Increment your version number.', 'wporg-plugins' );

	$info_text = sprintf(
		/* translators: %s: The current version number */
		__( 'New version: %s', 'wporg-plugins' ),
		'<code>' . $value . '</code>',
	);

	$status = '';

	if ( ! $verdict ) {
		$status    = 'error';
		$info_text = sprintf(
			/* translators: %s: The current version number */
			__( 'Your plugin\'s version number %s must be incremented before you can publish.', 'wporg-plugins' ),
			'<code>' . $value . '</code>',
		);
	}

	$content = get_release_item_content( $label, $info_text );

	return get_release_check_item( $status, $content );
}

/**
 * Generate the block content for the "Tested up to" check item.
 *
 * @param bool   $verdict Whether the tested up to value is recent.
 * @param string $value The tested up to value.
 *
 * @return string The block content.
 */
function get_tested_up_to_check_item( $verdict, $value ) {
	$label  = __(
		'Test your plugin with the latest version of WordPress.',
		'wporg-plugins'
	);
	$status = '';

	$info_text = sprintf(
		/* translators: %s: The Tested Up to value */
		__( 'Tested up to: %s', 'wporg-plugins' ),
		'<code>' . $value . '</code>',
	);

	if ( empty( $value ) ) {
		$value     = __( 'Unknown', 'wporg-plugins' );
		$status    = 'error';
		$info_text = __( 'We weren\'t able to determine your "Tested up to" value.', 'wporg-plugins' );
	} elseif ( ! $verdict ) {
		$status    = 'warning';
		$info_text = sprintf(
			/* translators: %s: URL to the plugin in the Plugin Directory */
			__( 'Tested up to is %1$s. <a target="_blank" href="%2$s">Test it now in Playground</a> and update your <code>readme.txt</code>. ', 'wporg-plugins' ),
			'<code>' . $value . '</code>',
			esc_url(
				get_blueprint_url(
					sprintf(
						'https://downloads.wordpress.org/plugin/%s.zip',
						get_plugin_slug(),
					)
				)
			)
		);
	}

	$content = get_release_item_content( $label, $info_text );

	return get_release_check_item( $status, $content );
}
