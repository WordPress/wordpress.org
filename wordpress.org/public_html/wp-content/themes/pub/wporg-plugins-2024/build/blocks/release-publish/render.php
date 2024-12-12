<?php
/**
 * Renders the Release Details Form block.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

use WordPressdotorg\Plugin_Directory\Template;
use function WordPressdotorg\Plugin_Directory\Theme\{get_latest_release, get_plugin, get_plugin_slug, user_can_edit_plugin};
use function WordPressdotorg\Theme\Plugins_2024\ReleasePublish\{has_recently_been_tested, get_view_diff_check_item, get_changelog_check_item, get_tested_up_to_check_item, get_version_number_check_item};

if ( ! user_can_edit_plugin() ) {
	return;
}

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

$latest_release    = get_latest_release( $release_post->post_parent );
$last_version      = get_post_meta( $latest_release->ID, 'release_version', true );
$version_pass      = version_compare( $current_version, $last_version, '>' );
$tested_up_to_pass = has_recently_been_tested( $tested_up_to );

$plugin_slug  = get_plugin_slug();
$form_context = array(
	'pluginSlug'          => $plugin_slug,
	'nonce'               => wp_create_nonce( 'wp_rest' ),
	'apiURL'              => esc_url( rest_url( 'plugins/v2/plugin/' . $plugin_slug . '/publish' ) ),
	'genericErrorMessage' => __( 'An error occurred while publishing the release.', 'wporg-plugins' ),
);

/**
 * Create initial state for the wporg/publish-draft.
 */
wp_interactivity_state(
	'wporg/publish-draft',
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
	data-wp-interactive="wporg/publish-draft" 	
	<?php echo wp_kses_data( get_block_wrapper_attributes() ); ?> 
	<?php echo wp_interactivity_data_wp_context( $form_context ); ?>
	>
	<div data-wp-bind--hidden="!state.isDefaultState">
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
		<p>
			<?php
			printf(
				/* translators: %s is the plugin version number. */
				esc_html__( 'Great news! Version %s of your plugin is now live!', 'wporg-plugins' ),
				'<code>' . esc_html( $current_version ) . '</code>'
			);
			?>
		</p>
		<p>
			<?php esc_html_e( 'To make the most of your release, consider the following:', 'wporg-plugins' ); ?>
			<ul>
			<li>
				<strong>
					<a target="_blank" href="https://developer.wordpress.org/plugins/wordpress-org/previews-and-blueprints/">
						<?php esc_html_e( 'Add a blueprint', 'wporg-plugins' ); ?>
					</a>
				</strong>: 
				<?php esc_html_e( 'Help users try out your plugin easily.', 'wporg-plugins' ); ?>
			</li>
			<li>
				<strong>
					<?php esc_html_e( "Review your plugin's page", 'wporg-plugins' ); ?>
				</strong>: 
				<?php esc_html_e( 'Ensure the information is clear and effective.', 'wporg-plugins' ); ?>
			</li>
				<li>
					<?php
					printf(
						/* translators: 1: Support forum URL, 2: Plugin reviews URL */
						__( '<strong>Engage with your audience</strong>: Monitor your <a target="_blank" href="%1$s">support forum</a> and <a target="_blank" href="%2$s">plugin reviews</a> for feedback.', 'wporg-plugins' ),
						esc_url( Template::get_support_url( get_plugin() ) ),
						esc_url( 'https://wordpress.org/support/plugin/' . $plugin_slug . '/reviews/' )
					);
					?>
				</li>
			</ul>
			<?php

			printf(
				/* translators: 1: Plugin Developer FAQ URL, 2: Slack channel URL */
				__( 'Have any questions? Check out the <a target="_blank" href="%1$s">Plugin Developer FAQ</a> or join the <a target="_blank" href="%2$s">#pluginreview</a> channel on Slack.', 'wporg-plugins' ),
				esc_url( 'https://developer.wordpress.org/plugins/wordpress-org/plugin-developer-faq/' ),
				esc_url( 'https://wordpress.slack.com/archives/C1LBM36LC ' )
			);
			?>

		</p>

		<div class="wp-block-wporg-release-publish-actions">
			<div class="wp-block-button is-small">
				<button data-wp-on-async--click="actions.handlePageReload" class="wp-block-button__link wp-element-button">
					<?php esc_html_e( 'View releases', 'wporg-plugins' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>
