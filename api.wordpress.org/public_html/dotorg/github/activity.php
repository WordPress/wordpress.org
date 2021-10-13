<?php
namespace WordPressdotorg\API\GitHub\Firehose;
/**
 * This webhook is configured on the WordPress GitHub organization, and receives events for all WordPress/* repositories.
 * 
 * The data is parsed, and then stored in the `wporg_github_activity` DB table for displaying on profiles and being used elsewhere.
 * 
 * Events to Private repo's are skipped, to avoid any potential leakage of sensitive information.
 * 
 * The format of the tables data is relied upon by the `wporg-profiles.php` profile plugin for generating timelines.
 * 
 * HTTP Error codes are used to provide action taken for GitHub webhook delivery screen.
 *  - 200: Processed
 *  - 400: Parameters incorrect
 *  - 403: Auth failure
 *  - 422: Payload not processed due to not being required for profiles (The majority of webhook fires)
 *  - 501: Not yet implemented features, if the webhook has extra unexpected data sent.
 * 
 * The webhook payload is temporarily stored, for easier debugging but will be removed later.
 */

require dirname( dirname( __DIR__ ) ) . '/wp-init.php';

header( 'Content-Type: text/plain' );

if (
	empty( $_SERVER['HTTP_X_HUB_SIGNATURE_256'] ) ||
	empty( $_SERVER['HTTP_X_GITHUB_EVENT'] ) ||
	empty( $_SERVER['CONTENT_TYPE'] ) ||
	! constant( 'GH_ACTIVITY_WEBHOOK_SECRET' ) ||
	'application/json' !== $_SERVER['CONTENT_TYPE']
) {
	header( 'HTTP/1.0 400 Bad Request', true, 400 );
	die( 'UHOH; Expectation failed.' );
}

/**
 * Fetch the GitHub webhook payload, verifying the signature is correct.
 * Execution is terminated on failure.
 * 
 * @return object The signed webhook payload on success.
 */
function get_signed_payload_or_die() {
	$payload            = file_get_contents( 'php://input' );
	// Validate that the request came from GitHub.
	$sent_signature     = $_SERVER['HTTP_X_HUB_SIGNATURE_256'];
	$expected_signature = 'sha256=' . hash_hmac( 'sha256', $payload, constant( 'GH_ACTIVITY_WEBHOOK_SECRET' ) );

	if ( ! hash_equals( $expected_signature, $sent_signature ) ) {
		header( 'HTTP/1.0 403 Forbidden', true, 403 );
		die( 'Err; Signature Failure.' );
	}

	return json_decode( $payload );
}

/**
 * Convert a GitHub username to a WordPress.org User ID.
 * 
 * @param string $user The GitHub username.
 * @return false|int The WordPress.org User ID on success, false on failure.
 */
function github_user_to_user_id( $user ) {
	global $wpdb;

	$user_id = $wpdb->get_var( $wpdb->prepare(
		"SELECT user_id FROM wporg_github_users WHERE github_user = %s",
		$user
	) );

	return $user_id ? intval( $user_id ) : false;
}

$event   = $_SERVER['HTTP_X_GITHUB_EVENT'];
$payload = get_signed_payload_or_die();

// Ignore anything on private repos.
if ( ! empty( $payload->repository->private ) ) {
	header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
	die( 'NO; Private repo activity is not tracked.' );
}

// Ignore bots (Issues, Pull Requests)
if ( 'User' !== $payload->sender->type ) {
	header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
	die( 'NO; No homers allowed.' );
}

switch ( $event ) {
	case 'push':
		// Ignore pushes to not-master-branch.
		if ( $payload->ref != "refs/heads/{$payload->repository->master_branch}" ) {
			header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
			die( 'NO; Push to not-master-branch.' );
		}

		$github_user = $payload->sender->login;
		$user_id     = github_user_to_user_id( $github_user );

		// Detect WordPress.org git mirrors.
		// Ignore .org SVN => Github pushes.
		if ( preg_match( '!git-svn-id: https://[^.]+.svn.wordpress.org/!i', $payload->head_commit->message ) ) {
			header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
			die( 'NO; push appears to be SVN->GIT sync.' );
		}
		// re-assign pushes to the WordPress.org git committer
		if ( 'git.wordpress.org' === substr( $payload->head_commit->author->email, -17 ) ) {
			$user        = get_user_by( 'slug', substr( $payload->head_commit->author->email, 0, -18 ) );
			$user_id     = $user->ID ?? false;
			$github_user = $payload->head_commit->author->username ?? ( $user->github ?? '' );
		}

		if ( ! $github_user && ! $user_id ) {
			header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
			die( 'NO; Unknown user.' );
		}

		// Ignore pushes of PRs
		$pr_check = wp_safe_remote_get(
			// This call is currently unauthenticated, 60 req/hour/IP.
			str_replace(
				'{/sha1}',
				"{$payload->head_commit->id}/pulls",
				$payload->repository->commits_url
			),
			[
				'user_agent' => 'WordPress.org Profiles GitHub Activity Notifier'
			]
		);
		if ( 200 === wp_remote_retrieve_response_code( $pr_check ) ) {
			// If anything other than an empty array is returned, it's part of a PR which is recorded separately.
			if ( json_decode( wp_remote_retrieve_body( $pr_check ) ) ) {
				header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
				die( 'NO; push appears to be a PR merge.' );
			}
		} else {
			echo "WARNING; Could not verify if it's a PR push from API; falling back to email verification.\n";

			// This isn't perfect, as it'll include web-ui commits to trunk, but it'll do for when the API is unavailable or we've exceeded qupta.
			if ( 'noreply@github.com' === $payload->head_commit->committer->email ) {
				header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
				die( 'NO; push appears to be a PR merge (or web-based commit).' );
			}
		}

		// Pushed commits.
		$wpdb->insert(
			'wporg_github_activity',
			( $user_id     ? compact( 'user_id' )     : [] ) + // Leave user_id as null if not known.
			( $github_user ? compact( 'github_user' ) : [] ) + // Leave github_user as null if not known.
			[
				'ts'          => gmdate( 'Y-m-d H:i:s' ),
				'category'    => 'push',
				'repo'        => $payload->repository->full_name,
				'url'         => count( $payload->commits ) > 1 ? $payload->compare : $payload->head_commit->url,
				'title'       => count( $payload->commits ) . ' commits: ' . $payload->head_commit->message,
				'payload'     => json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
			]
		);

		break;

	case 'issues':
		if ( ! in_array( $payload->action, [ 'opened', 'edited', 'closed', 'deleted' ] ) ) {
			header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
			die( "NO; $event:{$payload->action} not required." );
		}

		// Update the Title & Description if it's edited. Just to keep everything in sync.
		if ( 'edited' === $payload->action ) {
			$wpdb->update(
				'wporg_github_activity',
				[
					'title'       => $payload->issue->title,
					'description' => wp_strip_all_tags( $payload->issue->body ),
				],
				[
					'github_user' => $payload->issue->user->login,
					'url'         => $payload->issue->html_url,
				]
			);

			die( 'OK; Updated' );
		} elseif ( 'deleted' === $payload->action ) {
			$wpdb->delete(
				'wporg_github_activity',
				[
					'url' => $payload->issue->html_url,
				]
			);

			die( 'OK; Deleted' );
		}

		// Can use Sender here, as they'll be the one to create/close the Issue.
		$user_id = github_user_to_user_id( $payload->sender->login );

		$wpdb->insert(
			'wporg_github_activity',
			( $user_id ? compact( 'user_id' ) : [] ) + // Leave user_id as null if not known.
			[
				'ts'          => gmdate( 'Y-m-d H:i:s' ),
				'github_user' => $payload->sender->login,
				'category'    => 'issue_' . $payload->action,
				'repo'        => $payload->repository->full_name,
				'url'         => $payload->issue->html_url,
				'title'       => $payload->issue->title,
				'description' => wp_strip_all_tags( $payload->issue->body ),
				'payload'     => json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
			]
		);

		break;
	case 'pull_request':

		if ( ! in_array( $payload->action, [ 'opened', 'reopened', 'edited', 'closed' ] ) ) {
			header( 'HTTP/1.0 422 Unprocessable Entity', true, 422 );
			die( "NO; $event:{$payload->action} not required." );
		}

		// Update the Title & Description if it's edited. Just to keep everything in sync.
		if ( 'edited' === $payload->action ) {
			$wpdb->update(
				'wporg_github_activity',
				[
					'title'       => $payload->pull_request->title,
					'description' => wp_strip_all_tags( $payload->pull_request->body ),
				],
				[
					'github_user' => $payload->pull_request->user->login,
					'url'         => $payload->pull_request->html_url,
				]
			);

			die( 'OK; Updated' );
		}

		// The sender of a pull_request event is the merger. The PR author will get extra credit below.
		$user_id = github_user_to_user_id( $payload->sender->login );

		$event = 'pr_' . $payload->action;
		if ( 'pr_closed' === $event && ! empty( $payload->pull_request->merged ) ) {
			$event = 'pr_merge';
		}

		$wpdb->insert(
			'wporg_github_activity',
			( $user_id ? compact( 'user_id' ) : [] ) + // Leave user_id as null if not known.
			[
				'ts'          => gmdate( 'Y-m-d H:i:s' ),
				'github_user' => $payload->sender->login,
				'category'    => $event,
				'repo'        => $payload->repository->full_name,
				'url'         => $payload->pull_request->html_url,
				'title'       => $payload->pull_request->title,
				'description' => wp_strip_all_tags( $payload->pull_request->body ),
				'payload'     => json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
			]
		);

		// For pr_merge, credit the PR submitter too.
		if (
			'pr_merge' === $event &&
			$payload->pull_request->user->login !== $payload->sender->login &&
			'User' === $payload->pull_request->user->type // Ignore automated Bot-submited PRs
		) {
			$event   = 'pr_merged'; // "merge" is the mergee, "merged" is the OP.
			$user_id = github_user_to_user_id( $payload->pull_request->user->login );
			if ( $user_id ) {
				$wpdb->insert(
					'wporg_github_activity',
					[
						'user_id'     => $user_id,
						'ts'          => gmdate( 'Y-m-d H:i:s' ),
						'github_user' => $payload->pull_request->user->login,
						'category'    => $event,
						'repo'        => $payload->repository->full_name,
						'url'         => $payload->pull_request->html_url,
						'title'       => $payload->pull_request->title,
						'description' => wp_strip_all_tags( $payload->pull_request->body ),
						'payload'     => json_encode( $payload, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ),
					]
				);
			}
		}

		break;
	default:
		header( 'HTTP/1.0 501 Not Implemented', true, 501 );
		die( "UHOH; The webhook sent us an event we didn't anticipate." );
		break;
}

die( 'OK' );
