<?php

namespace WordPressdotorg\GlotPress\Rosetta_Roles;

class Utils {

	/**
	 * Returns all keys of a multidimensional array.
	 *
	 * @param array  $array      Multidimensional array to extract keys from.
	 * @param string $childs_key Optional. Key of the child elements. Default 'childs'.
	 * @return array Array keys.
	 */
	public static function array_keys_multi( $array, $childs_key = 'childs' ) {
		$keys = array();

		foreach ( $array as $key => $value ) {
			$keys[] = $key;

			if ( isset( $value->$childs_key ) && is_array( $value->$childs_key ) ) {
				$keys = array_merge( $keys, self::array_keys_multi( $value->$childs_key ) );
			}
		}

		return $keys;
	}
}
