<?php
namespace WordPressdotorg\Post_Translation;

use Translation_Entry, PO, GP;

/**
 * Imports translatable strings into a GlotPress project.
 *
 * This class handles creating GlotPress projects on-demand and importing
 * PO entries for post content strings.
 */
class Importer {
	protected $project_path;
	protected $project_name;

	/**
	 * @param string      $project_path The GlotPress project path.
	 * @param string|null $project_name Display name for the project when created
	 *                                  on-the-fly. Defaults to the current site's
	 *                                  name. Pass explicitly when importing for a
	 *                                  different blog (e.g. from a cron job).
	 */
	public function __construct( string $project_path, ?string $project_name = null ) {
		$this->project_path = $project_path;
		$this->project_name = $project_name;
	}

	/**
	 * Import strings into the GlotPress project.
	 *
	 * @param string[] $strings   Translatable strings to import.
	 * @param string   $reference URL reference for the source post.
	 * @return array|false Import counts [ added, existing, fuzzied, obsoleted, error ] or false on failure.
	 */
	public function import( array $strings, string $reference ) {
		$this->load_glotpress();

		// load_glotpress() is a no-op if GlotPress isn't installed; bail rather
		// than fatal on the GP::$project reference below.
		if ( ! class_exists( 'GP' ) ) {
			return false;
		}

		$gp_project = GP::$project->by_path( $this->project_path );

		// Create the project on-the-fly if it doesn't exist.
		if ( ! $gp_project ) {
			$gp_project = $this->create_project();

			if ( ! $gp_project ) {
				return false;
			}
		}

		$po = $this->build_po( $strings, $reference, $gp_project );

		$result = GP::$original->import_for_project( $gp_project, $po );

		if ( $result ) {
			// Invalidate frontend translation cache; the group is registered as global
			// in the plugin bootstrap so this is visible across the multisite network.
			wp_cache_set_last_changed( Frontend::CACHE_GROUP );
		}

		return $result;
	}

	/**
	 * Build a PO object from strings.
	 */
	protected function build_po( array $strings, string $reference, $gp_project ): PO {
		require_once ABSPATH . '/wp-includes/pomo/po.php';

		$po = new PO();
		$po->set_header( 'PO-Revision-Date', gmdate( 'Y-m-d H:i:s' ) . '+0000' );
		$po->set_header( 'MIME-Version', '1.0' );
		$po->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		$po->set_header( 'Content-Transfer-Encoding', '8bit' );
		$po->set_header( 'X-Generator', 'wporg-post-translation' );

		$entries = [];

		foreach ( $strings as $string ) {
			if ( isset( $entries[ $string ] ) ) {
				continue;
			}

			$entries[ $string ] = new Translation_Entry( [
				'singular'   => $string,
				'references' => [ $reference ],
			] );
		}

		// Preserve existing originals from other posts that reference this project.
		$this->merge_existing_originals( $entries, $reference, $gp_project );

		foreach ( $entries as $entry ) {
			$po->add_entry( $entry );
		}

		return $po;
	}

	/**
	 * Merge existing GlotPress originals that reference other posts.
	 *
	 * When importing strings for a single post, we need to keep originals
	 * that were imported from other posts in the same project. Without this,
	 * re-importing one post would obsolete strings from other posts.
	 */
	protected function merge_existing_originals( array &$entries, string $current_reference, $gp_project ): void {
		$all_originals = GP::$original->many_no_map(
			'SELECT * FROM ' . GP::$original->table . ' WHERE project_id = %d AND status != %s',
			$gp_project->id,
			'-obsolete'
		);

		foreach ( $all_originals as $original ) {
			$references = array_filter( explode( ' ', $original->references ) );

			// Remove the current post reference; remaining references are from other posts.
			$other_references = array_diff( $references, [ $current_reference ] );

			if ( empty( $other_references ) ) {
				// This original only existed for the current post - let the import handle it.
				continue;
			}

			if ( isset( $entries[ $original->singular ] ) ) {
				// String exists in both the current post and other posts; merge references.
				$entries[ $original->singular ]->references = array_unique(
					array_merge( $entries[ $original->singular ]->references, $other_references )
				);
				sort( $entries[ $original->singular ]->references );
			} else {
				// String only exists in other posts; preserve it.
				$entries[ $original->singular ] = new Translation_Entry( [
					'singular'           => $original->singular,
					'plural'             => $original->plural,
					'context'            => $original->context,
					'extracted_comments' => $original->comment,
					'references'         => $other_references,
				] );
			}
		}
	}

	/**
	 * Create the GlotPress project and its translation sets.
	 */
	protected function create_project() {
		$parent = $this->get_or_create_parent_project();

		if ( ! $parent ) {
			return false;
		}

		$slug = trim( str_replace( PROJECT_BASE, '', $this->project_path ), '/' );
		$name = $this->project_name ?: ( get_bloginfo( 'name' ) ?: $slug );

		$project = GP::$project->create_and_select( [
			'name'              => $name,
			'slug'              => $slug,
			'parent_project_id' => $parent->id,
			'description'       => 'Post content translations for ' . $name,
			'active'            => 1,
		] );

		if ( ! $project ) {
			return false;
		}

		// Copy translation sets from the parent project so all locales are available.
		$parent_sets = (array) GP::$translation_set->by_project_id( $parent->id );

		foreach ( $parent_sets as $set ) {
			GP::$translation_set->create( [
				'project_id' => $project->id,
				'name'       => $set->name,
				'locale'     => $set->locale,
				'slug'       => $set->slug,
			] );
		}

		return $project;
	}

	/**
	 * Get or create the parent "Post Content" project.
	 */
	protected function get_or_create_parent_project() {
		$parent = GP::$project->by_path( PROJECT_BASE );

		if ( $parent ) {
			return $parent;
		}

		return GP::$project->create_and_select( [
			'name'              => 'Post Content',
			'slug'              => PROJECT_BASE,
			'parent_project_id' => null,
			'description'       => 'Translations for WordPress post and page content.',
			'active'            => 1,
		] );
	}

	/**
	 * Ensure GlotPress is loaded.
	 */
	protected function load_glotpress(): void {
		if ( class_exists( 'GP' ) || did_action( 'gp_init' ) ) {
			return;
		}

		if ( defined( 'GLOTPRESS_TABLE_PREFIX' ) ) {
			$GLOBALS['gp_table_prefix'] = GLOTPRESS_TABLE_PREFIX;
		}

		if ( function_exists( 'gp_init' ) ) {
			gp_init();
		}
	}
}
