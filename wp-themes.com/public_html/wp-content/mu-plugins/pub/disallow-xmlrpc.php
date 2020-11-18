<?php
namespace WordPressdotorg\Theme_Preview;
/**
 * Plugin Name: Disallow all XML-RPC.
 * Description: Simply calls `die()` for all XML-RPC requests.
 */

if ( defined( 'XMLRPC_REQUEST' ) ) {
	die( 'Sorry, XML-RPC is disabled on this site.' );
}
