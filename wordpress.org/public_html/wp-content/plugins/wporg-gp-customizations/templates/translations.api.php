<?php
foreach ( $translations as $index => $translation ) {
	foreach ( (array) $translation as $key => $value ) {
		if ( $value instanceof WP_User ) {
			$translations[ $index ]->$key = (object) array_intersect_key( (array) $value->data, array_flip( array(
				'user_login',
				'display_name',
			) ) );
		}
	}
}
echo wp_json_encode( $translations );
