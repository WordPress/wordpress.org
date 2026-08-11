<?php
/**
 * Project pages for the Meta project family.
 *
 * @package wporg-gp-routes
 */

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP;
use GP_Locales;
use GP_Route;

/**
 * Project pages for the Meta project family.
 *
 * Identical in output to GlotPress's own project page, with one difference:
 * each locale links to its contributors page under /locale/ rather than
 * straight into the editor. Those pages already exist and already render for
 * Meta projects -- nothing linked to them, which is what this route fixes.
 *
 * Meta projects are leaves rather than containers, so this cannot reuse
 * WP_Plugins::get_plugin_projects(): that builds its table from child projects
 * (dev, stable, ...), and a Meta project has none. The numbers here come from
 * the project's own translation sets, the same source GlotPress uses.
 *
 * @see https://meta.trac.wordpress.org/ticket/8396
 */
class Meta extends GP_Route {

	/**
	 * Prints the project page for a Meta project.
	 *
	 * @param string $project_slug Slug of a project below meta/.
	 */
	public function get_meta_projects( $project_slug ) {
		$project = GP::$project->by_path( 'meta/' . $project_slug );

		if ( ! $project ) {
			return $this->die_with_404();
		}

		$sub_projects     = $project->sub_projects();
		$translation_sets = GP::$translation_set->by_project_id( $project->id );

		foreach ( $translation_sets as $set ) {
			$locale = GP_Locales::by_slug( $set->locale );

			$set->name_with_locale   = $set->name_with_locale();
			$set->current_count      = $set->current_count();
			$set->untranslated_count = $set->untranslated_count();
			$set->waiting_count      = $set->waiting_count();
			$set->fuzzy_count        = $set->fuzzy_count();
			$set->percent_translated = $set->percent_translated();
			$set->all_count          = $set->all_count();
			$set->wp_locale          = $locale ? $locale->wp_locale : '';
		}

		usort(
			$translation_sets,
			function ( $a, $b ) {
				return ( $b->current_count <=> $a->current_count );
			}
		);

		$title = sprintf(
			/* translators: %s: project name */
			__( '%s project', 'glotpress' ),
			esc_html( $project->name )
		);

		$this->tmpl( 'projects-meta', get_defined_vars() );
	}
}
