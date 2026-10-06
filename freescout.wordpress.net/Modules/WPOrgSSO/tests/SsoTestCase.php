<?php
/**
 * Base test case for WPOrgSSO.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Tests;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Illuminate\Foundation\Testing\TestResponse;
use Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\Saml;
use Modules\WPOrgSSO\Tests\Support\IdentityProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

require_once __DIR__ . '/Support/IdentityProvider.php';

/**
 * Registers the module against a test identity provider, and answers account.php from a list of accounts.
 */
abstract class SsoTestCase extends TestCase {

	/**
	 * Identity provider entity ID.
	 *
	 * @var string
	 */
	protected const IDP_ENTITY_ID = 'https://login.wordpress.test';

	/**
	 * Test identity provider.
	 *
	 * @var IdentityProvider
	 */
	protected $idp;

	/**
	 * WordPress.org accounts account.php knows, keyed by username.
	 *
	 * @var array
	 */
	protected $accounts = array();

	/**
	 * Avatars the avatar host serves, keyed by URL; others are not found.
	 *
	 * @var array
	 */
	protected $avatars = array();

	/**
	 * Whether account.php is down.
	 *
	 * @var bool
	 */
	protected $api_down = false;

	/**
	 * Configures and registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->idp = IdentityProvider::generate( self::IDP_ENTITY_ID );

		// Photos taken from avatars land in a throwaway folder.
		\Storage::fake( 'local' );

		config(
			array(
				'wporgsso.idp.entity_id'  => self::IDP_ENTITY_ID,
				'wporgsso.idp.url'        => self::IDP_ENTITY_ID . '/wp-login.php?action=idp',
				'wporgsso.idp.cert'       => $this->idp->certificate_body(),
				'wporgsso.password_login' => false,
			)
		);

		$this->avatars = array( 'https://secure.gravatar.com/avatar/rita?s=256&d=mm' => self::image( 255, 0, 0 ) );

		$this->accounts = array(
			'rita' => array(
				'username'     => 'rita',
				'display_name' => 'Rita Reviewer',
				'first_name'   => 'Rita',
				'last_name'    => 'Reviewer',
				'email'        => 'rita@example.org',
				'avatar_url'   => 'https://secure.gravatar.com/avatar/rita?s=256&d=mm',
				'two_factor'   => true,
				'blocked'      => false,
			),
		);

		$this->app->instance(
			Client::class,
			new Client( 'https://api.wordpress.test/', 'test-secret', 10, array( $this, 'answer_api' ) )
		);

		$this->app->register( WPOrgSSOServiceProvider::class );

		// Routes registered after boot aren't in the name index yet.
		$this->app['router']->getRoutes()->refreshNameLookups();
	}

	/**
	 * Answers requests to account.php and for avatars.
	 *
	 * @param \Psr\Http\Message\RequestInterface $request Request.
	 * @return \GuzzleHttp\Promise\PromiseInterface
	 */
	public function answer_api( \Psr\Http\Message\RequestInterface $request ): \GuzzleHttp\Promise\PromiseInterface {
		$avatar = $this->avatars[ (string) $request->getUri() ] ?? null;
		if ( $avatar || 'secure.gravatar.com' === $request->getUri()->getHost() ) {
			$response = $avatar ? new Response( 200, array( 'Content-Type' => 'image/png' ), $avatar ) : new Response( 404 );

			return ( new MockHandler( array( $response ) ) )( $request, array() );
		}

		if ( $this->api_down ) {
			return ( new MockHandler( array( new Response( 500 ) ) ) )( $request, array() );
		}

		$payload  = json_decode( (string) $request->getBody(), true );
		$username = strtolower( (string) ( $payload['username'] ?? '' ) );
		$user     = $this->accounts[ $username ] ?? null;

		return ( new MockHandler( array( new Response( 200, array(), (string) json_encode( array( 'user' => $user ) ) ) ) ) )( $request, array() );
	}

	/**
	 * Draws a square PNG in one colour.
	 *
	 * @param int $red   Red.
	 * @param int $green Green.
	 * @param int $blue  Blue.
	 * @return string PNG.
	 */
	protected static function image( int $red, int $green, int $blue ): string {
		$image = imagecreatetruecolor( 64, 64 );
		imagefill( $image, 0, 0, (int) imagecolorallocate( $image, $red, $green, $blue ) );

		ob_start();
		imagepng( $image );

		return (string) ob_get_clean();
	}

	/**
	 * Starts a login, and signs the identity provider's response to it.
	 *
	 * @param string $username WordPress.org username to log in as.
	 * @return string Base64-encoded SAMLResponse.
	 */
	protected function start_login( string $username ): string {
		$location = (string) $this->get( '/wporgsso/start' )->headers->get( 'Location' );
		parse_str( (string) parse_url( $location, PHP_URL_QUERY ), $query );

		$request = IdentityProvider::read_request( (string) $query['SAMLRequest'] );

		return $this->idp->response(
			array(
				'username'       => $username,
				'acs'            => $request['acs'],
				'audience'       => $request['issuer'],
				'in_response_to' => $request['id'],
			)
		);
	}

	/**
	 * Posts a response to the ACS, like the browser does.
	 *
	 * @param string $saml_response Base64-encoded SAMLResponse.
	 * @return string Where the ACS sends the browser.
	 */
	protected function post_to_acs( string $saml_response ): string {
		// OneLogin checks the response's destination against the URL it's read at.
		$request_uri            = $_SERVER['REQUEST_URI'] ?? null;
		$_SERVER['REQUEST_URI'] = '/wporgsso/acs';

		try {
			$response = $this->post( '/wporgsso/acs', array( 'SAMLResponse' => $saml_response ) );
		} finally {
			if ( null === $request_uri ) {
				unset( $_SERVER['REQUEST_URI'] );
			} else {
				$_SERVER['REQUEST_URI'] = $request_uri;
			}
		}

		$response->assertStatus( 303 );

		return (string) $response->headers->get( 'Location' );
	}

	/**
	 * Follows the ACS redirect back into the browser session.
	 *
	 * @param string $location Where the ACS sent the browser.
	 * @return TestResponse
	 */
	protected function complete( string $location ): TestResponse {
		return $this->get( (string) parse_url( $location, PHP_URL_PATH ) . '?' . (string) parse_url( $location, PHP_URL_QUERY ) );
	}

	/**
	 * The ACS URL responses are addressed to.
	 *
	 * @return string
	 */
	protected function acs_url(): string {
		return Saml::from_config()->acs_url();
	}
}
