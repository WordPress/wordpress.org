<?php
/**
 * PHPUnit bootstrap for the freescout.wordpress.net modules.
 *
 * @package WordPressdotorg\FreeScout\Tests
 */

declare( strict_types = 1 );

/**
 * Path of the FreeScout install the tests run against.
 *
 * @var string
 */
define( 'FREESCOUT_PATH', getenv( 'FREESCOUT_PATH' ) ? getenv( 'FREESCOUT_PATH' ) : '/var/www/html' );

require FREESCOUT_PATH . '/vendor/autoload.php';
require __DIR__ . '/TestCase.php';
