<?php
/**
 * Tests for who gets Manage » Mailboxes.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Tests;

use App\Mailbox;
use App\User;
use Modules\WPOrgSite\Providers\WPOrgSiteServiceProvider;
use WordPressdotorg\FreeScout\Tests\TestCase;

/**
 * Covers leaving Manage » Mailboxes out for agents who can't manage any mailbox.
 */
final class MailboxMenuTest extends TestCase {

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->app->register( WPOrgSiteServiceProvider::class );
	}

	/**
	 * Only administrators, and agents with access settings for a mailbox, get the menu; everyone keeps the Mailbox
	 * Settings menus of the sidebar and dashboard, which check the same policy before the layout renders.
	 *
	 * @dataProvider data_menu
	 *
	 * @param int   $role      Agent's role.
	 * @param array $access    The agent's access settings for their mailbox.
	 * @param bool  $in_layout Whether the layout is rendering.
	 * @param bool  $expected  Whether they get the menu.
	 * @return void
	 */
	public function test_mailbox_menu( int $role, array $access, bool $in_layout, bool $expected ): void {
		$user = $this->create_user( $role );
		$user->mailboxes()->attach( $this->create_mailbox()->id, array( 'access' => $access ? json_encode( $access ) : null ) );
		if ( $in_layout ) {
			\View::callComposer( view( 'layouts.app' ) );
		}

		$this->assertSame( $expected, $user->fresh()->can( 'viewMailboxMenu', $user ) );
	}

	/**
	 * Agents, and whether they get the menu.
	 *
	 * @return array
	 */
	public function data_menu(): array {
		return array(
			'administrator'                        => array( User::ROLE_ADMIN, array(), true, true ),
			'user who manages a mailbox'           => array( User::ROLE_USER, array( Mailbox::ACCESS_PERM_EDIT ), true, true ),
			'user who manages no mailbox'          => array( User::ROLE_USER, array(), true, false ),
			'user who manages no mailbox, sidebar' => array( User::ROLE_USER, array(), false, true ),
		);
	}
}
