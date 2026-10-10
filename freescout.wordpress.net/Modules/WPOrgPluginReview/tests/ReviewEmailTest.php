<?php
/**
 * Tests for reading review emails.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Tests;

use Modules\WPOrgPluginReview\Services\IssueNames;
use Modules\WPOrgPluginReview\Services\Review;
use Modules\WPOrgPluginReview\Services\ReviewEmail;
use PHPUnit\Framework\TestCase;

/**
 * Covers what's read from a review email's HTML.
 */
final class ReviewEmailTest extends TestCase {

	/**
	 * A review email, as the review tools write them: the Author URI as text, and the Plugin URI as the link an email
	 * client may have made of it.
	 *
	 * @var string
	 */
	private const EMAIL = '<p>Hi,</p>
		<ul>
			<li>Plugin Name: My Plugin</li>
			<li>Author URI: https://www.example.co.uk/about/ 🟧 The URL doesn’t answer.</li>
			<li>Plugin URI: <a href="https://plugins.example.org/my-plugin">https://plugins.example.org/my-plugin</a></li>
			<li>Our suggested alternative name: <code>Example Kit</code></li>
			<li>Our suggested alternative slug: <strong>example-kit</strong></li>
		</ul>
		<br><br><h3>🔴&#xFE0F; Data Must be Sanitized, Escaped, and Validated</h3><br>
		<p>Some details.</p>
		<h3>🟡 Use wp_enqueue commands</h3>
		<p>## Allowing Direct File Access to plugin files</p>
		<h3>🔴 Proper sanitization of inputs</h3>
		<h3>🔴 Trialware and license checks are not permitted</h3>
		<h3>🟡 A warning without a short name</h3>
		<h3>🔴 An issue without a short name</h3>
		<p>Review ID: R ❗TRM my-plugin/jane/1Aug26/T2 8Aug26/4.3 (P0TDX42HGN)</p>';

	/**
	 * The issues come in the email's order, by their short names, each once; issues without one keep their titles, and
	 * warnings aren't issues.
	 *
	 * @return void
	 */
	public function test_lists_issues_by_their_short_names(): void {
		$this->assertSame(
			array(
				array(
					'title'    => 'Data Must be Sanitized, Escaped, and Validated',
					'name'     => 'Sanitizing',
					'priority' => IssueNames::PRIORITY_LOW,
				),
				array(
					'title'    => 'Allowing Direct File Access to plugin files',
					'name'     => 'Direct File Access',
					'priority' => IssueNames::PRIORITY_LOW,
				),
				array(
					'title'    => 'Trialware and license checks are not permitted',
					'name'     => 'Trialware and Locked Features',
					'priority' => IssueNames::PRIORITY_HIGH,
				),
				array(
					'title'    => 'An issue without a short name',
					'name'     => 'An issue without a short name',
					'priority' => null,
				),
			),
			( new ReviewEmail( self::EMAIL ) )->issues()
		);
	}

	/**
	 * The suggested names and the declared URLs are read from their list items.
	 *
	 * @return void
	 */
	public function test_reads_suggestions_and_urls(): void {
		$email = new ReviewEmail( self::EMAIL );

		$this->assertSame( 'Example Kit', $email->suggested( 'name' ) );
		$this->assertSame( 'example-kit', $email->suggested( 'slug' ) );
		$this->assertSame( 'https://www.example.co.uk/about/', $email->declared_url( 'Author URI' ) );
		$this->assertSame( 'https://plugins.example.org/my-plugin', $email->declared_url( 'Plugin URI' ) );
		$this->assertSame( '', $email->declared_url( 'License URI' ) );
		$this->assertSame( 42, $email->review_id()['plugin_id'] );
	}

	/**
	 * A URL written as text is read up to where it ends, without the punctuation of the sentence it's in.
	 *
	 * @return void
	 */
	public function test_reads_urls_written_as_text(): void {
		$email = new ReviewEmail( '<ul><li>Author URI: (see https://example.org/me).</li><li>Plugin URI: none</li><li>Donate link: https://example.org/</li></ul>' );

		$this->assertSame( 'https://example.org/me', $email->declared_url( 'Author URI' ) );
		$this->assertSame( '', $email->declared_url( 'Plugin URI' ) );
	}

	/**
	 * A plain text body's lines end with line breaks, not elements.
	 *
	 * @return void
	 */
	public function test_reads_plain_text_bodies_line_by_line(): void {
		$email = new ReviewEmail( "Hi,\n\n## Use wp_enqueue commands\nSome details.\n\nReview ID: R my-plugin/jane/1Aug26/T2 8Aug26/4.3 (P0TDX42HGN)\n" );

		$this->assertSame( 'R', $email->review_id()['type'] );
		$this->assertSame( array( 'Enqueue' ), array_column( $email->issues(), 'name' ) );

		// In HTML, a line break in the source is a space.
		$this->assertNull( ( new ReviewEmail( "<p>Thanks.\nReview ID: R my-plugin/jane 8Aug26/4.3</p>" ) )->review_id() );
	}

	/**
	 * Quoted text isn't read, so an author's reply that quotes the review isn't one.
	 *
	 * @return void
	 */
	public function test_leaves_quoted_text_out(): void {
		$reply = '<p>Done, thanks!</p><blockquote>' . self::EMAIL . '</blockquote>';
		$email = new ReviewEmail( $reply );

		$this->assertNull( $email->review_id() );
		$this->assertSame( array(), $email->issues() );
		$this->assertNull( ( new ReviewEmail( '<div class="gmail_quote">' . self::EMAIL . '</div>' ) )->review_id() );
	}

	/**
	 * Of several Review ID lines, the last one is the email's.
	 *
	 * @return void
	 */
	public function test_takes_the_last_review_id(): void {
		$email = new ReviewEmail( '<p>Review ID: F1 my-plugin/jane 1Aug26/4.2</p><p>Review ID: R my-plugin/jane 8Aug26/4.3</p>' );

		$this->assertSame( 'R', $email->review_id()['type'] );
	}

	/**
	 * An upload confirmation isn't a review email, even when the author's comment in it is a Review ID line.
	 *
	 * @return void
	 */
	public function test_upload_confirmation_has_no_review_id(): void {
		$email = new ReviewEmail( '<p>' . Review::UPLOAD_CONFIRMATION . '</p><p>File updated by jane, version 4.4.</p><p>Comment: Review ID: APPROVED my-plugin/jane 8Aug26/4.4</p>' );

		$this->assertNull( $email->review_id() );

		// Nor when the comment has lines of its own.
		$email = new ReviewEmail( '<p>' . Review::UPLOAD_CONFIRMATION . '</p><p>Comment: thanks<br>Review ID: APPROVED my-plugin/jane 8Aug26/4.4 (P0TDX42HGN)</p>' );

		$this->assertNull( $email->review_id() );
	}
}
