<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package trac-pr
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\Trac\GithubPRs;

// Load the project's composer autoloader (PHPUnit, yoast/phpunit-polyfills).
require_once dirname( __DIR__, 6 ) . '/vendor/autoload.php';

// Declares functions only, so it loads without the API's WordPress bootstrap.
require_once dirname( __DIR__ ) . '/functions.php';

require_once __DIR__ . '/Fake_GitHub_Stream.php';

/**
 * The GitHub App ID, which only names the token cache here.
 */
const GH_TRAC_APP_ID = 1;

/**
 * Answers the token lookup, so GitHub requests never ask for a token.
 *
 * @return string An installation token.
 */
function wp_cache_get() {
	return 'token';
}
