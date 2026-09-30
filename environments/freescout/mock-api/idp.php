<?php
/**
 * Mock of login.wordpress.org's SAML identity provider.
 *
 * Instead of a WordPress.org login, it asks which account to log in as, a mock one or any username, then posts a
 * response signed with saml/idp.key like wp-saml-idp does. The key is for local development only.
 *
 * @package WordPressdotorg\FreeScout\Environment
 */

declare( strict_types = 1 );

namespace WordPressdotorg\FreeScout\Environment\MockAPI;

use Modules\WPOrgSSO\Tests\Support\IdentityProvider;

require '/srv/modules/WPOrgSSO/vendor/autoload.php';
require '/srv/modules/WPOrgSSO/tests/Support/IdentityProvider.php';

$saml_request = (string) ( $_REQUEST['SAMLRequest'] ?? '' );
$relay_state  = (string) ( $_REQUEST['RelayState'] ?? '' );
$accounts     = require __DIR__ . '/accounts.php';

if ( '' === $saml_request ) {
	http_response_code( 400 );
	exit( 'Missing SAMLRequest.' );
}

$request = IdentityProvider::read_request( $saml_request );
$idp     = new IdentityProvider(
	(string) getenv( 'IDP_ENTITY_ID' ),
	(string) file_get_contents( __DIR__ . '/saml/idp.key' ),
	(string) file_get_contents( __DIR__ . '/saml/idp.crt' )
);

header( 'Content-Type: text/html; charset=utf-8' );

$username = trim( (string) ( $_POST['username'] ?? '' ) );

if ( '' !== $username ) {
	$response = $idp->response(
		array(
			'username'       => $username,
			'email'          => $accounts[ $username ]['email'] ?? '',
			'acs'            => $request['acs'],
			'audience'       => $request['issuer'],
			'in_response_to' => $request['id'],
		)
	);
	?>
	<form method="post" action="<?php echo esc( $request['acs'] ); ?>">
		<input type="hidden" name="SAMLResponse" value="<?php echo esc( $response ); ?>">
		<input type="hidden" name="RelayState" value="<?php echo esc( $relay_state ); ?>">
		<noscript><button type="submit">Continue</button></noscript>
	</form>
	<script>document.forms[0].submit();</script>
	<?php
	exit;
}
?>
<!doctype html>
<title>Mock WordPress.org login</title>
<style>body { font-family: sans-serif; max-width: 30em; margin: 4em auto; } button, input { display: block; box-sizing: border-box; width: 100%; margin: .5em 0; padding: .75em; text-align: left; }</style>
<h1>Mock WordPress.org login</h1>
<p>Log in to <?php echo esc( $request['issuer'] ); ?> as:</p>
<form method="post">
	<input type="hidden" name="SAMLRequest" value="<?php echo esc( $saml_request ); ?>">
	<input type="hidden" name="RelayState" value="<?php echo esc( $relay_state ); ?>">
	<?php foreach ( $accounts as $account_username => $account ) : ?>
		<button type="submit" name="username" value="<?php echo esc( $account_username ); ?>">
			<strong><?php echo esc( $account_username ); ?></strong> (<?php echo esc( $account['display_name'] ); ?>)
			<?php echo $account['two_factor'] ? '' : '— no two-factor'; ?>
			<?php echo $account['blocked'] ? '— blocked' : ''; ?>
		</button>
	<?php endforeach; ?>
</form>
<form method="post">
	<input type="hidden" name="SAMLRequest" value="<?php echo esc( $saml_request ); ?>">
	<input type="hidden" name="RelayState" value="<?php echo esc( $relay_state ); ?>">
	<label for="username">Or any username, for a real account when FreeScout uses api.wordpress.org:</label>
	<input id="username" name="username" required>
	<button type="submit">Log in</button>
</form>
