<?php
/**
 * Tests for reading Review ID lines.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use Modules\WPOrgPluginReview\Services\ReviewId;
use PHPUnit\Framework\TestCase;

/**
 * Covers taking Review ID lines apart, with any of their pieces missing.
 */
final class ReviewIdTest extends TestCase {

	/**
	 * A complete line gives every piece.
	 *
	 * @return void
	 */
	public function test_reads_every_piece(): void {
		$this->assertSame(
			array(
				'type'      => 'AUTO-SVN',
				'flags'     => array( 'TRM', 'OWN' ),
				'slug'      => 'acme-shipping-rates',
				'username'  => 'acmeshipping',
				'started'   => '29Jul26',
				'reviews'   => 3,
				'reviewed'  => '2Aug26',
				'version'   => '4.2A2',
				'plugin_id' => 98765,
			),
			ReviewId::parse( 'Review ID: AUTO-SVN ❗TRM-OWN acme-shipping-rates/acmeshipping/29Jul26/T3 2Aug26/4.2A2 (P0TDX98765HGN)' )
		);
	}

	/**
	 * A missing piece only costs its own value.
	 *
	 * @return void
	 */
	public function test_reads_lines_with_pieces_missing(): void {
		$review_id = ReviewId::parse( 'Review ID: SVN order-status-tools-for-shops/29Jul26/T1 29Jul26/4.2A2' );

		$this->assertSame( 'SVN', $review_id['type'] );
		$this->assertSame( array(), $review_id['flags'] );
		$this->assertSame( 'order-status-tools-for-shops', $review_id['slug'] );
		$this->assertSame( '', $review_id['username'] );
		$this->assertSame( '29Jul26', $review_id['started'] );
		$this->assertSame( 1, $review_id['reviews'] );
		$this->assertSame( '29Jul26', $review_id['reviewed'] );
		$this->assertSame( 0, $review_id['plugin_id'] );

		// TX: the number of reviews wasn't known.
		$this->assertNull( ReviewId::parse( 'Review ID: R my-plugin/jane/TX 1Aug26/4.2' )['reviews'] );

		// Without a date, the last piece is still the date and version, unless it's the only one.
		$review_id = ReviewId::parse( 'Review ID: R my-plugin/jane ?/4.2' );
		$this->assertSame( array( 'my-plugin', 'jane', '?', '4.2' ), array( $review_id['slug'], $review_id['username'], $review_id['reviewed'], $review_id['version'] ) );
		$this->assertSame( 'my-plugin', ReviewId::parse( 'Review ID: R my-plugin' )['slug'] );
	}

	/**
	 * Flags can be glued to their mark or not, follow several marks, and come with the mark's variation selector.
	 *
	 * @return void
	 */
	public function test_reads_flags_however_they_are_written(): void {
		$this->assertSame( array( 'TRM' ), ReviewId::parse( 'Review ID: F1 ❗ TRM my-plugin/jane 1Aug26/4.2' )['flags'] );
		$this->assertSame( array( 'OWN', 'LIC' ), ReviewId::parse( 'Review ID: OWN ❗OWN ❗LIC my-plugin/jane 1Aug26/4.2' )['flags'] );
		$this->assertSame( array( 'FUN', 'ACT' ), ReviewId::parse( "Review ID: R \u{2757}\u{FE0F}FUN-ACT my-plugin/jane 1Aug26/4.2" )['flags'] );
	}

	/**
	 * No-break spaces, which emails are full of, separate pieces like spaces.
	 *
	 * @return void
	 */
	public function test_reads_no_break_spaces_as_spaces(): void {
		$this->assertSame( 'my-plugin', ReviewId::parse( "Review ID:\u{00A0}R\u{00A0}my-plugin/jane\u{00A0}1Aug26/4.2" )['slug'] );
	}

	/**
	 * "Review:", which the emails following up on a review write, only counts with one of their types.
	 *
	 * @return void
	 */
	public function test_reads_review_lines_only_for_follow_ups(): void {
		$this->assertSame( 'WRONGFORMAT', ReviewId::parse( 'Review: WRONGFORMAT my-plugin/jane 1Aug26/4.2' )['type'] );
		$this->assertSame( 'CHANGESNOTMADE', ReviewId::parse( 'Review: CHANGESNOTMADE ❗TRM my-plugin/jane/1Aug26/T3 9Aug26/4.3' )['type'] );
		$this->assertTrue( ReviewId::is_follow_up( 'CHANGESNOTMADE' ) );
		$this->assertFalse( ReviewId::is_follow_up( 'R' ) );

		$this->assertNull( ReviewId::parse( 'Review: Thanks for the quick review!' ) );
		$this->assertNull( ReviewId::parse( 'Review: I fixed the issues' ) );
		$this->assertNull( ReviewId::parse( 'Review: R my-plugin/jane 1Aug26/4.2' ) );
		$this->assertNull( ReviewId::parse( 'Review ID:' ) );
		$this->assertNull( ReviewId::parse( 'Please include the Review ID: R in your reply' ) );
	}

	/**
	 * What a review wrote it didn't know is read as missing, in its place.
	 *
	 * @return void
	 */
	public function test_reads_unknown_pieces_as_missing(): void {
		$review_id = ReviewId::parse( 'Review ID: GANDALFRW unknown/unknown/unknown/TX unknown/unknown' );
		$this->assertSame(
			array( '', '', '', null, '', '' ),
			array( $review_id['slug'], $review_id['username'], $review_id['started'], $review_id['reviews'], $review_id['reviewed'], $review_id['version'] )
		);

		$review_id = ReviewId::parse( 'Review ID: GANDALFRW unknown/acmeforms/3Aug26/TX 4Aug26/2.1' );
		$this->assertSame( array( '', 'acmeforms', '3Aug26' ), array( $review_id['slug'], $review_id['username'], $review_id['started'] ) );
	}
}
