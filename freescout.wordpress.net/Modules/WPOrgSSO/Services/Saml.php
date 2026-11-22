<?php
/**
 * SAML service provider for the WordPress.org identity provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Services;

use OneLogin\Saml2\Auth;
use OneLogin\Saml2\Constants;
use OneLogin\Saml2\Response;
use OneLogin\Saml2\Settings;
use OneLogin\Saml2\Utils;
use RuntimeException;

/**
 * Starts logins at login.wordpress.org and validates the signed responses it sends back.
 */
final class Saml {

	/**
	 * FreeScout's base URL, without a trailing slash.
	 *
	 * @var string
	 */
	private $app_url;

	/**
	 * Identity provider entity ID.
	 *
	 * @var string
	 */
	private $idp_entity_id;

	/**
	 * Identity provider login URL.
	 *
	 * @var string
	 */
	private $idp_url;

	/**
	 * Identity provider signing certificate.
	 *
	 * @var string
	 */
	private $idp_cert;

	/**
	 * Constructor.
	 *
	 * @param string $app_url       FreeScout's base URL.
	 * @param string $idp_entity_id Identity provider entity ID.
	 * @param string $idp_url       Identity provider login URL.
	 * @param string $idp_cert      Identity provider signing certificate.
	 */
	public function __construct( string $app_url, string $idp_entity_id, string $idp_url, string $idp_cert ) {
		$this->app_url       = rtrim( $app_url, '/' );
		$this->idp_entity_id = $idp_entity_id;
		$this->idp_url       = $idp_url;

		// .env values are one line: a certificate pasted with its line breaks written as \n still works.
		$this->idp_cert = str_replace( array( '\r', '\n' ), '', $idp_cert );
	}

	/**
	 * Creates the service provider from the module configuration.
	 *
	 * @return self
	 */
	public static function from_config(): self {
		return new self(
			(string) config( 'app.url' ),
			(string) config( 'wporgsso.idp.entity_id' ),
			(string) config( 'wporgsso.idp.url' ),
			(string) config( 'wporgsso.idp.cert' )
		);
	}

	/**
	 * Whether the identity provider is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->idp_entity_id && '' !== $this->idp_url && '' !== $this->idp_cert;
	}

	/**
	 * FreeScout's entity ID, which login.wordpress.org knows it by.
	 *
	 * @return string
	 */
	public function entity_id(): string {
		return $this->app_url . '/wporgsso/metadata';
	}

	/**
	 * Where login.wordpress.org posts its responses.
	 *
	 * @return string
	 */
	public function acs_url(): string {
		return $this->app_url . '/wporgsso/acs';
	}

	/**
	 * Builds a login request.
	 *
	 * @return array {
	 *     @type string $url        Identity provider URL to send the browser to.
	 *     @type string $request_id ID the response has to answer, in InResponseTo.
	 * }
	 */
	public function login_request(): array {
		$auth = new Auth( $this->settings() );
		$url  = $auth->login( $this->app_url, array(), false, false, true );

		return array(
			'url'        => $url,
			'request_id' => (string) $auth->getLastRequestID(),
		);
	}

	/**
	 * Validates a response from the identity provider.
	 *
	 * Must run at the ACS URL: the response names it as its destination.
	 *
	 * @param string $saml_response Base64-encoded SAMLResponse.
	 * @return array {
	 *     @type string $username       WordPress.org username.
	 *     @type string $in_response_to ID of the login request it answers.
	 * }
	 *
	 * @throws RuntimeException If the response is not valid.
	 */
	public function validate( string $saml_response ): array {
		if ( ! $this->has_readable_cert() ) {
			throw new RuntimeException( 'WPORG_SSO_IDP_CERT is not a readable X.509 certificate. Put it on one line, with or without its BEGIN/END lines.' );
		}

		// Compare the destination with the canonical URL, not whatever host and scheme the proxy passed on.
		Utils::setBaseURL( $this->app_url );

		try {
			$response = new Response( new Settings( $this->settings() ), $saml_response );

			// The login request's ID lives in the browser session, which this cross-site POST doesn't carry.
			if ( ! $response->isValid() ) {
				throw new RuntimeException( 'Invalid SAML response: ' . $response->getError( false ) );
			}

			$username       = (string) $response->getNameId();
			$in_response_to = self::in_response_to( $response->getXMLDocument() );
		} catch ( RuntimeException $e ) {
			throw $e;
		} catch ( \Throwable $e ) {
			throw new RuntimeException( 'Invalid SAML response: ' . $e->getMessage(), 0, $e );
		} finally {
			Utils::setBaseURL( '' );
		}

		if ( '' === $username || '' === $in_response_to ) {
			throw new RuntimeException( 'SAML response without a username or login request.' );
		}

		return array(
			'username'       => $username,
			'in_response_to' => $in_response_to,
		);
	}

	/**
	 * Whether the identity provider's certificate can be read.
	 *
	 * Otherwise every response fails its signature check, which hides that the setting is to blame.
	 *
	 * @return bool
	 */
	private function has_readable_cert(): bool {
		$pem = (string) Utils::formatCert( $this->idp_cert );
		if ( '' === $pem ) {
			return false;
		}

		// FreeScout turns openssl's warning about an unreadable certificate into an exception.
		try {
			return false !== openssl_x509_read( $pem );
		} catch ( \Throwable $e ) {
			return false;
		}
	}

	/**
	 * Gets the ID of the login request a response answers.
	 *
	 * WordPress.org's identity provider only names it in the assertion's bearer confirmation, not on the response
	 * itself. The whole response is signed, so it's as trustworthy there.
	 *
	 * @param \DOMDocument $document Validated response.
	 * @return string Login request ID, or empty.
	 */
	private static function in_response_to( \DOMDocument $document ): string {
		$xpath = new \DOMXPath( $document );
		$xpath->registerNamespace( 'samlp', Constants::NS_SAMLP );
		$xpath->registerNamespace( 'saml', Constants::NS_SAML );

		return (string) $xpath->evaluate( 'string(/samlp:Response/saml:Assertion/saml:Subject/saml:SubjectConfirmation[@Method="' . Constants::CM_BEARER . '"]/saml:SubjectConfirmationData/@InResponseTo)' );
	}

	/**
	 * FreeScout's service provider metadata, for registering it with the identity provider.
	 *
	 * @return string XML.
	 */
	public function metadata(): string {
		return ( new Settings( $this->settings(), true ) )->getSPMetadata();
	}

	/**
	 * OneLogin settings.
	 *
	 * @return array
	 */
	private function settings(): array {
		return array(
			'strict'   => true,
			'sp'       => array(
				'entityId'                 => $this->entity_id(),
				'assertionConsumerService' => array(
					'url'     => $this->acs_url(),
					'binding' => Constants::BINDING_HTTP_POST,
				),
				// The identity provider names users by username, not email.
				'NameIDFormat'             => Constants::NAMEID_UNSPECIFIED,
			),
			'idp'      => array(
				'entityId'            => $this->idp_entity_id,
				'singleSignOnService' => array(
					'url'     => $this->idp_url,
					'binding' => Constants::BINDING_HTTP_REDIRECT,
				),
				'x509cert'            => $this->idp_cert,
			),
			'security' => array(
				// login.wordpress.org signs both.
				'wantMessagesSigned'    => true,
				'wantAssertionsSigned'  => true,
				'requestedAuthnContext' => false,
			),
		);
	}
}
