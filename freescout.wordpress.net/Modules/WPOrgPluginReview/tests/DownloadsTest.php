<?php
/**
 * Tests for the header's button that copies the download URLs of the plugins open.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use App\User;
use Modules\WPOrgPluginReview\Providers\WPOrgPluginReviewServiceProvider;
use Modules\WPOrgPluginReview\Services\Reviewers;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers who gets the button, which downloads.js shows and fills in the browser.
 */
final class DownloadsTest extends TestCase {

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgPluginReviewServiceProvider::class );
	}

	/**
	 * Clears the modules' cache, which outlives the test's database.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		\App\Module::clearModulesCache();

		parent::tearDown();
	}

	/**
	 * Members of the plugins team's teams get the button, hidden until there are URLs, and its script.
	 *
	 * @return void
	 */
	public function test_reviewers_get_the_button(): void {
		$this->create_mailbox( 'Plugins', 'plugins@wordpress.test' );
		$this->actingAs( $this->create_user() );

		$html = $this->header();

		$this->assertStringContainsString( '<li class="wporg-downloads hidden"', $html );
		$this->assertStringContainsString( 'class="wporg-downloads-copy"', $html );
		$this->assertStringContainsString( 'class="wporg-downloads-clear"', $html );
		$this->assertStringContainsString( 'Copy :count URLs', html_entity_decode( $html ) );

		$scripts = implode( ' ', (array) \Eventy::filter( 'javascripts', array() ) );
		$this->assertStringContainsString( '/js/downloads.js', $scripts );
	}

	/**
	 * Members of other teams don't.
	 *
	 * @return void
	 */
	public function test_others_dont(): void {
		$this->actingAs( $this->create_user() );

		\App\Module::clearModulesCache();
		\App\Module::setActive( Reviewers::TEAMS_MODULE, true );
		\App\Module::clearModulesCache();
		$this->app->instance(
			Reviewers::class,
			new class() extends Reviewers {
				/**
				 * The names of the teams a user is a member of.
				 *
				 * @param User $user User.
				 * @return string[]
				 */
				protected function team_names( User $user ): array {
					return array( 'Themes' );
				}
			}
		);

		$this->assertSame( '', $this->header() );
		$this->assertStringNotContainsString( 'wporgpluginreview', implode( ' ', (array) \Eventy::filter( 'javascripts', array() ) ) );
	}

	/**
	 * What the module adds to the header's menu.
	 *
	 * @return string
	 */
	private function header(): string {
		ob_start();
		\Eventy::action( 'menu.append' );

		return trim( (string) ob_get_clean() );
	}
}
