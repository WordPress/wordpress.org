<?php

namespace WordPressdotorg\GlotPress\Routes\Routes;

use GP;
use GP_Route;
use GP_Route_Glossary_Entry;

/**
 * Redirector Route Class.
 *
 * Provides redirection routes.
 */
class Redirector extends GP_Route {

	public function redirect_languages( $path = '' ) {
		if ( empty( $path ) ) {
			$this->redirect( '/' );
		} else {
			$this->redirect( "/locale/$path" );
		}
	}

	public function redirect_index() {
		$this->redirect( '/' );
	}

	/**
	 * Redirects previous glossary paths from `/projects/wp/dev/$locale/$slug/glossary`
	 * to `/locale/$locale/$slug/glossary`.
	 *
	 * @param string $project_path Path of a project.
	 * @param string $locale       Locale slug.
	 * @param string $slug         Slug if a translation set.
	 */
	public function redirect_old_glossary( $project_path = '', $locale = '', $slug = '' ) {
		$project_path = "wp/$project_path";

		$project = GP::$project->by_path( $project_path );
		if ( ! $project ) {
			$route_glossary_entry = new GP_Route_Glossary_Entry();
			return $route_glossary_entry->glossary_entries_get( $project_path, $locale, $slug );
		}

		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $slug, $locale );
		if ( ! $translation_set ) {
			$route_glossary_entry = new GP_Route_Glossary_Entry();
			return $route_glossary_entry->glossary_entries_get( $project_path, $locale, $slug );
		}

		// If the current project has a glossary stop here and load it.
		$glossary = GP::$glossary->by_set_id( $translation_set->id );
		if ( $glossary ) {
			$route_glossary_entry = new GP_Route_Glossary_Entry();
			return $route_glossary_entry->glossary_entries_get( $project_path, $locale, $slug );
		}

		// Otherwise redirect to the locale glossary.
		$locale_glossary_translation_set = GP::$translation_set->by_project_id_slug_and_locale( 0, $slug, $locale );
		$locale_glossary = GP::$glossary->by_set_id( $locale_glossary_translation_set->id );
		$this->redirect( $locale_glossary->path() );
	}
}
