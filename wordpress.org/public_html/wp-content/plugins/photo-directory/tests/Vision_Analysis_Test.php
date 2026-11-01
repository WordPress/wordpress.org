<?php
/**
 * Tests the Google Vision analysis of an uploaded photo.
 *
 * @package WordPressdotorg\Photo_Directory
 */

declare( strict_types = 1 );

use Google\ApiCore\InsecureCredentialsWrapper;
use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use WordPressdotorg\Photo_Directory\VisionAPI;

/**
 * Tests for `VisionAPI::get_vision_analysis()`.
 *
 * The Vision API is not called: the client is given an HTTP handler that answers
 * with a recorded response instead of sending the request, so everything from
 * building the request through the vendored protobuf decoding runs for real.
 *
 * @group photo-directory
 */
#[Group( 'photo-directory' )]
class Vision_Analysis_Test extends TestCase {

	/**
	 * The request the client sent, as captured by the HTTP handler.
	 *
	 * @var array{uri: string, body: array}|null
	 */
	private ?array $request = null;

	/**
	 * Filter callbacks added for the test, keyed by hook name.
	 *
	 * @var array<string, Closure>
	 */
	private array $filters = array();

	/**
	 * Configures Google Cloud the way the production filters do.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->filters = array(
			// The path only has to exist; the client under test never reads it.
			'wporg_photos_google_credentials_json_path' => fn(): string => __FILE__,
			'wporg_photos_google_cloud_bucket'          => fn(): string => 'photos-test',
		);
		foreach ( $this->filters as $hook => $callback ) {
			add_filter( $hook, $callback );
		}
	}

	/**
	 * Removes the filters added in `setUp()`.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->filters as $hook => $callback ) {
			remove_filter( $hook, $callback );
		}

		parent::tearDown();
	}

	/**
	 * Builds a Vision client whose HTTP handler answers with the given body.
	 *
	 * @param string $body JSON body of the `images:annotate` response.
	 * @return ImageAnnotatorClient
	 */
	private function client( string $body ): ImageAnnotatorClient {
		return new ImageAnnotatorClient(
			array(
				'credentials'     => new InsecureCredentialsWrapper(),
				'transport'       => 'rest',
				'transportConfig' => array(
					'rest' => array(
						'httpHandler' => function ( RequestInterface $request ) use ( $body ): PromiseInterface {
							$this->request = array(
								'uri'  => (string) $request->getUri(),
								'body' => json_decode( (string) $request->getBody(), true ),
							);

							return Create::promiseFor( new Response( 200, array( 'Content-Type' => 'application/json' ), $body ) );
						},
					),
				),
			)
		);
	}

	/**
	 * Sends the configured features for the bucket object and stores the response as plain post meta values.
	 *
	 * @return void
	 */
	public function test_stores_analysis_from_api_response(): void {
		$client = $this->client( file_get_contents( __DIR__ . '/fixtures/vision-response.json' ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local fixture.

		$results = VisionAPI::get_vision_analysis( '2026/10/photo.jpg', $client );

		$this->assertSame( 'https://vision.googleapis.com/v1/images:annotate?%24alt=json%3Benum-encoding%3Dint', $this->request['uri'] );
		$this->assertSame(
			array(
				'image'    => array( 'source' => array( 'gcsImageUri' => 'gs://photos-test/2026/10/photo.jpg' ) ),
				'features' => array(
					array(
						'type'       => 'FACE_DETECTION',
						'maxResults' => 10,
					),
					array(
						'type'       => 'IMAGE_PROPERTIES',
						'maxResults' => 50,
					),
					array(
						'type'       => 'LABEL_DETECTION',
						'maxResults' => 10,
					),
					array(
						'type'       => 'SAFE_SEARCH_DETECTION',
						'maxResults' => 50,
					),
				),
			),
			$this->request['body']['requests'][0]
		);

		$this->assertSame( array( 'tree', 'plant', 'sky' ), $results['vision_labels'] );
		$this->assertSame(
			array(
				'adult'    => 'VERY_UNLIKELY',
				'medical'  => 'VERY_UNLIKELY',
				'racy'     => 'POSSIBLE',
				'spoof'    => 'UNLIKELY',
				'violence' => 'UNLIKELY',
			),
			$results['vision_safe_search']
		);
		$this->assertSame( 1, $results['vision_faces']['count'] );
		$this->assertSame( 'LEFT_EYE', $results['vision_faces']['raw'][0]['landmarks'][0]['type'] );
		$this->assertSame(
			array(
				'green' => 0.3125,
				'blue'  => 0.375,
			),
			$results['vision_colors']
		);
		$this->assertSame(
			array(
				'b'          => 3,
				'g'          => 12,
				'r'          => 0.0,
				'percentage' => 0.0625,
				'base_color' => 'green',
			),
			$results['vision_raw_colors'][2]
		);

		// Post meta must not depend on the library's classes to unserialize.
		array_walk_recursive(
			$results,
			function ( mixed $value ): void {
				$this->assertIsNotObject( $value );
			}
		);
	}

	/**
	 * Returns the image's error as a `WP_Error`, which stops the analysis from being stored.
	 *
	 * @return void
	 */
	public function test_returns_error_for_failed_image(): void {
		$client = $this->client( '{"responses":[{"error":{"code":3,"message":"Bad image data."}}]}' );

		$results = VisionAPI::get_vision_analysis( '2026/10/photo.jpg', $client );

		$this->assertInstanceOf( WP_Error::class, $results );
		$this->assertSame( 'wporg-photos-vision-error', $results->get_error_code() );
		$this->assertSame( 'Bad image data.', $results->get_error_message() );
	}
}
