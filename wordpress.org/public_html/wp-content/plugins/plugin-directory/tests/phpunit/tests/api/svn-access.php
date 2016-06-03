<?php

/**
 *
 * @group api
 */
class Tests_API_SVN_Access extends WP_UnitTestCase {

	function test_permission_denied() {
		$response = wp_remote_get( 'https://wordpress.org/plugins-wp/wp-json/plugins/v1/svn-access' );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->assertEquals( 'not_authorized', $data['code'] );
	}

	function test_permission_denied_with_bad_authorization() {
		$response = wp_remote_get( 'https://wordpress.org/plugins-wp/wp-json/plugins/v1/svn-access', array(
			'headers' => array(
				'Authorization' => 'lol like this would be a real auth key'
			),
		) );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		$this->assertEquals( 'not_authorized', $data['code'] );
	}
}
