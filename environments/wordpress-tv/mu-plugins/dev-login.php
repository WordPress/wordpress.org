<?php
/**
 * Plugin Name: Dev Login Helper Loader
 * Description: Loads the shared one-click login helper from the mocks directory.
 *
 * The mocks are mapped in as a directory because Docker Desktop's runc can't create
 * a single-file mountpoint inside the core directory wp-env bind-mounts.
 *
 * @package WordPressdotorg\Environments
 */

declare( strict_types = 1 );

require WP_CONTENT_DIR . '/env-mocks/mu-plugins/wporg-dev-login.php';
