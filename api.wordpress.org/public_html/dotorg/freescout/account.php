<?php
/**
 * FreeScout: a WordPress.org account, for connecting and signing in helpdesk agents.
 *
 * Expects a signed `{ "username": "..." }` and answers with `{ "user": {...} }`, or `{ "user": null }` if there's no such account.
 *
 * @package WordPressdotorg\API\FreeScout
 */

declare( strict_types = 1 );

namespace WordPressdotorg\API\FreeScout;

// Two-factor status is only known where the Two Factor plugin runs.
$wp_init_host = 'https://login.wordpress.org/';

require __DIR__ . '/common.php';

/**
 * Finds an account by username, which is how WordPress.org identifies it at login, or else by profile slug.
 *
 * @param string $username Username or profile slug.
 * @return \WP_User|false
 */
function get_account( string $username ): \WP_User|false {
	if ( '' === $username ) {
		return false;
	}

	$user = get_user_by( 'login', $username );
	if ( ! $user ) {
		$user = get_user_by( 'slug', $username );
	}

	return $user;
}

/**
 * Whether an account has been blocked from logging in.
 *
 * Reports true when that can't be determined, so FreeScout refuses the login.
 *
 * @param \WP_User $user Account.
 * @return bool
 */
function is_blocked( \WP_User $user ): bool {
	global $nologin_accounts;

	if ( str_starts_with( $user->user_pass, 'BLOCKED' ) ) {
		return true;
	}

	// Accounts wporg-sso refuses a login.
	if ( ! empty( $nologin_accounts ) && in_array( $user->user_login, (array) $nologin_accounts, true ) ) {
		return true;
	}

	if ( ! defined( 'WPORG_SUPPORT_FORUMS_BLOGID' ) ) {
		return true;
	}

	$forums_user = new \WP_User( $user->ID, '', WPORG_SUPPORT_FORUMS_BLOGID );

	return ! empty( $forums_user->allcaps['bbp_blocked'] );
}

/**
 * Whether an account signs in with two-factor authentication.
 *
 * Reports false when that can't be determined, so FreeScout refuses the login.
 *
 * @param \WP_User $user Account.
 * @return bool
 */
function uses_two_factor( \WP_User $user ): bool {
	return class_exists( 'Two_Factor_Core' ) && \Two_Factor_Core::is_user_using_two_factor( $user->ID );
}

$request = get_request();
$user    = get_account( trim( (string) ( $request->username ?? '' ) ) );

if ( ! $user ) {
	wp_send_json( array( 'user' => null ) );
}

wp_send_json(
	array(
		'user' => array(
			'username'     => $user->user_login,
			'profile_url'  => 'https://profiles.wordpress.org/' . $user->user_nicename . '/',
			'display_name' => $user->display_name,
			'first_name'   => (string) $user->first_name,
			'last_name'    => (string) $user->last_name,
			'email'        => $user->user_email,
			'avatar_url'   => get_avatar_url(
				$user,
				array(
					'size'    => 256,
					'default' => 'mm',
				)
			),
			'two_factor'   => uses_two_factor( $user ),
			'blocked'      => is_blocked( $user ),
		),
	)
);
