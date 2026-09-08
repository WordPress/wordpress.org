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
		'timeout'       => 10,
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
				preg_match_all( '!/(?P<trac>[a-z]+).trac.wordpress.org/!i', $pr->body, $m ) &&
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

		// Any GitHub defined ticket autolink references.
		'!(?:(?P<trac>Core|Meta)-|ticket:)(?P<id>\d+)!i', // Core-1234, Meta-1234, ticket:1234

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
					$trac = strtolower( $m['trac'] );
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
 * @return string|false Converted PR Description, or false if it may not be synced.
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
 * The Trac wiki processors a synced comment may name.
 *
 * The rest render badly, or take element attributes as `#!div` and `#!span` do.
 *
 * @link https://trac.edgewall.org/wiki/1.1/WikiProcessors#AvailableProcessors
 *
 * @return array Supported processor names.
 */
function trac_comment_processors() {
	return [ 'default', 'xml', 'php', 'js', 'javascript', 'css', 'sql', 'sh', 'diff' ];
}

/**
 * Escapes the row separators of a line, leaving its inline code alone.
 *
 * Trac writes a row separator's parameters onto the `tr` element, but renders inline
 * code literally, so only the text outside a `{{{…}}}` or a backtick span needs it.
 *
 * @param string $line One line of composed wiki text.
 * @return string|false The line with its row separators escaped.
 */
function trac_comment_escape_row_separators( $line ) {
	$parts = preg_split( '#(\{\{\{.*?\}\}\}|`[^`\n]*`)#', $line, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( false === $parts ) {
		return false;
	}

	foreach ( $parts as $i => $part ) {
		if ( $i % 2 ) {
			continue;
		}

		$parts[ $i ] = preg_replace( '~\|(?=-)~', '!|', $part );
		if ( null === $parts[ $i ] ) {
			return false;
		}
	}

	return implode( '', $parts );
}

/**
 * What Trac skips within a line before a delimiter, plus the `>` of a citation.
 *
 * Python's whitespace class, which is wider than PCRE's, so a pattern using this
 * needs the `u` modifier. `\n` is left out: it must not carry across a line.
 *
 * @return string A character class, for use inside a `/u` pattern.
 */
function trac_comment_skipped() {
	return '[ \t\x1c-\x1f\p{Z}>]';
}

/**
 * Turns one fenced code block into a Trac code block.
 *
 * The contents are rendered literally, so only the block's own delimiters and its
 * processor line have to be taken away from the pull request.
 *
 * @param string $fence One ```-fenced span, delimiters included.
 * @return string|false The span as Trac wiki markup, or false if it cannot be built.
 */
function trac_comment_code_block( $fence ) {
	/*
	 * Trac ends a block only on a line of exactly `}}}` and nests one only on a line
	 * opening with `{{{`, so those two shapes are broken up and braces anywhere else
	 * are left as the code wrote them.
	 */
	$inert = function ( $code ) {
		$skipped = trac_comment_skipped();

		return preg_replace(
			array( "~^({$skipped}*)\}\}\}{$skipped}*$~mu", "~^({$skipped}*)\{\{\{(?![^\n]*\}\}\})~mu" ),
			array( '$1} }}', '$1{ {{' ),
			$code
		);
	};

	// Anchored to the whole span: a match on part of it would drop the rest.
	if ( preg_match( '#\A(?P<indent>[ >]*)```[ ]*(?P<format>[a-z]+)$(?P<code>.+?)```[ \t]*\z#sm', $fence, $m ) ) {
		$format = trim( $m['format'] );

		// The HTML code block in trac renders actual HTML, set it as an XML code block for syntax highlighting.
		if ( 'html' === $format ) {
			$format = 'xml';
		}

		if ( ! in_array( $format, trac_comment_processors(), true ) ) {
			$format = 'default';
		}

		$code = trim( $m['code'] );
		// replace a blank indented line at the end of the code block with.. nothing.
		if ( $m['indent'] ) {
			$code = preg_replace( "#\n[ >]+$#", '', $code );
			if ( null === $code ) {
				return false;
			}
		}

		$code = $inert( $code );
		if ( ! is_string( $code ) ) {
			return false;
		}

		return $m['indent'] . "{{{\n" .
			$m['indent'] . '#!' . $format . "\n" .
			$code . "\n" .
			$m['indent'] . "}}}\n";
	}

	if ( ! preg_match( '#\A(?P<indent>[ >]*)```(?P<code>.*?)```[ \t]*\z#s', $fence, $m ) ) {
		return false;
	}

	$code = preg_replace( "#\n[ >]+$#", '', trim( $m['code'], "\n" ) );
	if ( null === $code ) {
		return false;
	}

	$code = $inert( $code );
	if ( ! is_string( $code ) ) {
		return false;
	}

	// Naming the processor stops the fence's first line from choosing one.
	return $m['indent'] . "{{{\n" .
		$m['indent'] . "#!default\n" .
		$code . "\n" .
		$m['indent'] . '}}}';
}

/**
 * Converts one span of pull request text to Trac wiki markup.
 *
 * The body's own wiki syntax is escaped before any is added, so only the markup
 * this composer writes reaches Trac as markup.
 *
 * @param string $text A span of the pull request lying outside any code fence.
 * @return string|false The span as Trac wiki markup, or false if it cannot be built.
 */
function trac_comment_wiki_text( $text ) {
	// Trac renders inline code literally, so its contents are not escaped with the prose.
	$parts = preg_split( '#(```.*?```|`[^`\n]*`)#s', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( false === $parts ) {
		return false;
	}

	foreach ( $parts as $i => $part ) {
		if ( $i % 2 ) {
			// A one-line fence becomes Trac's own inline code; a single backtick already is.
			$parts[ $i ] = str_starts_with( $part, '```' ) ? '{{{' . substr( $part, 3, -3 ) . '}}}' : $part;
			continue;
		}

		// Only the openers Trac reads as markup; a lone `[` is left for the conversions below.
		$parts[ $i ] = preg_replace( '~\[(?=[\[=])|\{(?=\{\{)~', '!$0', $part );
		if ( null === $parts[ $i ] ) {
			return false;
		}
	}

	$text = implode( '', $parts );

	// Convert Images (Must happen prior to Links, as the only difference is a preceeding `!`).
	$text = preg_replace_callback(
		'#!\[(?!\[)(.+?)\]\((.+?)\)#',
		function ( $m ) {
			return '[[Image(' . trac_comment_link_target( $m[2] ) . ')]]';
		},
		$text
	);
	if ( ! is_string( $text ) ) {
		return false;
	}

	// Convert Images embedded as `<img>`.
	$text = preg_replace_callback(
		'#<img[^>]+src=(["\'])(.+?)\\1[^>]*>#',
		function ( $m ) {
			return '[[Image(' . trac_comment_link_target( $m[2] ) . ')]]';
		},
		$text
	);

	if ( ! is_string( $text ) ) {
		return false;
	}

	// Convert Links.
	$text = preg_replace_callback(
		'#\[(.+?)\]\((.+?)\)#',
		function ( $m ) {
			return '[' . trac_comment_link_target( $m[2] ) . ' ' . $m[1] . ']';
		},
		$text
	);

	// A null subject is coerced to '' by the next call, so every pass is checked.
	if ( ! is_string( $text ) ) {
		return false;
	}

	// Convert Tables, and escape the row separators of every line that is not one.
	$escaped = true;
	$text    = preg_replace_callback(
		'#^.+$#m',
		function ( $m ) use ( &$escaped ) {
			if ( ! preg_match( '#^[|].+[|]$#', $m[0] ) ) {
				$line = trac_comment_escape_row_separators( $m[0] );
				// A false here would be coerced to '', dropping the line from the comment.
				if ( false === $line ) {
					$escaped = false;

					return $m[0];
				}

				return $line;
			}

			// Headers such as `| --- |---|`.
			if ( preg_match( '#^[- |]+$#', $m[0] ) ) {
				return '~~~TABLEHEADER~~~';
			}

			// Replace singular |'s but not double ||'s.
			return preg_replace( '#(?<![|])[|](?![|])#', '||', $m[0] );
		},
		$text
	);
	// Markup the headers now. Trac table headers are in the format of `||= Header =||`.
	$text = preg_replace_callback(
		"#^([|].+[|])\n(~~~TABLEHEADER~~~)#m",
		function ( $m ) {
			$headers = $m[1];
			$headers = preg_replace( '#[|]{2}([^|=])#', '||=$1', $headers );
			$headers = preg_replace( '#([^|=])[|]{2}#', '$1=||', $headers );

			return $headers;
		},
		$text
	);

	// A conversion above that PCRE gave up on would otherwise empty the span.
	if ( ! $escaped || ! is_string( $text ) ) {
		return false;
	}

	// It shouldn't exist at this point, but if it does, replace it back with it's original content.
	return str_replace( '~~~TABLEHEADER~~~', '|| ||', $text );
}

/**
 * Encodes a URL for use as the target of a Trac macro or link.
 *
 * Macro arguments are comma-separated and Trac writes one it does not recognise onto
 * the element as an attribute, while `|` would meet the row-separator escape below.
 *
 * @param string $url Link or image target taken from the pull request.
 * @return string The target with the markup's own punctuation percent-encoded.
 */
function trac_comment_link_target( $url ) {
	return preg_replace_callback(
		'/[,()\[\]"\'|]/',
		function ( $m ) {
			return rawurlencode( $m[0] );
		},
		trim( $url )
	);
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
 * @param string $desc GitHub content to convert to Trac wiki markup.
 * @return string|false Converted PR Description, or false if it may not be synced.
 */
function format_github_content_for_trac_comment( $desc ) {
	// Standardise on \n, including the boundaries Trac breaks lines on but PCRE does not.
	$line_breaks = array( "\r\n", "\r", "\x0b", "\x0c", "\x1c", "\x1d", "\x1e", "\xc2\x85", "\xe2\x80\xa8", "\xe2\x80\xa9" );
	$desc        = str_replace( $line_breaks, "\n", $desc );

	// Remove HTML comments.
	$desc = preg_replace( '#<!--.+?-->#s', '', $desc );
	if ( null === $desc ) {
		return false;
	}

	$parts = preg_split( '#(^[ >]*```(?:(?!```)[^\n])*\n.*?```[ \t]*$)#sm', $desc, -1, PREG_SPLIT_DELIM_CAPTURE );
	if ( false === $parts ) {
		return false;
	}

	foreach ( $parts as $i => $part ) {
		$part = ( $i % 2 ) ? trac_comment_code_block( $part ) : trac_comment_wiki_text( $part );
		if ( false === $part ) {
			return false;
		}

		$parts[ $i ] = $part;
	}

	$desc = implode( '', $parts );

	$desc = trim( $desc );

	$skipped = trac_comment_skipped();

	// After all this, if it names a processor we didn't pick, we're not interested in syncing it.
	$block = "~^{$skipped}*\{\{\{(?![^\n]*\}\}\}){$skipped}*\n?{$skipped}*#!([\w+-][\w+/-]*)~mu";
	$count = preg_match_all( $block, $desc, $processors );
	if ( false === $count || array_diff( array_map( 'strtolower', $processors[1] ), trac_comment_processors() ) ) {
		return false;
	}

	return $desc;
}
