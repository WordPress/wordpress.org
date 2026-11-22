<?php
/**
 * Tests that a string from a plugin's code project translates whole or not at all.
 *
 * @package WordPressdotorg\Plugin_Directory\Tests
 */

declare( strict_types = 1 );

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use WordPressdotorg\Plugin_Directory\Plugin_I18n;

/**
 * Tests for `Plugin_I18n::translate()` with `code_i18n`.
 *
 * @group i18n
 */
#[Group( 'i18n' )]
class Plugin_I18n_Code_Strings_Test extends TestCase {

	/**
	 * IDs of posts created during a test, deleted again on teardown.
	 *
	 * @var int[]
	 */
	protected $post_ids = array();

	/**
	 * Removes the posts the test created.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		foreach ( $this->post_ids as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		$this->post_ids = array();

		parent::tearDown();
	}

	/**
	 * Data provider for {@see test_code_string_translates_whole_or_not_at_all()}.
	 *
	 * @return array<string, array{0: array<int, string>, 1: string}>
	 */
	public static function translations_provider(): array {
		return array(
			'whole string translated'    => array(
				array(
					1 => 'Formulaire',
					2 => 'Formulaire de contact',
				),
				'Formulaire de contact',
			),
			'only a fragment translated' => array(
				array( 1 => 'Formulaire' ),
				'Contact Form',
			),
		);
	}

	/**
	 * A block title is one original in the code project, never a sentence built from several.
	 *
	 * @param array<int, string> $translations GlotPress translations, keyed by original ID.
	 * @param string             $expected     The title the block list may display.
	 * @return void
	 */
	#[DataProvider( 'translations_provider' )]
	public function test_code_string_translates_whole_or_not_at_all( array $translations, string $expected ): void {
		$slug      = 'fixture-' . strtolower( wp_generate_password( 8, false ) );
		$plugin_id = wp_insert_post(
			array(
				'post_type'         => 'plugin',
				'post_title'        => $slug,
				'post_name'         => $slug,
				'post_status'       => 'publish',
				'post_modified'     => current_time( 'mysql' ),
				'post_modified_gmt' => current_time( 'mysql', 1 ),
			),
			true
		);

		$this->assertIsInt( $plugin_id );
		$this->post_ids[] = $plugin_id;
		update_post_meta( $plugin_id, 'stable_tag', '1.0' );

		// The code project as GlotPress would return it, seeded where translate() looks first.
		$i18n = Plugin_I18n::instance();
		$i18n->cache_set( $slug, 'stable', 10, 'branch_id' );
		$i18n->cache_set(
			$slug,
			'stable',
			array(
				1 => 'Form',
				2 => 'Contact Form',
			),
			'originals'
		);
		$i18n->cache_set( $slug, 'stable', 20, 'fr:translation_set_id' );
		$i18n->cache_set( $slug, 'stable', $translations, 'translations:20' );

		$this->assertSame(
			$expected,
			$i18n->translate(
				'block_title:' . md5( 'Contact Form' ),
				'Contact Form',
				array(
					'post_id'   => $plugin_id,
					'locale'    => 'fr_FR',
					'code_i18n' => true,
				)
			)
		);
	}
}
