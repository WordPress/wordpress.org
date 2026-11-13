<?php
/**
 * Generates a WP_SECRETS_KEY line for wp-config.php.
 *
 * The Secrets API in WordPress 7.2 encrypts stored credentials under this key.
 * It is served apart from /secret-key/1.1/salt/ on purpose. People and tools
 * rotate their salts by replacing them with that endpoint's whole output, and
 * replacing this key makes every stored secret unreadable.
 *
 * @link https://meta.trac.wordpress.org/ticket/8492
 * @package WordPressdotorg\API\SecretKey
 */

header( 'Cache-Control: no-store' );

try {
	$key = random_bytes( 32 );
} catch ( Exception $e ) {
	// Never hand out a weaker key.
	http_response_code( 500 );
	exit;
}

header( 'Content-Type: text/plain; charset=utf-8' );

// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Plain-text API response; base64 is the key's format.
echo "define( 'WP_SECRETS_KEY', '" . base64_encode( $key ) . "' );\n";
