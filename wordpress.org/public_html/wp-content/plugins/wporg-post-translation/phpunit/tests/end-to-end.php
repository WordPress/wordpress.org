<?php
/**
 * End-to-end tests for the full translation pipeline.
 *
 * Requires GlotPress to be installed and activated.
 *
 * @group e2e
 */

require_once WPORG_POST_TRANSLATION_PLUGIN_DIR . '/inc/class-importer.php';

use WordPressdotorg\Post_Translation\{Post_Parser, Importer, Frontend};
use function WordPressdotorg\Post_Translation\{get_site_project, get_translation_project};

class Test_End_To_End extends WP_UnitTestCase {

	protected static $parent_project;

	/**
	 * Skip all tests if GlotPress is not available.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'GP' ) ) {
			$this->markTestSkipped( 'GlotPress is not installed.' );
		}
	}

	/**
	 * Create the parent GlotPress project that child projects inherit from.
	 */
	public static function wpSetUpBeforeClass( $factory ) {
		if ( ! class_exists( 'GP' ) ) {
			return;
		}

		// Create the parent "Post Content" project.
		self::$parent_project = GP::$project->create( [
			'name'              => 'Post Content',
			'slug'              => 'post-content',
			'parent_project_id' => null,
			'active'            => 1,
		] );

		// Create a translation set for testing (Spanish).
		GP::$translation_set->create( [
			'project_id' => self::$parent_project->id,
			'name'       => 'Spanish',
			'locale'     => 'es',
			'slug'       => 'default',
		] );
	}

	/**
	 * Test importing strings from a post into GlotPress.
	 */
	public function test_import_creates_originals() {
		$post_id = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'About Us',
			'post_content' => '<!-- wp:paragraph --><p>Welcome to our site.</p><!-- /wp:paragraph -->',
		] );
		update_post_meta( $post_id, '_post_translation_enabled', true );

		$project  = get_translation_project( $post_id );
		$strings  = Post_Parser::post_to_strings( get_post( $post_id ) );
		$importer = new Importer( $project );
		$result   = $importer->import( $strings, get_permalink( $post_id ) );

		$this->assertIsArray( $result, 'Import should return an array of counts.' );

		// Verify the originals exist in GlotPress.
		$gp_project = GP::$project->by_path( $project );
		$this->assertNotNull( $gp_project, 'GlotPress project should have been created.' );

		$originals        = GP::$original->by_project_id( $gp_project->id );
		$singular_strings = wp_list_pluck( $originals, 'singular' );

		$this->assertContains( 'About Us', $singular_strings );
		$this->assertContains( 'Welcome to our site.', $singular_strings );
	}

	/**
	 * Test that re-importing a post updates originals without losing others.
	 */
	public function test_reimport_preserves_other_post_strings() {
		// Import first post.
		$post1 = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Page One',
			'post_content' => '<!-- wp:paragraph --><p>First page content.</p><!-- /wp:paragraph -->',
		] );
		update_post_meta( $post1, '_post_translation_enabled', true );

		$project   = get_translation_project( $post1 );
		$strings1  = Post_Parser::post_to_strings( get_post( $post1 ) );
		$importer1 = new Importer( $project );
		$importer1->import( $strings1, get_permalink( $post1 ) );

		// Import second post into the same project.
		$post2 = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Page Two',
			'post_content' => '<!-- wp:paragraph --><p>Second page content.</p><!-- /wp:paragraph -->',
		] );
		update_post_meta( $post2, '_post_translation_enabled', true );

		$strings2  = Post_Parser::post_to_strings( get_post( $post2 ) );
		$importer2 = new Importer( $project );
		$importer2->import( $strings2, get_permalink( $post2 ) );

		// Verify both posts' strings are present.
		$gp_project       = GP::$project->by_path( $project );
		$originals        = GP::$original->by_project_id( $gp_project->id );
		$singular_strings = wp_list_pluck( $originals, 'singular' );

		$this->assertContains( 'Page One', $singular_strings, 'First post title should still exist.' );
		$this->assertContains( 'First page content.', $singular_strings, 'First post content should still exist.' );
		$this->assertContains( 'Page Two', $singular_strings );
		$this->assertContains( 'Second page content.', $singular_strings );
	}

	/**
	 * Test the full round-trip: import -> translate -> display.
	 */
	public function test_full_translation_round_trip() {
		// 1. Create and import a post.
		$post_id = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Hello World',
			'post_content' => '<!-- wp:paragraph --><p>This is a test.</p><!-- /wp:paragraph -->',
		] );
		update_post_meta( $post_id, '_post_translation_enabled', true );

		$project  = get_translation_project( $post_id );
		$strings  = Post_Parser::post_to_strings( get_post( $post_id ) );
		$importer = new Importer( $project );
		$importer->import( $strings, get_permalink( $post_id ) );

		// 2. Add a Spanish translation in GlotPress.
		$gp_project = GP::$project->by_path( $project );
		$this->assertNotNull( $gp_project );

		$translation_set = GP::$translation_set->by_project_id_slug_and_locale(
			$gp_project->id,
			'default',
			'es'
		);
		$this->assertNotNull( $translation_set, 'Spanish translation set should exist.' );

		// Find the original for "This is a test."
		$originals     = GP::$original->by_project_id( $gp_project->id );
		$test_original = null;
		foreach ( $originals as $original ) {
			if ( 'This is a test.' === $original->singular ) {
				$test_original = $original;
				break;
			}
		}
		$this->assertNotNull( $test_original, 'Original string should exist in GlotPress.' );

		// Add the translation.
		GP::$translation->create( [
			'original_id'        => $test_original->id,
			'translation_set_id' => $translation_set->id,
			'translation_0'      => 'Esto es una prueba.',
			'status'             => 'current',
			'user_id'            => 1,
		] );

		// 3. Verify the bridge can fetch the translation.
		// Switch locale to Spanish.
		switch_to_locale( 'es_ES' );

		$translated = \GlotPress_Translate_Bridge::translate( 'This is a test.', $project, null, $found );

		$this->assertTrue( $found, 'Bridge should find the translation.' );
		$this->assertEquals( 'Esto es una prueba.', $translated );

		// 4. Verify the frontend translates block content.
		$parser             = new Post_Parser();
		$translated_content = $parser->translate_content(
			get_post( $post_id )->post_content,
			function ( $string ) use ( $project ) {
				return \GlotPress_Translate_Bridge::translate( $string, $project );
			}
		);

		$this->assertStringContainsString( 'Esto es una prueba.', $translated_content );
		$this->assertStringNotContainsString( 'This is a test.', $translated_content );

		restore_previous_locale();
	}

	/**
	 * Test that untranslated strings pass through unchanged.
	 */
	public function test_untranslated_strings_unchanged() {
		$post_id = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Untranslated Page',
			'post_content' => '<!-- wp:paragraph --><p>No translation here.</p><!-- /wp:paragraph -->',
		] );
		update_post_meta( $post_id, '_post_translation_enabled', true );

		$project  = get_translation_project( $post_id );
		$strings  = Post_Parser::post_to_strings( get_post( $post_id ) );
		$importer = new Importer( $project );
		$importer->import( $strings, get_permalink( $post_id ) );

		// Switch to Spanish but don't add any translations.
		switch_to_locale( 'es_ES' );

		$parser = new Post_Parser();
		$result = $parser->translate_content(
			get_post( $post_id )->post_content,
			function ( $string ) use ( $project ) {
				return \GlotPress_Translate_Bridge::translate( $string, $project );
			}
		);

		// Should return false (no translations applied).
		$this->assertFalse( $result );

		restore_previous_locale();
	}

	/**
	 * Test importing a post with complex nested blocks.
	 */
	public function test_import_complex_blocks() {
		$content = '<!-- wp:columns --><div class="wp-block-columns">'
			. '<!-- wp:column --><div class="wp-block-column">'
			. '<!-- wp:heading --><h2>Features</h2><!-- /wp:heading -->'
			. '<!-- wp:list --><ul><li>Fast</li><li>Secure</li></ul><!-- /wp:list -->'
			. '</div><!-- /wp:column -->'
			. '<!-- wp:column --><div class="wp-block-column">'
			. '<!-- wp:paragraph --><p>Learn more about us.</p><!-- /wp:paragraph -->'
			. '</div><!-- /wp:column -->'
			. '</div><!-- /wp:columns -->';

		$post_id = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Complex Page',
			'post_content' => $content,
		] );
		update_post_meta( $post_id, '_post_translation_enabled', true );

		$project  = get_translation_project( $post_id );
		$strings  = Post_Parser::post_to_strings( get_post( $post_id ) );
		$importer = new Importer( $project );
		$result   = $importer->import( $strings, get_permalink( $post_id ) );

		$gp_project       = GP::$project->by_path( $project );
		$originals        = GP::$original->by_project_id( $gp_project->id );
		$singular_strings = wp_list_pluck( $originals, 'singular' );

		$this->assertContains( 'Complex Page', $singular_strings );
		$this->assertContains( 'Features', $singular_strings );
		$this->assertContains( 'Fast', $singular_strings );
		$this->assertContains( 'Secure', $singular_strings );
		$this->assertContains( 'Learn more about us.', $singular_strings );
	}

	/**
	 * Test that the GlotPress project is auto-created with translation sets.
	 */
	public function test_project_auto_created_with_sets() {
		$post_id = self::factory()->post->create( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Auto Project',
			'post_content' => '<!-- wp:paragraph --><p>Test.</p><!-- /wp:paragraph -->',
		] );
		update_post_meta( $post_id, '_post_translation_enabled', true );

		$project  = get_translation_project( $post_id );
		$strings  = Post_Parser::post_to_strings( get_post( $post_id ) );
		$importer = new Importer( $project );
		$importer->import( $strings, get_permalink( $post_id ) );

		$gp_project = GP::$project->by_path( $project );
		$this->assertNotNull( $gp_project );

		// Should have inherited the Spanish translation set from the parent.
		$sets    = GP::$translation_set->by_project_id( $gp_project->id );
		$locales = wp_list_pluck( $sets, 'locale' );

		$this->assertContains( 'es', $locales, 'Spanish set should be inherited from parent.' );
	}
}
