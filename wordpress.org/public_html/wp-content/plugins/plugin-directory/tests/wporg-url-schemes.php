<?php

class TestUrlSchemes extends WP_UnitTestCase {

	function http_get( $uri ) {
		$base_url = 'https://wordpress.org';
		$url      = $base_url . $uri;

		$http_args = array(
			'timeout' => 15,
			// 'body' => array(
			// 'action' => $action,
			// 'request' => serialize( $args )
			// ),
			// 'user-agent' => $user_agent,
		);
		$request = wp_remote_get( $url, $http_args );

		return $request;
	}

	function urlProvider() {
		return [
			[ '/plugins/add/' ],
			[ '/plugins/about/' ],
			[ '/plugins/about/guidelines/' ],
			[ '/plugins/about/svn/' ],
			[ '/plugins/about/faq/' ],
			[ '/plugins/about/readme.txt' ],
			[ '/plugins/about/validator/' ],
			[ '/plugins/forum/1/page/1/' ],
			[ '/plugins/forum/1/' ],
			[ '/plugins/tags/widget/page/1/' ],
			[ '/plugins/tags/widget/' ],
			[ '/plugins/tags/' ],
			[ '/plugins/profile/joostdevalk/page/1/' ],
			[ '/plugins/profile/joostdevalk/content-plugins/' ],
			[ '/plugins/profile/joostdevalk/content-plugins/page/1/' ],
			[ '/plugins/profile/joostdevalk/' ],
			[ '/plugins/browse/author/joostdevalk/page/1/' ],
			[ '/plugins/browse/author/joostdevalk/' ],
			[ '/plugins/browse/beta/page/1/' ],
			[ '/plugins/browse/beta/' ],
			[ '/plugins/rss/' ],
			[ '/plugins/rss/forum/1/' ],
			[ '/plugins/rss/topic/1/' ],
			[ '/plugins/rss/tags/widget/' ],
			[ '/plugins/rss/view/author/joostdevalk/page/1/' ],
			[ '/plugins/rss/view/author/joostdevalk/' ],
			[ '/plugins/rss/view/beta/page/1/' ],
			[ '/plugins/rss/view/beta/' ],
			[ '/plugins/rss/browse/author/joostdevalk/page/1/' ],
			[ '/plugins/rss/browse/author/joostdevalk/' ],
			[ '/plugins/rss/browse/beta/page/1/' ],
			[ '/plugins/rss/browse/beta/' ],
			[ '/plugins/rss/popular/' ],
			[ '/plugins/plugin/jetpack' ],
		];
	}

	/**
	 * @dataProvider urlProvider
	 */
	function test_url( $url ) {
		$response = $this->http_get( $url );
		$this->assertFalse( is_wp_error( $response ), ( is_wp_error( $response ) ? $response->get_error_message() : '' ) );
		$response_status = wp_remote_retrieve_response_code( $response );
		$this->assertEquals( 200, $response_status );
	}
}
