<?php
/**
 *  Expands the GP API by adding extended Translation endpoints.
 *  Ultimate goal here being inclusion in the appropriate parts of GP core.
 *
 *  Put this file in the folder: /glotpress/plugins/
 */


class GP_Route_Translation_Extended extends GP_Route_Main {

	function __construct() {
		$this->template_path = dirname( __FILE__ ) . '/templates/';
	}

	function translations_options_ok() {
		$this->tmpl( 'status-ok' );
	}

	/**
	 * Gets translation set by project and locale slug, and returns counts and
	 * an array of untranslated strings (up to the number defined in GP::$translation->per_page)
	 * Example GET string: https://translate.wordpress.com/api/translations/-untranslated-by-locale?translation_set_slug=default&locale_slug=ta&project=wpcom&view=calypso
	 *
	 */
	function translations_get_untranslated_strings_by_locale() {
		if ( ! $this->api ) {
			$this->die_with_error( __( "Yer not 'spose ta be here." ), 403 );
		}

		$project_path          	= gp_get( 'project' );
		$locale_slug           	= gp_get( 'locale_slug' );
		$project_view           = gp_get( 'view', null );
		$translation_set_slug  	= gp_get( 'translation_set_slug', 'default' );

		if ( ! $project_path || ! $locale_slug || ! $translation_set_slug ) {
			$this->die_with_404();
		}

		$filters = array(
			'status' 	=> 'untranslated',
		);

		$sort = array(
			'by' => 'priority',
			'how' => 'desc',
		);
		$page = 1;
		$locale = GP_Locales::by_slug( $locale_slug );

		$project = GP::$project->by_path( $project_path );
		$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );
		$translations = GP::$translation->for_translation( $project, $translation_set, $page, $filters, $sort );

		if ( $project_view && class_exists( 'GP_Views' ) ) {
			$gp_plugin_views = GP_Views::get_instance();
			$gp_plugin_views->set_project_id( $project->id );
		}

		$result = new stdClass();
		$result->all_count 					= $translation_set->all_count();
		$result->country_code 				= $locale->country_code;
		$result->current_count 				= $translation_set->current_count();
		$result->fuzzy_count 				= $translation_set->fuzzy_count();
		$result->language_name 				= $locale->native_name;
		$result->language_name_en 			= $locale->english_name;
		$result->last_modified 				= $translation_set->current_count ? $translation_set->last_modified() : false;
		$result->percent_translated 		= $translation_set->percent_translated();
		$result->slug 						= $locale->slug;
		$result->untranslated_strings		= $translations;
		$result->untranslated_count 		= $translation_set->untranslated_count();
		$result->waiting_count 				= $translation_set->waiting_count();
		$result->wp_locale					= $locale->wp_locale;

		$translations = $result;
		$this->tmpl( 'translations-extended', get_defined_vars(), true );
	}

	function translations_get_by_originals() {
		if ( ! $this->api ) {
			$this->die_with_error( __( "Yer not 'spose ta be here." ), 403 );
		}

		$project_path          = gp_post( 'project' );
		$locale_slug           = gp_post( 'locale_slug' );
		$translation_set_slug  = gp_post( 'translation_set_slug', 'default' );
		$original_strings      = gp_post( 'original_strings', array() );

		if ( ! $project_path || ! $locale_slug || ! $translation_set_slug || ! $original_strings ) {
			$this->die_with_404();
		}

		$original_strings      = json_decode( $original_strings );

		$project_paths = $translation_sets = array();
		foreach ( explode( ',', $project_path ) as $project_path ) {

			$project = GP::$project->by_path( $project_path );
			if ( ! $project ) {
				continue;
			}

			$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $project->id, $translation_set_slug, $locale_slug );
			if ( ! $translation_set ) {
				continue;
			}

			$project_paths[ $project->id ] = $project_path;
			$translation_sets[ $project->id ] = $translation_set;
		}

		if ( empty( $translation_sets ) ) {
			$this->die_with_404();
		}

		$checked_originals = array();
		foreach ( $original_strings as $original ) {
			if ( empty( $original ) || ! property_exists( $original, 'singular' ) ) {
				continue;
			}
			$contexts = array( false );
			if ( property_exists( $original, 'context' ) && $original->context ) {
				if ( is_array( $original->context ) ) {
					$contexts = $original->context;
				} else {
					$contexts = array( $original->context );
				}
			}

			foreach ( $contexts as $context ) {
				$key = $original->singular;
				if ( $context ) {
					$original->context = $context;
					$key = $original->context . '\u0004' . $key;
				} else {
					unset( $original->context );
				}

				if ( isset( $checked_originals[ $key ] ) ) {
					continue;
				}
				$checked_originals[ $key ] = true;

				foreach ( $translation_sets as $project_id => $translation_set ) {
					$original_record = $this->by_project_id_and_entry( $project_id, $original );
					if ( ! $original_record ) {
						continue;
					}

					$query_result                    = new stdClass();
					$query_result->original_id       = $original_record->id;
					$query_result->original          = $original;
					$query_result->original_comment  = $original_record->comment;
					$query_result->project           = $project_paths[ $project_id ];

					$query_result->translations  = GP::$translation->find_many_no_map( "original_id = '{$query_result->original_id}' AND translation_set_id = '{$translation_set->id}' AND ( status = 'waiting' OR status = 'fuzzy' OR status = 'current' )" );

					$translations[] = $query_result;
					continue 2;
				}

				$translations[ 'originals_not_found' ][] = $original;
			}
		}
		$this->tmpl( 'translations-extended', get_defined_vars(), true );
	}

	function save_translation() {
		if ( ! $this->api ) {
			$this->die_with_error( __( "Yer not 'spose ta be here." ), 403 );
		}

		$this->logged_in_or_forbidden();

		$project_paths         = gp_post( 'project' );
		$locale_slug           = gp_post( 'locale_slug' );
		$translation_set_slug  = gp_post( 'translation_set_slug', 'default' );

		if ( ! $project_paths || ! $locale_slug || ! $translation_set_slug ) {
			$this->die_with_404();
		}

		$project_ids = array_map( function( $project_path ) {
			return GP::$project->by_path( $project_path )->id;
		}, explode( ',', $project_paths ) );

		if ( empty( $project_ids ) ) {
			$this->die_with_404();
		}


		$locale = GP_Locales::by_slug( $locale_slug );
		if ( ! $locale ) {
			$this->die_with_404();
		}

		$output = array();
		foreach( gp_post( 'translation', array() ) as $original_id => $translations ) {

			$original = GP::$original->get( $original_id );
			if ( ! $original || ! in_array( $original->project_id, $project_ids ) ) {
				$this->die_with_404();
			}

			$project = GP::$project->get( $original->project_id );

			$translation_set = GP::$translation_set->by_project_id_slug_and_locale( $original->project_id, $translation_set_slug, $locale_slug );
			if ( ! $translation_set ) {
				$this->die_with_404();
			}

			$data = compact('original_id');
			$data['user_id'] = get_current_user_id();
			$data['translation_set_id'] = $translation_set->id;

			foreach( range( 0, GP::$translation->get_static( 'number_of_plural_translations' ) ) as $i ) {
				if ( isset( $translations[$i] ) ) $data["translation_$i"] = $translations[$i];
			}

			$data['warnings'] = GP::$translation_warnings->check( $original->singular, $original->plural, $translations, $locale );

			if ( empty( $data['warnings'] ) && ( $this->can( 'approve', 'translation-set', $translation_set->id ) || $this->can( 'write', 'project', $project->id ) ) ) {
				$data['status'] = 'current';
			} else {
				$data['status'] = 'waiting';
			}

			$existing_translations = GP::$translation->for_translation( $project, $translation_set, 'no-limit', array('original_id' => $original_id, 'status' => 'current_or_waiting' ), array() );
			foreach( $existing_translations as $e ) {
				if ( array_pad( $translations, $locale->nplurals, null ) == $e->translations ) {
					return $this->die_with_error( __( 'Identical current or waiting translation already exists.' ), 409 );
				}
			}

			$translation = GP::$translation->create( $data );
			if ( ! $translation->validate() ) {
				$error_output = $translation->errors;
				$translation->delete();
				$this->die_with_error( $error_output, 422 );
			}

			do_action( 'gp_extended_api_save', $project, $locale, $translation );

			if ( 'current' == $data['status'] ) {
				$translation->set_status( 'current' );
			}

			gp_clean_translation_set_cache( $translation_set->id );
			$translations = GP::$translation->find_many_no_map( "original_id = '{$original_id}' AND translation_set_id = '{$translation_set->id}' AND ( status = 'waiting' OR status = 'fuzzy' OR status = 'current' )" );
			if ( ! $translations ) {
				$output[$original_id] = false;
			}

			$output[$original_id] = $translations;
		}

		$translations = $output;
		$this->tmpl( 'translations-extended', get_defined_vars(), true );
	}

	function set_status( $translation_id ) {
		if ( ! $this->api ) {
			$this->die_with_error( __( "Yer not 'spose ta be here." ), 403 );
		}

		$translation = GP::$translation->get( $translation_id );
		if ( ! $translation ) {
			$this->die_with_error( 'Translation doesn&#8217;t exist!', 404 );
		}

		$this->can_approve_translation_or_forbidden( $translation );

		$result = $translation->set_status( gp_post( 'status' ) );
		if ( ! $result ) {
			$this->die_with_error( 'Error in saving the translation status!', 409 );
		}

		$translations = $this->translation_record_by_id( $translation_id );
		if ( ! $translations ) {
			$this->die_with_error( 'Error in retrieving translation record!', 409 );
		}

		$this->tmpl( 'translations-extended', get_defined_vars() );
	}

	private function can_approve_translation_or_forbidden( $translation ) {
		$can_reject_self = ( get_current_user_id() == $translation->user_id && $translation->status == "waiting" );
		if ( $can_reject_self ) {
			return;
		}
		$this->can_or_forbidden( 'approve', 'translation-set', $translation->translation_set_id );
	}

	private function translation_record_by_id( $translation_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->translations WHERE id = %d", $translation_id ) );
	}

	// A slightly modified version og GP_Original->by_project_id_and_entry without the BINARY search keyword
	// to make sure the index on the gp_originals table is used
	private function by_project_id_and_entry( $project_id, $entry, $status = "+active" ) {
		global $wpdb;

		$entry->plural  = isset( $entry->plural ) ? $entry->plural : null;
		$entry->context = isset( $entry->context ) ? $entry->context : null;

		$where = array();

		$where[] = is_null( $entry->context ) ? '(context IS NULL OR %s IS NULL)' : 'context = %s';
		$where[] = 'singular = %s';
		$where[] = is_null( $entry->plural ) ? '(plural IS NULL OR %s IS NULL)' : 'plural = %s';
		$where[] = 'project_id = %d';
		$where[] = $wpdb->prepare( 'status = %s', $status );

		$where = implode( ' AND ', $where );

		$query = "SELECT * FROM $wpdb->gp_originals WHERE $where";
		$result = GP::$original->one( $query, $entry->context, $entry->singular, $entry->plural, $project_id );
		if ( ! $result ) {
			return null;
		}
		// we want case sensitive matching but this can't be done with MySQL while continuing to use the index
		// therefore we do an additional check here
		if ( $result->singular === $entry->singular ) {
			return $result;
		}

		// and get the whole result set here and check each entry manually
		$results = GP::$original->many( $query . ' AND id != %d', $entry->context, $entry->singular, $entry->plural, $project_id, $result->id );
		foreach ( $results as $result ) {
			if ( $result->singular === $entry->singular ) {
				return $result;
			}
		}

		return null;
	}
}

class GP_Translation_Extended_API_Loader {
	function init() {
		$this->init_new_routes();
	}

	function init_new_routes() {
		GP::$router->add( '/translations/-new', array( 'GP_Route_Translation_Extended', 'save_translation' ), 'post' );
		GP::$router->add( '/translations/-new', array( 'GP_Route_Translation_Extended', 'translations_options_ok' ), 'options' );
		GP::$router->add( '/translations/(\d+)/-set-status', array( 'GP_Route_Translation_Extended', 'set_status' ), 'post' );
		GP::$router->add( '/translations/(\d+)/-set-status', array( 'GP_Route_Translation_Extended', 'translations_options_ok' ), 'options' );
		GP::$router->add( '/translations/-query-by-originals', array( 'GP_Route_Translation_Extended', 'translations_get_by_originals' ), 'post' );
		GP::$router->add( '/translations/-query-by-originals', array( 'GP_Route_Translation_Extended', 'translations_options_ok' ), 'options' );
		GP::$router->add( '/translations/-untranslated-by-locale', array( 'GP_Route_Translation_Extended', 'translations_get_untranslated_strings_by_locale' ), 'get' );
		GP::$router->add( '/translations/-untranslated-by-locale', array( 'GP_Route_Translation_Extended', 'translations_get_untranslated_strings_by_locale' ), 'options' );
	}
}

$gp_translation_extended_api = new GP_Translation_Extended_API_Loader();
add_action( 'gp_init', array( $gp_translation_extended_api, 'init' ) );

