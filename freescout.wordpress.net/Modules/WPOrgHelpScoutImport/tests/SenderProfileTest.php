<?php
/**
 * Tests for giving senders their HelpScout profile.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Tests;

use App\Customer;
use App\Email;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Modules\WPOrgHelpScoutImport\Exceptions\RateLimited;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;
use Modules\WPOrgHelpScoutImport\Services\Importer;
use Modules\WPOrgHelpScoutImport\Services\People;
use Modules\WPOrgHelpScoutImport\Tests\Support\FakeHelpScout;

require_once __DIR__ . '/ImportTestCase.php';

/**
 * Covers People::complete(), through the importer.
 */
final class SenderProfileTest extends ImportTestCase {

	/**
	 * Importer under test.
	 *
	 * @var Importer
	 */
	private $importer;

	/**
	 * The sender's HelpScout customer ID, as the fixtures give it.
	 *
	 * @var int
	 */
	private $customer_id;

	/**
	 * Creates the importer, and fakes where sender photos are kept.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		Storage::fake( 'local' );
		$this->importer    = new Importer( app( HelpScout::class ), new People( app( HelpScout::class ) ) );
		$this->customer_id = (int) self::sender( 'sam@example.org', 'Sam', 'Sender' )['id'];
	}

	/**
	 * A new sender gets their HelpScout profile, and what FreeScout has no field for is kept in their meta.
	 *
	 * @return void
	 */
	public function test_sender_gets_their_profile(): void {
		$this->helpscout->on( 'GET', 'v2/customers/' . $this->customer_id, $this->profile() );

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$sender = $this->imported_sender();
		$this->assertSame( 'Sam', $sender->first_name );
		$this->assertSame( 'Acme, Inc', $sender->company );
		$this->assertSame( 'CEO', $sender->job_title );
		$this->assertSame( 'Knows the photo guidelines well.', $sender->notes );
		$this->assertSame( '1 Main Street, Suite 2', $sender->address );
		$this->assertSame( 'Prague', $sender->city );
		$this->assertSame( '11000', $sender->zip );
		$this->assertSame( 'CZ', $sender->country );
		$this->assertSame( array( '+420 123', '555-0100' ), array_column( $sender->getPhones(), 'value' ) );
		$this->assertSame( Customer::PHONE_TYPE_MOBILE, (int) $sender->getPhones()[0]['type'] );
		$this->assertSame( array( 'https://sam.example.org' ), $sender->getWebsites() );
		$this->assertSame(
			array(
				array(
					'value' => '@sam',
					'type'  => Customer::SOCIAL_TYPE_TWITTER,
				),
			),
			$sender->getSocialProfiles()
		);
		$this->assertEqualsCanonicalizing( array( 'sam@example.org', 'sam@home.example.org' ), $sender->emails()->pluck( 'email' )->all() );
		$this->assertSame( Email::TYPE_HOME, (int) Email::query()->where( 'email', 'sam@home.example.org' )->value( 'type' ) );

		$meta = $sender->getMeta( People::PROFILE_META );
		$this->assertSame( $this->customer_id, $meta['id'] );
		$this->assertSame( 'samchat', $meta['chats'][0]['value'] );
		$this->assertSame( array( 'Tesla' ), array_column( $meta['properties'], 'value' ) );
		$this->assertSame( '30-35', $meta['age'] );

		// The photo is copied, resized like FreeScout's own.
		$this->assertNotEmpty( $sender->photo_url );
		$this->assertSame( Customer::PHOTO_TYPE_TWITTER, (int) $sender->photo_type );
		Storage::disk( 'local' )->assertExists( Customer::PHOTO_DIRECTORY . '/' . $sender->photo_url );
	}

	/**
	 * Gravatars are left to WPOrgSidebar, and HelpScout's own placeholder photos aren't copied.
	 *
	 * @return void
	 */
	public function test_gravatars_and_placeholders_are_left_out(): void {
		foreach ( array( 'gravatar', 'unknown' ) as $type ) {
			$profile              = $this->profile();
			$profile['photoType'] = $type;
			$this->helpscout->only( 'GET', 'v2/customers/' . $this->customer_id, $profile );
			Customer::query()->whereKey( Email::query()->where( 'email', 'sam@example.org' )->value( 'customer_id' ) )->update( array( 'meta' => null ) );

			$this->importer->import( $this->conversation(), $this->mailbox );

			$this->assertEmpty( $this->imported_sender()->photo_url, $type );
		}

		$this->assertCount( 0, $this->helpscout->requests_to( 'photos/sam.png' ) );
	}

	/**
	 * A sender's profile is read once, however many of their conversations are imported.
	 *
	 * @return void
	 */
	public function test_profile_is_read_once(): void {
		$this->helpscout->on( 'GET', 'v2/customers/' . $this->customer_id, $this->profile() );
		$this->answer_threads( 1002, $this->threads( 100 ) );

		$this->importer->import( $this->conversation(), $this->mailbox );
		$this->importer->import( $this->conversation( array( 'id' => 1002 ) ), $this->mailbox );

		$this->assertCount( 1, $this->helpscout->requests_to( 'v2/customers/' . $this->customer_id ) );
	}

	/**
	 * Someone who wrote an email in the conversation, other than its sender, gets their profile too.
	 *
	 * @return void
	 */
	public function test_thread_author_gets_their_profile(): void {
		$author                  = self::sender( 'bo@example.org', 'Bo', 'Brother' );
		$threads                 = $this->threads();
		$threads[0]['customer']  = $author;
		$threads[0]['createdBy'] = $author;
		$this->answer_threads( self::CONVERSATION_ID, $threads );
		$this->helpscout->on(
			'GET',
			'v2/customers/' . $author['id'],
			array(
				'id'           => $author['id'],
				'organization' => 'Bo & Co',
			)
		);

		$this->importer->import( $this->conversation(), $this->mailbox );

		$this->assertSame( 'Bo & Co', Email::query()->where( 'email', 'bo@example.org' )->firstOrFail()->customer->company );
	}

	/**
	 * A profile that can't be saved is left out; the sender and their conversation are imported, and it isn't tried again.
	 *
	 * @return void
	 */
	public function test_profile_that_cannot_be_saved_is_left_out(): void {
		$this->helpscout->on(
			'GET',
			'v2/customers/' . $this->customer_id,
			array(
				'id'           => $this->customer_id,
				'organization' => 'Unsaveable',
			)
		);
		Customer::saving(
			static function ( Customer $customer ): void {
				if ( 'Unsaveable' === $customer->company ) {
					throw new \RuntimeException( 'Could not save.' );
				}
			}
		);

		$this->assertSame( Importer::IMPORTED, $this->importer->import( $this->conversation(), $this->mailbox ) );

		$sender = $this->imported_sender();
		$this->assertNull( $sender->company );
		$this->assertSame( array( 'id' => $this->customer_id ), $sender->getMeta( People::PROFILE_META ) );
	}

	/**
	 * A sender HelpScout won't give the profile of is imported without it.
	 *
	 * @return void
	 */
	public function test_sender_without_a_profile_is_imported(): void {
		foreach ( array( 404, 403 ) as $status ) {
			$this->helpscout->only( 'GET', 'v2/customers/' . $this->customer_id, FakeHelpScout::json( array(), $status ) );
			Customer::query()->whereKey( Email::query()->where( 'email', 'sam@example.org' )->value( 'customer_id' ) )->update( array( 'meta' => null ) );

			$this->assertContains( $this->importer->import( $this->conversation(), $this->mailbox ), array( Importer::IMPORTED, Importer::UPDATED ) );

			$sender = $this->imported_sender();
			$this->assertNull( $sender->company );
			$this->assertSame( array( 'id' => $this->customer_id ), $sender->getMeta( People::PROFILE_META ), 'HTTP ' . $status );
		}
	}

	/**
	 * The rate limit stops the conversation before anything of it is written, so it's imported on its next try.
	 *
	 * @return void
	 */
	public function test_rate_limit_stops_before_writing(): void {
		$this->helpscout->on( 'GET', 'v2/customers/' . $this->customer_id, FakeHelpScout::json( array(), 429, array( 'X-RateLimit-Retry-After' => '30' ) ) );

		try {
			$this->importer->import( $this->conversation(), $this->mailbox );
			$this->fail( 'The import should have waited for the rate limit.' );
		} catch ( RateLimited $e ) {
			$this->assertSame( 0, $this->mailbox->conversations()->count() );
			// Nothing was downloaded yet, to be downloaded again on the next try.
			$this->assertCount( 0, $this->helpscout->requests_to( 'v2/conversations/1001/attachments/3001/file' ) );
		}
	}

	/**
	 * The fixtures' sender, as imported.
	 *
	 * @return Customer
	 */
	private function imported_sender(): Customer {
		return Email::query()->where( 'email', 'sam@example.org' )->firstOrFail()->customer;
	}

	/**
	 * The sender's HelpScout profile, and their photo to copy.
	 *
	 * @return array
	 */
	private function profile(): array {
		$this->helpscout->on( 'GET', 'photos/sam.png', new Response( 200, array(), self::png() ) );

		return array(
			'id'           => $this->customer_id,
			'firstName'    => 'Samuel',
			'lastName'     => 'Sender',
			'organization' => 'Acme, Inc',
			'jobTitle'     => 'CEO',
			'background'   => 'Knows the photo guidelines well.',
			'location'     => 'Central Europe',
			'age'          => '30-35',
			'photoType'    => 'twitter',
			'photoUrl'     => 'https://pbs.twimg.com/photos/sam.png',
			'_embedded'    => array(
				'emails'          => array(
					array(
						'id'    => 1,
						'value' => 'sam@example.org',
						'type'  => 'work',
					),
					array(
						'id'    => 2,
						'value' => 'sam@home.example.org',
						'type'  => 'home',
					),
				),
				'phones'          => array(
					array(
						'id'    => 3,
						'value' => '+420 123',
						'type'  => 'mobile',
					),
					array(
						'id'    => 4,
						'value' => '555-0100',
						'type'  => 'satellite',
					),
				),
				'chats'           => array(
					array(
						'id'    => 5,
						'value' => 'samchat',
						'type'  => 'skype',
					),
				),
				'social_profiles' => array(
					array(
						'id'    => 6,
						'value' => '@sam',
						'type'  => 'twitter',
					),
				),
				'websites'        => array(
					array(
						'id'    => 7,
						'value' => 'https://sam.example.org',
					),
				),
				'properties'      => array(
					array(
						'type'  => 'text',
						'slug'  => 'car',
						'name'  => 'Car',
						'value' => 'Tesla',
					),
					array(
						'type' => 'dropdown',
						'slug' => 'user-status',
						'name' => 'User Status',
					),
				),
				'address'         => array(
					'lines'      => array( '1 Main Street', 'Suite 2' ),
					'city'       => 'Prague',
					'postalCode' => '11000',
					'country'    => 'CZ',
				),
			),
		);
	}

	/**
	 * A 2×2 PNG.
	 *
	 * @return string
	 */
	private static function png(): string {
		$image = imagecreatetruecolor( 2, 2 );
		ob_start();
		imagepng( $image );

		return (string) ob_get_clean();
	}
}
