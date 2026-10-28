<?php
/**
 * Plugin name: GlotPress: Custom Translation Errors
 * Description: Provides custom translation errors.
 * Version:     1.0
 * Author:      WordPress.org
 * Author URI:  http://wordpress.org/
 * License:     GPLv2 or later
 */
class WPorg_GP_Custom_Translation_Errors {
	/**
	 * The parent WordPress core project ID.
	 *
	 * @var int
	 */
	const WORPRESS_CORE_PROJECT_ID = 1;

	/**
	 * Registers all methods starting with error_ with GlotPress.
	 */
	public function __construct() {
		$errors = array_filter(
			get_class_methods( get_class( $this ) ),
			function ( $key ) {
				return gp_startswith( $key, 'error_' );
			}
		);

		foreach ( $errors as $error ) {
			GP::$translation_errors->add( str_replace( 'error_', '', $error ), array( $this, $error ) );
		}
	}

	/**
	 * Checks if the project is a WordPress core project.
	 *
	 * @param GP_Original $gp_original The GP_original object.
	 * @return bool
	 */
	public function is_core_project( GP_Original $gp_original ): bool {
		$project = GP::$project->get( $gp_original->project_id );
		$project = GP::$project->get( $project->parent_project_id );

		if ( self::WORPRESS_CORE_PROJECT_ID == $project->parent_project_id ) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Adds an error for unexpected timezone strings.
	 *
	 * Must be either a valid integer offset (-12 to 14) or a valid timezone string (America/New_York)
	 *
	 * @param string      $original    The original string.
	 * @param string      $translation The translated string.
	 * @param GP_Original $gp_original The GP_original object.
	 * @param GP_Locale   $locale      The locale.
	 * @return string|true The error message or true if no error.
	 */
	public function error_unexpected_timezone( $original, $translation, $gp_original, $locale ) {
		if ( ! $this->is_core_project( $gp_original ) ) {
			return true;
		}
		if ( is_null( $gp_original->context ) ) {
			return true;
		}
		if ( strpos( $gp_original->context, 'default GMT offset or timezone string' ) === false ) {
			return true;
		}
		$spaces_present = $translation !== trim( $translation );
		// Must be either a valid offset (-12 to 14).
		if ( is_numeric( $translation ) && floor( $translation ) == $translation && ! $spaces_present && $translation >= -12 && $translation <= 14 ) {
			// Countries with half-hour offsets or similar need to use a timezone string.
			return true;
		}
		// Or a valid timezone string (America/New_York).
		if ( in_array( $translation, timezone_identifiers_list() ) ) {
			return true;
		}
		if ( $spaces_present ) {
			return esc_html__( 'The translation has empty spaces, new lines or another similar elements.', 'glotpress' );
		}

		return esc_html__( 'Must be either a valid integer offset (-12 to 14) or a valid timezone string (America/New_York).', 'glotpress' );
	}

	/**
	 * Adds an error for unexpected start of week number.
	 *
	 * It should be 0, 1 or 6.
	 *
	 * @param string      $original    The original string.
	 * @param string      $translation The translated string.
	 * @param GP_Original $gp_original The GP_original object.
	 * @param GP_Locale   $locale      The locale.
	 * @return string|true The error message or true if no error.
	 */
	public function error_unexpected_start_of_week_number( $original, $translation, $gp_original, $locale ) {
		if ( ! $this->is_core_project( $gp_original ) ) {
			return true;
		}
		if ( is_null( $gp_original->context ) ) {
			return true;
		}
		if ( strpos( $gp_original->context, 'start of week' ) === false ) {
			return true;
		}
		$spaces_present = $translation !== trim( $translation );
		if ( is_numeric( $translation ) && ! $spaces_present && ( $translation == 0 || $translation == 1 || $translation == 6 ) ) {
			return true;
		}
		if ( $spaces_present ) {
			return esc_html__( 'The translation has empty spaces, new lines or another similar elements.', 'glotpress' );
		}

		return esc_html__( 'Must be one of the following integers: 0, 1, or 6.', 'glotpress' );
	}

	/**
	 * Adds an error for unexpected slug format.
	 *
	 * @param string      $original    The original string.
	 * @param string      $translation The translated string.
	 * @param GP_Original $gp_original The GP_original object.
	 * @param GP_Locale   $locale      The locale.
	 * @return string|true The error message or true if no error.
	 */
	public function error_unexepected_slug( $original, $translation, $gp_original, $locale ) {
		if ( ! $this->is_core_project( $gp_original ) ) {
			return true;
		}
		if ( is_null( $gp_original->context ) ) {
			return true;
		}
		if (
			strpos( $gp_original->context, 'Default post slug' ) === false &&
			strpos( $gp_original->context, 'sample permalink structure' ) === false
		) {
			return true;
		}
		$spaces_present = $translation !== trim( $translation );
		if ( $translation == sanitize_title( $translation ) ) {
			return true;
		}

		if ( $spaces_present ) {
			return esc_html__( 'The translation has empty spaces, new lines or another similar elements.', 'glotpress' );
		}

		return sprintf(
		/* translators: %1$s: The slug made with the translation suggested. */
			esc_html__( 'Must be a slug, like %1$s.', 'glotpress' ),
			sanitize_title( $translation )
		);
	}

	/**
	 * Adds an error for unexpected date formats.
	 *
	 * @param string      $original    The original string.
	 * @param string      $translation The translated string.
	 * @param GP_Original $gp_original The GP_original object.
	 * @param GP_Locale   $locale      The locale.
	 * @return string|true The error message or true if no error.
	 */
	public function error_timezone_date_format( $original, $translation, $gp_original, $locale ) {
		if ( ! $this->is_core_project( $gp_original ) ) {
			return true;
		}
		if ( is_null( $gp_original->context ) ) {
			return true;
		}
		if ( ! str_contains( $gp_original->context, 'timezone date format' ) ) {
			return true;
		}

		$date_time = DateTime::createFromFormat( $translation, gmdate( $translation ) );
		if ( $date_time ) {
			return true;
		}

		$spaces_present = $translation !== trim( $translation );
		if ( $spaces_present ) {
			return esc_html__( 'The translation has empty spaces, new lines or another similar elements.', 'glotpress' );
		}

		return esc_html__( 'Must be a valid timezone date format.', 'glotpress' );
	}
}

/**
 * Returns the instance of the WPorg_GP_Custom_Translation_Errors class.
 *
 * @return WPorg_GP_Custom_Translation_Errors
 */
function wporg_gp_custom_translation_errors() {
	 global $wporg_gp_custom_translation_errors;

	if ( ! isset( $wporg_gp_custom_translation_errors ) ) {
		$wporg_gp_custom_translation_errors = new WPorg_GP_Custom_Translation_Errors();
	}

	return $wporg_gp_custom_translation_errors;
}

add_action( 'plugins_loaded', 'wporg_gp_custom_translation_errors' );
