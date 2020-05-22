<?php
namespace WordPressdotorg\API\Trac\GithubPRs;

/**
 * Fetches and reformats the Github PR API response to the details we need.
 */
function fetch_pr_data( $repo, $pr ) {
	$url = '/repos/' . $repo . '/pulls/' . intval( $pr );
	$data = api_request( $url );

	// Error time..
	if ( ! $data || ! $data->number ) {
		return false;
	}

	// Get Travis CI State.
	$check_runs = [];
	$raw_check_runs = api_request(
		'/repos/' . $repo . '/commits/' . $data->head->sha . '/check-runs',
		null,
		[ 'Accept: application/vnd.github.antiope-preview+json' ]
	);
	if ( !empty( $raw_check_runs->check_runs ) ) {
		foreach ( $raw_check_runs->check_runs as $check ) {
			switch ( $check->status ) {
				case 'queued':
				case 'in_progress':
					$check_runs[ $check->app->name ] = 'in_progress';
					break;
				case 'completed':
					switch( $check->conclusion ) {
						case 'success':
							$check_runs[ $check->app->name ] = 'success';
							break;
						case 'failure':
							$check_runs[ $check->app->name ] = 'failed';
							break;
						case 'action_required':
							$check_runs[ $check->app->name ] = $check->output->title;
							break;
					}
			}
		}
	}

	$_reviews = api_request(
		'/repos/' . $repo . '/pulls/' . intval( $pr ) . '/reviews',
		null,
		[ 'Accept: application/vnd.github.antiope-preview+json' ]
	);
	$reviews = [];
	foreach ( $_reviews as $r ) {
		if (
			in_array( $r->state, [ 'CHANGES_REQUESTED', 'APPROVED' ] ) &&
			! in_array( $r->user->login, $reviews[ $r->state ] ?? [], true )
		) {
			$reviews[ $r->state ][] = $r->user->login;
		}
	}

	return (object) [
		'repo'            => $data->base->repo->full_name,
		'number'          => $data->number,
		'html_url'        => $data->html_url,
		'state'           => $data->state,
		'title'           => $data->title,
		'created_at'      => $data->created_at,
		'updated_at'      => $data->updated_at,
		'closed_at'       => $data->closed_at,
		'mergeable_state' => $data->mergeable_state,
		'check_runs'      => $check_runs,
		'reviews'         => $reviews,
		'body'            => $data->body,
		'user'            => (object) [
			'url'  => $data->user->html_url,
			'name' => $data->user->login,
		],
		'changes'         => (object) [
			'additions' => $data->additions,
			'deletions' => $data->deletions,
			'patch_url' => $data->diff_url,
			'html_url'  => $data->html_url,
		],
		'trac_ticket'    => determine_trac_ticket( $data ),
	];
}

/**
 * Find a WordPress.org user by a Github login.
 */
function find_wporg_user_by_github( $github_user ) {
	global $wpdb;

	return $wpdb->get_var( $wpdb->prepare(
		"SELECT u.user_login
			FROM wporg_github_users g
				JOIN {$wpdb->users} u ON g.user_id = u.ID
			WHERE g.github_user = %s",
		$github_user
	) );
}

/**
 * A simple wrapper to make a Github API request..
 */
function api_request( $url, $args = null, $headers = [], $method = null ) {
	// Prepend GitHub URL for relative URLs, not all API URI's are on api.github.com, which is why we support full URI's.
	if ( '/' === substr( $url, 0, 1 ) ) {
		$url = 'https://api.github.com' . $url;
	}

	$context = stream_context_create( [ 'http' => [
		'method'        => $method ?: ( is_null( $args ) ? 'GET' : 'POST' ),
		'user_agent'    => 'WordPress.org Trac; trac.WordPress.org',
		'max_redirects' => 0,
		'timeout'       => 5,
		'ignore_errors' => true,
		'header'        => array_merge(
			[
				'Accept: application/json',
				'Authorization: ' . get_authorization_token( $url ),
			],
			$headers
		),
		'content'       => $args ?: null,
	] ] );

	return json_decode( file_get_contents(
		$url,
		false,
		$context
	) );
}

/**
 * Fetch an Authorization token for a Github API request.
 */
function get_authorization_token( $url ) {
	// There are two different tokens used, JWT and App Installation tokens.
	if ( false !== stripos( $url, 'api.github.com/app' ) ) {
		// App Endpoint, that's a JWT token
		return 'BEARER ' . get_jwt_app_token();
	} else {
		// Regular Endpoint, use an App Installation token
		return 'BEARER ' . get_app_install_token();
	}
}

/**
 * Fetch a JWT Authorization token for the Github /app API endpoints.
 */
function get_jwt_app_token() {
	$token = wp_cache_get( GH_TRAC_APP_ID, 'API:JWT-token' );
	if ( $token ) {
		return $token;
	}

	include_once __DIR__ . '/adhocore-php-jwt/ValidatesJWT.php';
	include_once __DIR__ . '/adhocore-php-jwt/JWTException.php';
	include_once __DIR__ . '/adhocore-php-jwt/JWT.php';

	$key = openssl_pkey_get_private( base64_decode( GH_TRAC_APP_PRIV_KEY ) );
	$jwt = new \Ahc\Jwt\JWT( $key, 'RS256' );

	$token = $jwt->encode([
		'iat' => time(),
		'exp' => time() + 10*60,
		'iss' => GH_TRAC_APP_ID,
	]);

	// Cache it for 9 mins (It's valid for 10min)
	wp_cache_set( GH_TRAC_APP_ID, $token, 'API:JWT-token', 9 * 60 );

	return $token;
}

/**
 * Fetch an App Authorization token for accessing Github Resources.
 * 
 * This assumes that the Github App will only ever be installed on the @WordPress organization.
 */
function get_app_install_token() {
	$token = wp_cache_get( GH_TRAC_APP_ID . '-install-token', 'API:JWT-token' );
	if ( $token ) {
		return $token;
	}

	$installs = api_request(
		'/app/installations',
		null,
		[ 'Accept: application/vnd.github.machine-man-preview+json' ]
	);
	if ( ! $installs || empty( $installs[0]->access_tokens_url ) ) {
		return false;
	}

	$access_token = api_request(
		$installs[0]->access_tokens_url,
		null,
		[ 'Accept: application/vnd.github.machine-man-preview+json' ],
		'POST'
	);
	if ( ! $access_token || empty( $access_token->token ) ) {
		return false;
	}

	$token     = $access_token->token;
	$token_exp = strtotime( $access_token->expires_at );

	// Cache the token for 1 minute less than what it's valid for.
	wp_cache_set( GH_TRAC_APP_ID . '-install-token', $token, 'API:JWT-token', $token_exp - time() - 60 );

	return $token;
}

/**
 * Use some rough heuristics to find the Trac ticket for a given PR.
 * 
 * TODO: This should probably support multiple Trac Tickets, but once you start to use the final few regexes it can start to match Gutenberg references.
 */
function determine_trac_ticket( $pr ) {
	$ticket = false;

	// For now, we assume everything is destined for the Core Trac.
	switch ( $pr->base->repo->full_name ) {
		case 'WordPress/wordpress.org':
			$trac = 'meta';
			break;
		case 'WordPress/wordpress-develop':
		default:
			$trac = 'core';
			break;
	}

	$regexes = [
		'!' . $trac . '.trac.wordpress.org/ticket/(\d+)!i',
		'!(?:^|\s)#WP(\d+)!', // #WP1234
		'!(?:^|\s)#(\d{4,5})!', // #1234
		'!Ticket[ /-](\d+)!i',
		// diff filenames.
		'!\b(\d+)(\.\d)?\.(?:diff|patch)!i',
		// Formats of common branches
		'!(?:' . $trac . '|WordPress|fix|trac)[-/](\d+)!i',
		// Starts or ends with a ticketish number
		// These match things it really shouldn't, and are a last-ditch effort.
		'!\s(\d{4,5})$!i',
		'!^(\d{4,5})[\s\W]!i',
	];

	// Simple, the Trac ticket is mentioned in the title, or body.
	foreach ( $regexes as $regex ) {
		foreach ( [
			$pr->title,
			$pr->body,
			$pr->head->label,
			$pr->head->ref
		] as $field ) {
			if ( preg_match( $regex, $field, $m ) ) {
				return [ $trac, $m[1] ];
			}
		}
	}

	return false;
}

/**
 * Returns a instance of the Trac class for a given trac.
 */
function get_trac_instance( $trac ) {
	$trac_uri = 'https://' . $trac . '.trac.wordpress.org/login/rpc';

	return new Trac( GH_PRBOT_USER, GH_PRBOT_PASS, $trac_uri );
}

/**
 * Formats a PR description for usage on Trac.
 * 
 * This strips out HTML comments and standard boilerplate text.
 * 
 * @param object $pr_data PR Data.
 * @return string Stripped down PR Description
 */
function format_pr_desc_for_trac_comment( $pr_data ) {
	$desc = trim( $pr_data->body );

	// Remove HTML comments
	$desc = preg_replace( '#<!--.+?-->#s', '', $desc );

	// Remove the final line if it matches the specific boilerplate format.
	$desc = preg_replace( "#---\r?\n\*\*.+\*\*$#", '', $desc );

	return trim( $desc );
}
