<?php
/**
 * @package WPorg_Plugin_Directory
 */

/**
 * Various functions used by other processes, will make sense to move to specific classes.
 */
class WPorg_Plugin_Directory_Tools {

	/**
	 * @param string $readme
	 * @return object
	 */
	static function get_readme_data( $readme ) {

		// Uses https://github.com/rmccue/WordPress-Readme-Parser (with modifications)
		include_once __DIR__ . '/readme-parser/markdown.php';
		include_once __DIR__ . '/readme-parser/compat.php';

		$data = (object) WPorg_Readme::parse_readme( $readme );

		unset( $data->sections['screenshots'] ); // Useless.

		// Sanitize contributors.
		var_dump($data);
		foreach ( $data->contributors as $i => $name ) {
			if ( get_user_by( 'login', $name ) ) {
				continue;
			} elseif ( false !== ( $user = get_user_by( 'slug', $name ) ) ) {
				$data->contributors[] = $user->user_login;
				unset( $data->contributors[ $i ] );
			} else {
				unset( $data->contributors[ $i ] );
			}
		}

		return $data;
	}
}
