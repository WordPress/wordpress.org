<?php
/**
 * Renders the Release Details Form block.
 *
 * @package WordPressdotorg\Plugin_Directory
 */

use WordPressdotorg\Plugin_Directory\Template;
use function WordPressdotorg\Plugin_Directory\Theme\{get_blueprint_url, get_latest_release};

// Ensure the block context has a valid post ID.
if ( empty( $block->context['postId'] ) ) {
	return;
}

$release_post    = get_post( $block->context['postId'] );
$current_version = get_post_meta( $block->context['postId'], 'release_version', true );
$tested_up_to    = get_post_meta( $block->context['postId'], 'release_tested', true );

// Bail if the release post does not exist.
if ( ! $release_post ) {
	return;
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
 * @param string $value   The value for the item.
 * @param string $content The additional content or description.
 * @return string The formatted HTML content for the release item.
 */
function get_release_item_content( $label, $value, $content ) {
	return sprintf(
		'<div><strong>%1$s:</strong> <code>%2$s</code><div>%3$s</div></div>',
		wp_kses_post( $label ),
		wp_kses_post( $value ),
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
			'<!-- wp:wporg/release-check-item {"status":"%1$s"} -->%2$s<!-- /wp:wporg/release-check-item -->',
			esc_attr( $status ),
			$content
		)
	);
}

/**
 * Generate the block content for the version number check item.
 *
 * @param bool    $verdict Whether the version number is valid.
 * @param string  $value The current version number.
 * @param WP_Post $release_post The release post object.
 *
 * @return string The block content.
 */
function get_version_number_check_item( $verdict, $value, $release_post ) {
	$label     = __( 'Version number', 'wporg-plugins' );
	$info_text = __( 'It looks like plugin version was incremented appropriately.', 'wporg-plugins' );
	$status    = 'success';

	if ( ! $verdict ) {
		$status    = 'error';
		$info_text = __( 'Please increment your plugin\'s version number.', 'wporg-plugins' );
	}

	$content = get_release_item_content( $label, $value, $info_text );

	return get_release_check_item( $status, $content );
}

/**
 * Generate the block content for the "Tested up to" check item.
 *
 * @param string $value The tested up to value.
 * @return string The block content.
 */
function get_tested_up_to_check_item( $verdict, $value, $release_post ) {
	$label  = __(
		'Tested up to',
		'wporg-plugins'
	);
	$status = 'success';

	$parent = get_post( $release_post->post_parent );

	$blueprint_url = get_blueprint_url(
		sprintf(
			'https://downloads.wordpress.org/plugin/%s.zip',
			$parent->post_name,
		)
	);

	$info_text = sprintf(
		/* translators: %s: URL to the plugin in the Plugin Directory */
		__( 'Everything looks great! Playground makes testing easy. If needed, you can test it <a target="_blank" href="%s">here.</a>', 'wporg-plugins' ),
		esc_url( $blueprint_url )
	);

	if ( empty( $value ) ) {
		$value     = __( 'Unknown', 'wporg-plugins' );
		$status    = 'error';
		$info_text = __( 'We weren\'t able to determine your "Tested up to" value.', 'wporg-plugins' );
	} elseif ( ! $verdict ) {
		$status    = 'warning';
		$info_text = sprintf(
			/* translators: %s: URL to the plugin in the Plugin Directory */
			__( 'Your plugin has been tested with recent versions of WordPress! <a target="_blank" href="%s">Test it now in Playground</a>.', 'wporg-plugins' ),
			esc_url( $blueprint_url )
		);
	}

	$content = get_release_item_content( $label, $value, $info_text );

	return get_release_check_item( $status, $content );
}

$latest_release    = get_latest_release( $release_post->post_parent );
$last_version      = get_post_meta( $latest_release->ID, 'release_version', true );
$version_pass      = version_compare( $current_version, $last_version, '>' );
$tested_up_to_pass = has_recently_been_tested( $tested_up_to );

?>

<form 
	data-wp-on-async--submit="actions.handleSubmit" 
	data-wp-interactive="async-action-block" 
	<?php echo wp_kses_data( get_block_wrapper_attributes() ); ?>
>
<?php
	echo do_blocks(
		sprintf(
			'<!-- wp:heading {"level":4,"fontSize":"normal", "style":{"spacing":{"margin":{"bottom":"0","left":"0","right":"0"}}}} -->
				<h4 class="wp-block-heading has-normal-font-size" style="margin-right:0;margin-bottom:0;margin-left:0">%s</h4>
			<!-- /wp:heading -->',
			esc_html__( 'Headers', 'wporg-plugins' )
		)
	);
?>

	<ul>
		<?php echo get_version_number_check_item( $version_pass, $current_version, $release_post ); ?>
		<?php echo get_tested_up_to_check_item( $tested_up_to_pass, $tested_up_to, $release_post ); ?>     
	</ul>

	<div class="wp-block-group wp-block-wporg-release-details-form-actions">
		<div class="wp-block-button is-small">
			<button 
				type="submit" 
				class="wp-block-button__link wp-element-button"
				data-wp-text="state.btnText"
				<?php echo ! $version_pass ? 'disabled' : ''; ?>
			>
				<?php esc_html_e( 'Publish release', 'wporg-plugins' ); ?>
			</button>
		</div>

		<div 
			class="wp-block-button is-small is-style-text" 
			data-wp-on-async--click="actions.handleBackClick"
		>
			<button 
				type="button" 
				class="wp-block-button__link wp-element-button"
			>
				<?php esc_html_e( 'Cancel', 'wporg-plugins' ); ?>
			</button>
		</div>
	</div>
</form>
