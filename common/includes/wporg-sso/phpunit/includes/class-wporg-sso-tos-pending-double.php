<?php
/**
 * A WordPress.org SSO whose user has not accepted the current terms of service.
 *
 * @package wporg-sso
 */

declare( strict_types = 1 );

/**
 * Stands in for the state `has_agreed_to_tos()` cannot currently report.
 *
 * The production method returns true for everyone ahead of its own body, so the
 * interstitial it guards is unreachable. Overriding it here reaches the blocked
 * branch of `maybe_block_auth_cookies()` without changing production.
 *
 * It covers nothing beyond that: the body a reopened gate would run — the
 * super-admin check, the `tos_revision` meta, the comparison against
 * `TOS_REVISION` — stays untested, and `TOS_REVISION` is not defined for this
 * suite, so reopening the gate would fatal here rather than fail informatively.
 */
class WPOrg_SSO_TOS_Pending_Double extends WPOrg_SSO_Test_Double {

	/**
	 * Reports the user as not having accepted the terms of service.
	 *
	 * @param int $user_id The user to check.
	 * @return bool
	 */
	protected function has_agreed_to_tos( $user_id ): bool { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- Matches WPOrg_SSO::has_agreed_to_tos().
		return false;
	}
}
