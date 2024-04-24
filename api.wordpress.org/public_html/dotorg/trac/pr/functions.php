<?php
namespace WordPressdotorg\API\Trac\GithubPRs;

/**
 * Fetches and reformats the Github PR API response to the details we need.
 */
function fetch_pr_data( $repo, $pr ) {
	$url = '/repos/' . $repo . '/pulls/' . intval( $pr );
	$data = api_request( $url );

	// Error time..
	if ( ! $data || empty( $data->number ) ) {
		return false;
	}

	// Get Travis CI State.
	$check_runs = [];
	$raw_check_runs = api_request(
		'/repos/' . $repo . '/commits/' . $data->head->sha . '/check-runs',
		null,
		[ 'Accept: application/vnd.github.antiope-preview+json' ]
	);
	if ( ! empty( $raw_check_runs->check_runs ) ) {
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
	if ( $_reviews ) {
		foreach ( $_reviews as $r ) {
			if (
				in_array( $r->state, [ 'CHANGES_REQUESTED', 'APPROVED' ] ) &&
				! in_array( $r->user->login, $reviews[ $r->state ] ?? [], true )
			) {
				$reviews[ $r->state ][] = $r->user->login;
			}
		}
	}

	$touches_tests = false;
	$_files = api_request(
		'/repos/' . $repo . '/pulls/' . intval( $pr ) . '/files?per_page=999',
		null,
		[ 'Accept: application/vnd.github.antiope-preview+json' ]
	);
	if ( $_files ) {
		foreach ( $_files as $f ) {
			if ( preg_match( '!(^tests/|/tests/)!', $f->filename ) ) {
				$touches_tests = true;
				break;
			}
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
		'touches_tests'   => $touches_tests,
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
function find_wporg_user_by_github( $github_user, $what = 'user_login' ) {
	global $wpdb;

	if ( ! in_array( $what, [ 'ID', 'user_login' ], true ) ) {
		return false;
	}

	return $wpdb->get_var( $wpdb->prepare(
		"SELECT u.{$what}
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
	$trac = 'core';
	switch ( $pr->base->repo->full_name ) {
		case 'WordPress/wordpress.org':
			$trac = 'meta';
			break;
		case 'WordPress/wordpress-develop':
			$trac = 'core';
			break;
		case 'buddypress/buddypress':
			$trac = 'buddypress';
			break;
		case 'bbpress/bbPress':
			$trac = 'bbpress';
			break;
		default:
			// If `?trac=....` is passed to the webhook endpoint:
			if ( defined( 'WEBHOOK_TRAC_HINT' ) && WEBHOOK_TRAC_HINT ) {
				$trac = WEBHOOK_TRAC_HINT;
			}

			// If a specific trac is mentioned within the PR body (and only that trac)
			elseif (
				preg_match_all( '!/(?P<trac>[a-z]+).trac.wordpress.org/!i', $body, $m ) &&
				1 === count( array_unique( $m[0] ) )
			) {
				$trac = $m['trac'][0];
			}

			// If the repo starts with 'wporg-' assume Meta.
			elseif ( str_starts_with( $pr->base->repo->full_name, 'WordPress/wporg-' ) ) {
				$trac = 'meta';
			}
			break;
	}

	$regexes = [
		// Match explicit ticket-ref links, as included in most PR descriptions.
		"!Trac ticket:\s*https://(?P<trac>[a-z]+).trac.wordpress.org/ticket/(?P<id>\d+)!i",

		// Match any references to the "expected" trac instance first. This covers cases where multiple tracs are mentioned.
		"!{$trac}.trac.wordpress.org/ticket/(?P<id>\d+)!i",

		// Then any trac instance.
		'!(?P<trac>[a-z]+).trac.wordpress.org/ticket/(?P<id>\d+)!i',

		// Now for just plain ticket references without a trac instance.
		'!(?:^|\s)#WP(\d+)!', // #WP1234
		'!(?:^|\s)#(\d{4,5})!', // #1234
		'!Ticket[ /-](\d+)!i',

		// diff filenames.
		'!\b(\d+)(\.\d)?\.(?:diff|patch)!i',

		// Formats of common branches.
		'!((?P<trac>core|meta|bbpress|buddypress|themes)|WordPress|fix|trac)[-/](?P<id>\d+)!i',

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
				$id = $m['id'] ?? $m[1];

				// If a Trac-specific link is detected, use that trac.
				if ( ! empty( $m['trac'] ) ) {
					$trac = $m['trac'];
				}

				return [ $trac, $id ];
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
 * Formats a PR description/comment for usage on Trac.
 *
 * This:
 *  - Strips standard boilerplate texts
 *  - format_github_content_for_trac_comment();
 *
 * @param string $desc.
 * @return string Converted PR Description
 */
function format_pr_desc_for_trac_comment( $desc ) {
	$desc = trim( $desc );

	// Remove the final line if it matches the specific boilerplate format.
	$desc = preg_replace( "#---\r?\n\*\*.+\*\*$#", '', $desc );

	// Or the 'Trac Ticket: ...' reference.
	$desc = preg_replace( '!^(Trac )?Ticket:\s*https://[a-z0-9.#/:]+$!im', '', $desc );

	return format_github_content_for_trac_comment( $desc );
}

/**
 * Formats github content for usage on Trac.
 *
 * This:
 *  - Strips HTML comments
 *  - Converts code blocks
 *  - Converts image embeds
 *  - Converts links
 *  - Converts tables
 *
 * @param string $desc.
 * @return string Converted PR Description
 */
function format_github_content_for_trac_comment( $desc ) {
	// Standardise on \n.
	$desc = str_replace( "\r\n", "\n", $desc );

	// Remove HTML comments
	$desc = preg_replace( '#<!--.+?-->#s', '', $desc );

	// Convert Code blocks.
	$desc = preg_replace_callback(
		'#^(?P<indent>[ >]*)```(?P<format>[a-z]+$)(?P<code>.+?)```$#sm',
		function( $m ) {
			return
				$m['indent'] . "{{{\n" .
				$m['indent'] . "#!" . trim( $m['format'] ) . "\n" .
				trim( $m['code'] ) . "\n" .
				$m['indent'] . "}}}\n";
		},
		$desc
	);

	$desc = preg_replace( '#```(.+?)```#s', '{{{$1}}}', $desc );

	// Convert Images (Must happen prior to Links, as the only difference is a preceeding !)
	$desc = preg_replace( '#!\[(.+?)\]\((.+?)\)#', '[[Image($2)]]', $desc );
	// Convert Images embedded as `<img>`.
	$desc = preg_replace( '#<img[^>]+src=(["\'])(.+?)\\1[^>]*>#', '[[Image($2)]]', $desc );

	// Convert Links.
	$desc = preg_replace( '#\[(.+?)\]\((.+?)\)#', '[$2 $1]', $desc );

	// Convert Tables.
	$desc = preg_replace_callback(
		'#^[|].+[|]$#m', 
		function( $m ) {
			// Headers such as `| --- |---|`
			if ( preg_match( '#^[- |]+$#', $m[0] ) ) {
				return '~~~TABLEHEADER~~~';
			}

			// Replace singular |'s but not double ||'s
			return preg_replace( '#(?<![|])[|](?![|])#', '||', $m[0] );
		},
		$desc
	);
	// Markup the headers now. Trac table headers are in the format of ||= Header =||
	$desc = preg_replace_callback(
		"#^([|].+[|])\n(~~~TABLEHEADER~~~)#m",
		function( $m ) {
			$headers = $m[1];
			$headers = preg_replace( '#[|]{2}([^|=])#', '||=$1', $headers );
			$headers = preg_replace( '#([^|=])[|]{2}#', '$1=||', $headers );

			return $headers;
		},
		$desc
	);

	// It shouldn't exist at this point, but if it does, replace it back with it's original content.
	$desc = str_replace( '~~~TABLEHEADER~~~', '|| ||', $desc );

	$desc = trim( $desc );

	// After all this, if it's a HTML comment, we're not interested in syncing it.
	if ( preg_match( '/[{`]+\s*#!html/i', $desc ) ) {
		return false;
	}

	return $desc;
}
