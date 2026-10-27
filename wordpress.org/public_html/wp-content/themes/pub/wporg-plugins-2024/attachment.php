<?php
/**
 * The template for Attachments.
 *
 * @package WordPressdotorg\Plugin_Directory\Theme
 */

namespace WordPressdotorg\Plugin_Directory\Theme;

// The plugin directory does not have any attachments which should be accessed directly.
wp_safe_redirect( home_url( '/' ), 301, 'attachment.php' );
exit;

