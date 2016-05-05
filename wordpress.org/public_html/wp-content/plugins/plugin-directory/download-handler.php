<?php
namespace WordPressdotorg\Plugin_Directory;

/*
 * This is a stand alone script, it operates with the expectation that the
 * global `$wpdb` is available and the object cache is configured.
 * 
 * Examples of URLs which are accepted:
 * - downloads.wordpress.org/plugin/hello-dolly.zip (trunk)
 * - downloads.wordpress.org/plugin/hello-dolly.latest-stable.zip
 * - downloads.wordpress.org/plugin/hello-dolly.3.2.1.zip
 */

include __DIR__ . '/class-autoloader.php';
Autoloader\register_class_path( __NAMESPACE__, __DIR__ );

$serve = new Zip\Serve();