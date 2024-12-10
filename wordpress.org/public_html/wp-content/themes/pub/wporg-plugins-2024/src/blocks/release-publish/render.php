<?php
/**
 * Renders the Release Details Form block.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

use function WordPressdotorg\Plugin_Directory\Theme\{get_blueprint_url, get_latest_release, get_plugin_slug, get_revision_changeset_link};

// Ensure the block context has a valid post ID.
if ( empty( $block->context['postId'] ) ) {
	return;
}

$release_post = get_post( $block->context['postId'] );

// Bail if the release post does not exist.
if ( ! $release_post ) {
	return;
}

$current_version = get_post_meta( $block->context['postId'], 'release_version', true );
$tested_up_to    = get_post_meta( $block->context['postId'], 'release_tested', true );

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

$latest_release    = get_latest_release( $release_post->post_parent );
$last_version      = get_post_meta( $latest_release->ID, 'release_version', true );
$version_pass      = version_compare( $current_version, $last_version, '>' );
$tested_up_to_pass = has_recently_been_tested( $tested_up_to );

$plugin_slug  = get_plugin_slug();
$form_context = array(
	'pluginSlug' => $plugin_slug,
	'nonce'      => wp_create_nonce( 'wp_rest' ),
	'apiURL'     => esc_url( rest_url( 'plugins/v2/plugin/' . $plugin_slug . '/publish' ) ),
);

/**
 * Create initial state for the async-action-block.
 */
wp_interactivity_state(
	'async-action-block',
	array(
		'hasConfirmed' => false,
		'isPublishing' => false,
		'isPublished'  => false,
		'hasError'     => false,
		'errorMessage' => '',
	)
);

?>

<div 
	data-wp-interactive="async-action-block" 	
	<?php echo wp_kses_data( get_block_wrapper_attributes() ); ?> 
	<?php echo wp_interactivity_data_wp_context( $form_context ); ?>
	>
	<div data-wp-bind--hidden="!state.isDefaultState">
		<?php
			echo do_blocks(
				sprintf(
					'<!-- wp:paragraph -->
					<p>%s</p>
					<!-- /wp:paragraph -->',
					__( 'Before releasing your plugin, make sure everything is up-to-date and ready for your users:', 'wporg-plugins' )
				),
			);
			?>

		<form data-wp-on-async--submit="actions.handleSubmit">
			<?php
				echo do_blocks(
					sprintf(
						'<!-- wp:heading {"level":4,"fontSize":"normal", "style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0"}}}} -->
							<h4 class="wp-block-heading has-normal-font-size" style="margin-right:0;margin-bottom:0;margin-left:0">%s</h4>
						<!-- /wp:heading -->',
						esc_html__( 'Checklist', 'wporg-plugins' )
					)
				)
				?>
			<ul>
				<?php echo get_view_diff_check_item( $release_post ); ?>   
				<?php echo get_changelog_check_item(); ?>   
				<?php echo get_tested_up_to_check_item( $tested_up_to_pass, $tested_up_to ); ?>     
				<?php echo get_version_number_check_item( $version_pass, $current_version ); ?>
			</ul>

			<div class="wp-block-wporg-release-publish-user-confirm">
				<label for="confirm-release">
					<input 
					data-wp-bind--checked="state.userHasConfirmed" 
					data-wp-on-async--click="actions.handleReleaseConfirm"
					id="confirm-release" type="checkbox" required>
					<?php esc_html_e( 'I have completed the checklist and I\'m ready to publish this release.', 'wporg-plugins' ); ?>
				</label>
				
			</div>

			<div class="wp-block-group wp-block-wporg-release-publish-actions">
				<div class="wp-block-button is-small">
					<button 
						type="submit"
						class="wp-block-button__link wp-element-button"
					>
						<?php
						printf(
							/* translators: %s is the plugin version number. */
							__( 'Publish v.%s', 'wporg-plugins' ),
							esc_html( $current_version )
						);
						?>
					</button>
				</div>

				<div class="wp-block-button is-small is-style-text">
					<button 
						type="button" 
						class="wp-block-button__link wp-element-button"
						data-wp-on-async--click="actions.handleBackClick"
					>
						<?php esc_html_e( 'Cancel', 'wporg-plugins' ); ?>
					</button>
				</div>
			</div>
		</form>
	</div>

	<div data-wp-bind--hidden="!state.isPublishingState">
		<div class="wp-block-wporg-release-publish-spinner">
				<span class="wporg-spinner"></span>
				<?php esc_html_e( 'Publishing your release...', 'wporg-plugins' ); ?>
			</div>
	</div>


	<div data-wp-bind--hidden="!state.isPublishedState">
	<p><?php
		printf(
			/* translators: %s is the plugin version number. */
			__( 'Your release v.%s is now live!', 'wporg-plugins' ),
			esc_html( $current_version )
		);
		?>
	</p>

		<div>
			<div class="wp-block-button is-small">
				<a 
					class="wp-block-button__link wp-element-button"
					data-wp-on-async--click="actions.handlePageReload"
				>
					<?php esc_html_e( 'View releases', 'wporg-plugins' ); ?>
				</a>
			</div>
		</div>
	</div>

	<div data-wp-bind--hidden="!state.hasError">
		<?php
			echo do_blocks(
				'<!-- wp:wporg/notice {"type":"warning"} -->
				<div class="wp-block-wporg-notice is-warning-notice">
				<div class="wp-block-wporg-notice__icon"></div>
				<div class="wp-block-wporg-notice__content"><p data-wp-text="state.errorMessage"></p></div>
				</div>
				<!-- /wp:wporg/notice -->'
			);
			?>
	</div>
</div>
