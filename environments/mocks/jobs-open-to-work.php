<?php
/**
 * Plugin Name: Jobs Open to Work Mock
 * Description: Answers the profiles.wordpress.org "open to work" API with sample candidates, so the candidate sections render locally.
 *
 * @package WordPressdotorg\Environments
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Environments\Jobs_Open_To_Work;

add_filter( 'pre_http_request', __NAMESPACE__ . '\respond', 10, 3 );

/**
 * Returns sample candidates in the shape the profiles API sends.
 *
 * @return object[]
 */
function get_candidates(): array {
	$candidates = array(
		array( 'Alex Example', 'Developer', 'Example Agency', true ),
		array( 'Sam Sample', 'Designer', '', true ),
		array( 'Jordan Placeholder', 'Support Engineer', 'Plugin Shop', false ),
		array( 'Riley Demo', 'Project Manager', 'Demo Co', true ),
		array( 'Casey Mockup', 'Content Writer', '', false ),
		array( 'Taylor Fixture', 'Theme Developer', 'Fixture Studio', true ),
		array( 'Morgan Stub', 'Translator', '', false ),
		array( 'Jamie Testcase', 'Performance Engineer', 'Fast Sites', true ),
		array( 'Drew Dummy', 'Accessibility Specialist', '', false ),
		array( 'Quinn Sandbox', 'Full-Stack Developer', 'Sandbox Labs', true ),
		array( 'Avery Example', 'Migration Specialist', '', false ),
		array( 'Robin Specimen', 'Community Manager', 'Specimen Inc', true ),
	);

	return array_map(
		static function ( array $candidate ): object {
			$login = sanitize_title( $candidate[0] );

			return (object) array(
				'user_login'      => $login,
				'display_name'    => $candidate[0],
				'profile_url'     => "https://profiles.wordpress.org/{$login}/",
				'current_role'    => $candidate[1],
				'current_company' => $candidate[2],
				'show_badge'      => $candidate[3],
			);
		},
		$candidates
	);
}

/**
 * Short-circuits requests to the open to work endpoint.
 *
 * @param false|array|\WP_Error $response    Response to return instead, or false to send the request.
 * @param array                 $parsed_args HTTP request arguments.
 * @param string                $url         Request URL.
 * @return false|array|\WP_Error
 */
function respond( false|array|\WP_Error $response, array $parsed_args, string $url ): false|array|\WP_Error {
	if ( ! str_starts_with( $url, 'https://profiles.wordpress.org/wp-json/wporg-profiles/v1/jobs/open-to-work' ) ) {
		return $response;
	}

	$query = array();
	wp_parse_str( (string) wp_parse_url( $url, PHP_URL_QUERY ), $query );

	$candidates = get_candidates();
	$per_page   = max( 1, (int) ( $query['per_page'] ?? 10 ) );
	$page       = max( 1, (int) ( $query['page'] ?? 1 ) );

	return array(
		'headers'  => array(
			'x-wp-total'      => count( $candidates ),
			'x-wp-totalpages' => (int) ceil( count( $candidates ) / $per_page ),
		),
		'body'     => wp_json_encode( array_slice( $candidates, ( $page - 1 ) * $per_page, $per_page ) ),
		'response' => array(
			'code'    => 200,
			'message' => 'OK',
		),
		'cookies'  => array(),
		'filename' => null,
	);
}
