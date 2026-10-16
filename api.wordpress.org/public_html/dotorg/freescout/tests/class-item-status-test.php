<?php
/**
 * Tests for the status lines of plugins and themes in the sidebar.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

use PHPUnit\Framework\TestCase;

/**
 * Tests for get_item_status().
 */
class Item_Status_Test extends TestCase {

	/**
	 * Published plugins and themes have no status line.
	 *
	 * @return void
	 */
	public function test_published_has_no_status(): void {
		$this->assertSame( array(), get_item_status( 'publish' ) );
	}

	/**
	 * Each status has words, a tone, and but for delisted themes, an icon.
	 *
	 * @dataProvider data_statuses
	 *
	 * @param string $post_status Post status.
	 * @param string $icon        Icon.
	 * @param string $text        Text.
	 * @param string $tone        Tone.
	 * @return void
	 */
	public function test_status( string $post_status, string $icon, string $text, string $tone ): void {
		$this->assertSame(
			array(
				'tone'   => $tone,
				'status' => array_filter(
					array(
						'icon' => $icon,
						'text' => $text,
					)
				),
			),
			get_item_status( $post_status )
		);
	}

	/**
	 * Data provider.
	 *
	 * @return array
	 */
	public static function data_statuses(): array {
		return array(
			'new'       => array( 'new', '⏳', 'Pending initial review', 'warning' ),
			'pending'   => array( 'pending', '🔍', 'In review', 'warning' ),
			'approved'  => array( 'approved', '✅', 'Approved', 'warning' ),
			'closed'    => array( 'closed', '🔒', 'Closed', 'error' ),
			'disabled'  => array( 'disabled', '🔒', 'Disabled', 'error' ),
			'rejected'  => array( 'rejected', '🚫', 'Rejected', 'muted' ),
			'draft'     => array( 'draft', '⏳', 'In review or rejected', 'warning' ),
			'suspended' => array( 'suspend', '⛔', 'Suspended', 'error' ),
			'delisted'  => array( 'delist', '', 'Delisted', 'muted' ),
		);
	}

	/**
	 * A closed or disabled plugin's reason picks its icon.
	 *
	 * @return void
	 */
	public function test_close_reason(): void {
		$this->assertSame(
			array(
				'icon' => '🛡️',
				'text' => 'Closed: Security issue',
			),
			get_item_status( 'closed', '', 'security-issue' )['status']
		);

		$this->assertSame(
			array(
				'icon' => '™️',
				'text' => 'Disabled: Trademark or licensing',
			),
			get_item_status( 'disabled', '', 'licensing-trademark-violation' )['status']
		);
	}

	/**
	 * A reason without an icon is still named.
	 *
	 * @return void
	 */
	public function test_unknown_close_reason(): void {
		$this->assertSame(
			array(
				'icon' => '🔒',
				'text' => 'Closed: Some new reason',
			),
			get_item_status( 'closed', '', 'some-new-reason' )['status']
		);
	}

	/**
	 * The reviewer of a plugin in review follows its status.
	 *
	 * @return void
	 */
	public function test_reviewer(): void {
		$this->assertSame( 'In review · Assigned to Priya Shah', get_item_status( 'pending', 'Priya Shah' )['status']['text'] );
		$this->assertSame( 'Pending initial review · Assigned to Priya Shah', get_item_status( 'new', 'Priya Shah' )['status']['text'] );
	}

	/**
	 * Only reviews have a reviewer: the assignment stays after they're done.
	 *
	 * @return void
	 */
	public function test_no_reviewer_after_review(): void {
		$this->assertSame( 'Approved', get_item_status( 'approved', 'Priya Shah' )['status']['text'] );
	}

	/**
	 * Why a plugin was rejected is in the tooltip.
	 *
	 * @return void
	 */
	public function test_rejection_reason(): void {
		$this->assertSame( 'Duplicate (copy) of another Plugin', get_item_status( 'rejected', '', '', 'Duplicate (copy) of another Plugin' )['status']['tooltip'] );
	}
}
