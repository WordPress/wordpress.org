<?php

namespace WordPressdotorg\Post_Translation;
use Translation_Entry, PO;
use GP;

class MakePot {
	public $project;
	public $posts;

	public function __construct( string $project, array $posts ) {
		$this->project = $project;
		$this->posts   = $posts;
	}

	/**
	 * Create a translation project for a site..
	 */
	public function create_translation_project_for_current_site() {
		$project_args = array(
			'name'              => get_blog_details()->blogname,
			'slug'              => trim( str_replace( PROJECT_BASE, '', $this->project ), '/' ),
			'parent_project_id' => 0,
			'description'       => 'Strings for ' . home_url('/'),
			'active'            => 1,
		);

		// Load GlotPress for the API.
		switch_to_blog( WPORG_TRANSLATE_BLOGID );
		$this->load_glotpress();

		$parent = GP::$project->by_path( PROJECT_BASE );

		$project_args['parent_project_id'] = $parent->id;

		$project = GP::$project->create_and_select( $project_args );

		// Import translation sets.
		if ( $project ) {
			$translation_sets = (array) GP::$translation_set->by_project_id(
				GP::$project->by_path( PROJECT_INHERIT_SETS )->id
			);

			foreach ( $translation_sets as $set ) {
				echo sprintf( 'Creating translation set %s (%s)', $set->name, $set->locale . ( $set->slug != 'default' ? '/' . $set->slug : '' ) );

				GP::$translation_set->create( array(
					'project_id' => $project->id,
					'name'       => $set->name,
					'locale'     => $set->locale,
					'slug'       => $set->slug,
				) );
			}
		}

		// Switch back
		restore_current_blog();

		return (bool) $project;
	}

	public function import( $save = false ) {
		// Avoid attempting to import strings when no patterns are found.
		// This is a precautionary check to ensure we don't accidentally remove all translations.
		if ( empty( $this->posts ) ) {
			return 'No posts found: skipping import.';
		}

		// Load GlotPress for the API.
		switch_to_blog( WPORG_TRANSLATE_BLOGID );
		$this->load_glotpress();

		$this->project_obj = GP::$project->by_path( $this->project );

		// Switch back, so we can create proper referenced originals.
		restore_current_blog();

		// Create the project on-the-fly for the current site.
		if ( ! $this->project_obj ) {
			$this->create_translation_project_for_current_site();

			$this->project_obj = GP::$project->by_path( $this->project );
		}

		if ( ! $this->project_obj ) {
			return 'Project not found!';
		}

		$po = $this->makepo();

		if ( true !== $save ) {
			var_dump( $po );

			return sprintf( 'dry-run: %s translations would be imported into %s', count( $po->entries ), $this->project );
		}

		// Switch to GlotPress to import..
		switch_to_blog( WPORG_TRANSLATE_BLOGID );

		list( $added, $existing, $fuzzied, $obsoleted, $error ) = GP::$original->import_for_project( $this->project_obj, $po );

		$notice = sprintf(
			'%s: %s new strings added, %s updated, %s fuzzied, and %s obsoleted.',
			$this->project,
			$added,
			$existing,
			$fuzzied,
			$obsoleted
		);

		if ( $error ) {
			$notice .= ' ' . sprintf(
				'%s new string(s) were not imported due to an error.',
				$error
			);
		}

		restore_current_blog();

		return $notice;
	}

	public function makepo( $revision_time = null ) : \PO {
		require_once ABSPATH . '/wp-includes/pomo/po.php';

		$po = new PO();

		$po->set_header( 'PO-Revision-Date', gmdate( 'Y-m-d H:i:s', $revision_time ?? time() ) . '+0000' );
		$po->set_header( 'MIME-Version', '1.0' );
		$po->set_header( 'Content-Type', 'text/plain; charset=UTF-8' );
		$po->set_header( 'Content-Transfer-Encoding', '8bit' );
		$po->set_header( 'X-Generator', 'wporg_post_translation_makepot' );

		foreach ( $this->entries() as $entry ) {
			$po->add_entry( $entry );
		}

		return $po;
	}

	public function entries() : array {
		$entries = [];

		$import_references = array_map( 'get_permalink', $this->posts );

		foreach ( $this->posts as $post ) {

			$reference = get_permalink( $post );
			$strings   = Post_Parser::post_to_strings( $post );

			foreach ( $strings as $string ) {
				if ( ! isset( $entries[ $string ] ) ) {
					$entries[ $string ] = new Translation_Entry(
						[
							'singular' => $string,
							'references' => [
								$reference,
							],
						]
					);
				} elseif ( ! in_array( $url, $entries[ $string ]->references ) ) {
					$entries[ $string ]->references[] = $url;

					sort( $entries[ $string ]->references );
				}
			}
		}

		// Now add any originals with references that we haven't imported.
		$all_originals = GP::$original->many_no_map( 'SELECT * FROM ' . GP::$original->table . ' WHERE project_id = %d AND status != "-obsolete"', $this->project_obj->id );

		foreach ( $all_originals as $original ) {
			$original_references = array_diff( explode( ' ', $original->references ), $import_references );
			// If the only references were to one of the posts being imported now, skip, that string will be obsoleted.
			if ( empty( $original_references ) ) {
				continue;
			}

			// Add new, or add reference to existing string.
			// We're taking a shortcut here, as we're using `singular` as the key, rather than `{context}\004{singular}({plural})?`
			if ( ! isset( $entries[ $original->singular ] ) ) {
				$entries[ $original->singular ] = new Translation_Entry(
					[
						'singular'           => $original->singular,
						'plural'             => $original->plural,
						'context'            => $original->context,
						'extracted_comments' => $original->comment,
						'references'         => $original_references,
					]
				);
			} else {
				// String is set, make sure it has the originals references.
				$entries[ $original->singular ]->references = array_merge( $entries[ $string ]->references, $original_references );

				sort( $entries[ $original->singular ]->references );
			}
		}

		return array_values( $entries );
	}

	/**
	 * Load GlotPress so that we can interact with the GlotPress APIs.
	 */
	public function load_glotpress() {
		if ( did_action( 'gp_init' ) ) {
			return;
		}

		// TODO: Figure out how to properly do the following stuff.
		// This might be better be done as a two-part process;
		// 1. Generate the partial .po here on the individual sites
		// 2. POST it to translate.w.org, have it handle ensuring the originals unrelated to this reference are set.
		// 3. Profit, by not loading GlotPress & it's plugins on random sites.

		$GLOBALS['gp_table_prefix'] = GLOTPRESS_TABLE_PREFIX;

		// Load any GlotPress plugins as needed.
		$plugins = get_option( 'active_plugins', [] );
		array_walk( $plugins, function( $plugin ) {
			include_once trailingslashit( WP_PLUGIN_DIR ) . $plugin;
		} );

		// Run the GlotPress init routines.
		if ( ! did_action( 'gp_init' ) ) {
			gp_init();
		}
	}
}
