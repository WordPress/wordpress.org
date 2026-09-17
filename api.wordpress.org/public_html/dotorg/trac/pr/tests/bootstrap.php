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
