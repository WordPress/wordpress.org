<?php
/**
 * Tests which originals the event translations page renders.
 *
 * @package wporg-gp-translation-events
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use Wporg\Tests\Base_Test as TestCase;
use Wporg\TranslationEvents\Routes\Event\Translations_Route;
use Wporg\TranslationEvents\Tests\Event_Factory;
use Wporg\TranslationEvents\Tests\Stats_Factory;

/**
 * The event translations page applies GlotPress's hidden-priority rule.
 */
class Translations_Route_Test extends TestCase {
	/**
	 * Factory used to create events for the tests.
	 *
	 * @var Event_Factory
	 */
	private Event_Factory $event_factory;

	/**
	 * Factory used to create event action rows for the tests.
	 *
	 * @var Stats_Factory
	 */
	private Stats_Factory $stats_factory;

	/**
	 * Sets up the test case before each test runs.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();
		$this->event_factory = new Event_Factory();
		$this->stats_factory = new Stats_Factory();
	}

	/**
	 * Cleans up after each test.
	 *
	 * @return void
	 */
	public function tearDown(): void {
		$this->stats_factory->clean();
		parent::tearDown();
	}

	/**
	 * Viewers and whether they may see a hidden original.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	public static function data_viewers(): array {
		return array(
			'anonymous visitor'           => array( 'anonymous', false ),
			'translator without write'    => array( 'translator', false ),
			'writer on this project only' => array( 'writer', true ),
		);
	}

	/**
	 * A hidden original translated during an event is shown only to project writers.
	 *
	 * @param string $viewer      Who views the page: 'anonymous', 'translator', or 'writer'.
	 * @param bool   $sees_hidden Whether the hidden original should render.
	 * @return void
	 */
	#[DataProvider( 'data_viewers' )]
	public function test_hidden_original_is_shown_only_to_project_writers( string $viewer, bool $sees_hidden ): void {
		$translator_id = $this->set_normal_user_as_current();
		$event_id      = $this->event_factory->create_active( $this->now );
		$set           = $this->factory->translation_set->create_with_project_and_locale();

		$visible = $this->create_event_translation( $event_id, $set, $translator_id, 'Visible event string', 0 );
		$hidden  = $this->create_event_translation( $event_id, $set, $translator_id, 'Hidden event string', -2 );

		if ( 'anonymous' === $viewer ) {
			wp_set_current_user( 0 );
		} else {
			$viewer_id = $this->factory->user->create();
			if ( 'writer' === $viewer ) {
				GP::$permission->create(
					array(
						'user_id'     => $viewer_id,
						'action'      => 'write',
						'object_type' => 'project',
						'object_id'   => $set->project_id,
					)
				);
			}
			wp_set_current_user( $viewer_id );
		}

		$output = $this->render( get_post( $event_id )->post_name, $set->locale );

		$this->assertStringContainsString( $visible, $output );
		if ( $sees_hidden ) {
			$this->assertStringContainsString( $hidden, $output );
		} else {
			$this->assertStringNotContainsString( $hidden, $output );
		}
	}

	/**
	 * Create an original with a translation and record it against the event.
	 *
	 * @param int                $event_id Event the translation was made in.
	 * @param GP_Translation_Set $set      Translation set to translate into.
	 * @param int                $user_id  Translator.
	 * @param string             $singular Original text.
	 * @param int                $priority Original priority.
	 * @return string The original text.
	 */
	private function create_event_translation( int $event_id, GP_Translation_Set $set, int $user_id, string $singular, int $priority ): string {
		$original = $this->factory->original->create(
			array(
				'project_id' => $set->project_id,
				'status'     => '+active',
				'singular'   => $singular,
				'priority'   => $priority,
			)
		);
		$this->factory->translation->create(
			array(
				'original_id'        => $original->id,
				'translation_set_id' => $set->id,
				'user_id'            => $user_id,
				'status'             => 'current',
			)
		);
		$this->stats_factory->create( $event_id, $user_id, $original->id, 'create', $set->locale );

		return $singular;
	}

	/**
	 * Render the event translations page and return its output.
	 *
	 * @param string $event_slug Event slug.
	 * @param string $locale     Locale slug.
	 * @return string
	 */
	private function render( string $event_slug, string $locale ): string {
		$route               = new Translations_Route();
		$route->fake_request = true;

		ob_start();
		try {
			$route->handle( $event_slug, $locale );
		} catch ( GP_Route_Exit_Exception $e ) {
			$route->exited = true;
		}
		return (string) ob_get_clean();
	}
}
