<?php
/**
 * A stand-in for login.wordpress.org's SAML identity provider.
 *
 * Used by the tests and by the local environment's mock API; answers like wp-saml-idp does.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Tests\Support;

use RobRichards\XMLSecLibs\XMLSecurityDSig;
use RobRichards\XMLSecLibs\XMLSecurityKey;

/**
 * Reads login requests and signs responses to them.
 */
final class IdentityProvider {

	/**
	 * Entity ID, which responses are issued by.
	 *
	 * @var string
	 */
	private $entity_id;

	/**
	 * PEM private key.
	 *
	 * @var string
	 */
	private $private_key;

	/**
	 * PEM certificate.
	 *
	 * @var string
	 */
	private $certificate;

	/**
	 * Constructor.
	 *
	 * @param string $entity_id   Entity ID.
	 * @param string $private_key PEM private key.
	 * @param string $certificate PEM certificate.
	 */
	public function __construct( string $entity_id, string $private_key, string $certificate ) {
		$this->entity_id   = $entity_id;
		$this->private_key = $private_key;
		$this->certificate = $certificate;
	}

	/**
	 * Creates an identity provider with a fresh key pair.
	 *
	 * @param string $entity_id Entity ID.
	 * @return self
	 */
	public static function generate( string $entity_id ): self {
		$key  = openssl_pkey_new(
			array(
				'private_key_bits' => 2048,
				'private_key_type' => OPENSSL_KEYTYPE_RSA,
			)
		);
		$csr  = openssl_csr_sign( openssl_csr_new( array( 'commonName' => 'test-idp' ), $key ), null, $key, 1 );
		$pem  = '';
		$cert = '';
		openssl_pkey_export( $key, $pem );
		openssl_x509_export( $csr, $cert );

		return new self( $entity_id, $pem, $cert );
	}

	/**
	 * The certificate as service providers are configured with it: base64, without the BEGIN/END lines.
	 *
	 * @return string
	 */
	public function certificate_body(): string {
		return (string) preg_replace( '/-----[^-]+-----|\s+/', '', $this->certificate );
	}

	/**
	 * Reads a login request sent with the HTTP-Redirect binding.
	 *
	 * @param string $saml_request SAMLRequest query parameter.
	 * @return array {
	 *     @type string $id     Request ID.
	 *     @type string $issuer Service provider entity ID.
	 *     @type string $acs    Where to post the response.
	 * }
	 */
	public static function read_request( string $saml_request ): array {
		$document = new \DOMDocument();
		$document->loadXML( (string) gzinflate( (string) base64_decode( $saml_request, true ) ) );

		$request = $document->documentElement;
		$issuer  = $document->getElementsByTagNameNS( 'urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer' )->item( 0 );

		return array(
			'id'     => $request->getAttribute( 'ID' ),
			'issuer' => $issuer ? $issuer->textContent : '',
			'acs'    => $request->getAttribute( 'AssertionConsumerServiceURL' ),
		);
	}

	/**
	 * Signs a response for a user.
	 *
	 * @param array $args {
	 *     Response details.
	 *
	 *     @type string $username       WordPress.org username.
	 *     @type string $email          Email address.
	 *     @type string $acs            Where the response is posted to.
	 *     @type string $audience       Service provider entity ID.
	 *     @type string $in_response_to Login request ID.
	 *     @type int    $issued_at      Timestamp; responses are valid for a minute.
	 * }
	 * @return string Base64-encoded SAMLResponse.
	 */
	public function response( array $args ): string {
		$args = array_merge(
			array(
				'email'     => '',
				'issued_at' => time(),
			),
			$args
		);

		$now      = gmdate( 'Y-m-d\TH:i:s\Z', $args['issued_at'] );
		$expires  = gmdate( 'Y-m-d\TH:i:s\Z', $args['issued_at'] + 60 );
		$authn_at = gmdate( 'Y-m-d\TH:i:s\Z', $args['issued_at'] - 60 );
		$response = '_' . bin2hex( random_bytes( 16 ) );
		$id       = '_' . bin2hex( random_bytes( 16 ) );

		$xml = sprintf(
			'<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="%1$s" Version="2.0" IssueInstant="%3$s" Destination="%6$s" InResponseTo="%8$s">' .
				'<saml:Issuer>%5$s</saml:Issuer>' .
				'<samlp:Status><samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/></samlp:Status>' .
				'<saml:Assertion ID="%2$s" Version="2.0" IssueInstant="%3$s">' .
					'<saml:Issuer>%5$s</saml:Issuer>' .
					'<saml:Subject>' .
						'<saml:NameID Format="urn:oasis:names:tc:SAML:1.1:nameid-format:unspecified">%9$s</saml:NameID>' .
						'<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">' .
							'<saml:SubjectConfirmationData NotOnOrAfter="%4$s" Recipient="%6$s" InResponseTo="%8$s"/>' .
						'</saml:SubjectConfirmation>' .
					'</saml:Subject>' .
					'<saml:Conditions NotBefore="%3$s" NotOnOrAfter="%4$s">' .
						'<saml:AudienceRestriction><saml:Audience>%7$s</saml:Audience></saml:AudienceRestriction>' .
					'</saml:Conditions>' .
					'<saml:AuthnStatement AuthnInstant="%11$s" SessionIndex="%2$s">' .
						'<saml:AuthnContext><saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef></saml:AuthnContext>' .
					'</saml:AuthnStatement>' .
					'<saml:AttributeStatement>' .
						'<saml:Attribute Name="http://schemas.xmlsoap.org/ws/2005/05/identity/claims/emailaddress"><saml:AttributeValue>%10$s</saml:AttributeValue></saml:Attribute>' .
					'</saml:AttributeStatement>' .
				'</saml:Assertion>' .
			'</samlp:Response>',
			$response,
			$id,
			$now,
			$expires,
			htmlspecialchars( $this->entity_id, ENT_XML1 ),
			htmlspecialchars( (string) $args['acs'], ENT_XML1 ),
			htmlspecialchars( (string) $args['audience'], ENT_XML1 ),
			htmlspecialchars( (string) $args['in_response_to'], ENT_XML1 ),
			htmlspecialchars( (string) $args['username'], ENT_XML1 ),
			htmlspecialchars( (string) $args['email'], ENT_XML1 ),
			$authn_at
		);

		$document = new \DOMDocument();
		$document->loadXML( $xml );

		// Like wp-saml-idp: the assertion first, then the response around it.
		$this->sign( $document->getElementsByTagNameNS( 'urn:oasis:names:tc:SAML:2.0:assertion', 'Assertion' )->item( 0 ) );
		$this->sign( $document->documentElement );

		return base64_encode( (string) $document->saveXML() );
	}

	/**
	 * Adds an enveloped signature to an element, right after its Issuer as the schema wants.
	 *
	 * @param \DOMElement $element Response or Assertion.
	 * @return void
	 */
	private function sign( \DOMElement $element ): void {
		$key = new XMLSecurityKey( XMLSecurityKey::RSA_SHA256, array( 'type' => 'private' ) );
		$key->loadKey( $this->private_key );

		$signature = new XMLSecurityDSig();
		$signature->setCanonicalMethod( XMLSecurityDSig::EXC_C14N );
		$signature->addReference(
			$element,
			XMLSecurityDSig::SHA256,
			array( 'http://www.w3.org/2000/09/xmldsig#enveloped-signature', XMLSecurityDSig::EXC_C14N ),
			array(
				'id_name'   => 'ID',
				'overwrite' => false,
			)
		);
		$signature->sign( $key );
		$signature->add509Cert( $this->certificate );

		$issuer = $element->getElementsByTagNameNS( 'urn:oasis:names:tc:SAML:2.0:assertion', 'Issuer' )->item( 0 );
		$signature->insertSignature( $element, $issuer->nextSibling );
	}
}
