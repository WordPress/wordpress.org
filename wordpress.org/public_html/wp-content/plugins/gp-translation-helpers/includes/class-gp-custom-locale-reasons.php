<?php

class GP_Custom_Locale_Reasons extends GP_Route {
	/**
	 * Return the custom reasons set for the specified locale
	 *
	 * @since 0.0.2
	 *
	 * @param string $locale The locale for the custom reason
	 *
	 * @return array $locale_reasons[ $locale ] The custom reasons defined for the specified locale.
	 */
	public static function get_custom_reasons( $locale ) {
		// Add custom reasons here in this array in the format below ,
		// here's and example how to add a custom reason for the `yor` locale
		// Ensure the key for the custom reasons is not one of the following [ 'style', 'grammar', 'branding', 'glossary', 'punctuation', 'typo' ]
		// $locale_reasons = array(
		// 'yor' => array (
		// 'custom_style'       => array(
		// 'name'        => __( 'Custom Style Guide' ),
		// 'explanation' => __( 'The translation is not following the style guide. It will be interesting to provide a link to the style guide for your locale in the comment.' ),
		// ),
		// )
		// );
		$locale_reasons = array();

		return isset( $locale_reasons[ $locale ] ) ? $locale_reasons[ $locale ] : array();
	}
}
