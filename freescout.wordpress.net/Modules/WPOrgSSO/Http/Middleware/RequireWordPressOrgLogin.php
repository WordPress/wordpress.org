<?php
/**
 * Makes WordPress.org the only way in.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Http\Middleware;

use App\Mailbox;
use App\User;
use Closure;
use Illuminate\Http\Request;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\WordPressOrgUser;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every web request, after FreeScout's own middleware.
 *
 * - Logs out anyone who logs in some other way once WordPress.org is enforced, however they got in.
 * - Logs out administrators outside the proxy, when that's required.
 * - Replaces the login form, and closes password logins, resets, and invite setups.
 * - Fills in new users from their WordPress.org account, and connects the account.
 * - Keeps what comes from WordPress.org, and passwords, out of the profile form.
 * - Deletes a mailbox only when its name was typed in to confirm, since users don't know a FreeScout password.
 */
final class RequireWordPressOrgLogin {

	/**
	 * How often a session's WordPress.org account is checked again, in seconds.
	 *
	 * FreeScout keeps busy sessions alive, so without this, a blocked account would keep its session.
	 *
	 * @var int
	 */
	private const RECHECK_SECONDS = 3600; // 1 hour.

	/**
	 * How long sessions from before WordPress.org was enforced go on, in seconds.
	 *
	 * @var int
	 */
	private const CUTOVER_SECONDS = 86400; // 1 day.

	/**
	 * Core action that signs in with a FreeScout password; with the break-glass switch on, open to administrators.
	 *
	 * @var string
	 */
	public const PASSWORD_LOGIN_ACTION = 'App\Http\Controllers\Auth\LoginController@login';

	/**
	 * Core actions that set a password through an email link; nobody needs them.
	 *
	 * Not even in break-glass mode: whoever reads an administrator's inbox would get in without WordPress.org's
	 * two-factor authentication. Administrators get a password from `wporgsso:password` instead. Closing them here
	 * only explains why; the provider's login listener refuses their logins, whatever core calls them.
	 *
	 * @var string[]
	 */
	private const SETUP_ACTIONS = array(
		'App\Http\Controllers\Auth\RegisterController@showRegistrationForm',
		'App\Http\Controllers\Auth\RegisterController@register',
		'App\Http\Controllers\Auth\ForgotPasswordController@showLinkRequestForm',
		'App\Http\Controllers\Auth\ForgotPasswordController@sendResetLinkEmail',
		'App\Http\Controllers\Auth\ResetPasswordController@showResetForm',
		'App\Http\Controllers\Auth\ResetPasswordController@reset',
		'App\Http\Controllers\OpenController@userSetup',
		'App\Http\Controllers\OpenController@userSetupSave',
	);

	/**
	 * Core actions that change the logged-in user's own password; with the break-glass switch on, open.
	 *
	 * @var string[]
	 */
	private const PASSWORD_PAGES = array(
		'App\Http\Controllers\UsersController@password',
		'App\Http\Controllers\UsersController@passwordSave',
	);

	/**
	 * User AJAX actions that email a password or an invite to set one.
	 *
	 * @var string[]
	 */
	private const PASSWORD_AJAX_ACTIONS = array( 'reset_password', 'send_invite' );

	/**
	 * User AJAX actions that change what comes from WordPress.org.
	 *
	 * @var string[]
	 */
	private const ACCOUNT_AJAX_ACTIONS = array( 'delete_photo' );

	/**
	 * Handles a request.
	 *
	 * @param Request $request Request.
	 * @param Closure $next    Next handler.
	 * @return Response
	 */
	public function handle( Request $request, Closure $next ): Response {
		$user   = $request->user();
		$action = $request->route() ? (string) $request->route()->getActionName() : '';

		// Whatever way they logged in, so a stolen session doesn't work from outside the proxy either.
		if (
			$user instanceof User &&
			$user->isAdmin() &&
			WPOrgSSOServiceProvider::proxy_required() &&
			! WPOrgSSOServiceProvider::proxied( $request )
		) {
			\Log::warning( '[WPOrgSSO] Logged out ' . $user->email . ', an administrator, from outside the proxy at ' . $request->ip() . '.' );

			return self::log_out( $request, __( 'Administrators can only use the helpdesk through the proxy.' ) );
		}

		// Logins stay as they are until the identity provider is configured, so administrators can be connected first.
		if ( WPOrgSSOServiceProvider::enforced() ) {
			$response = self::enforce_login( $request, $user, $action );
			if ( $response ) {
				return $response;
			}
		}

		/*
		 * Only for those allowed to add users, so nobody else can probe WordPress.org accounts; core refuses the rest.
		 * Without the API, core's own form stays.
		 */
		if (
			'App\Http\Controllers\UsersController@createSave' === $action &&
			$user instanceof User &&
			$user->can( 'create', User::class ) &&
			Client::from_config()->is_configured()
		) {
			$error = self::resolve_account( $request, null );
			if ( $error ) {
				return redirect()->route( 'users.create' )->withErrors( array( 'wporg_username' => $error ) )->withInput();
			}

			/*
			 * Agents log in through WordPress.org, so they never need the password or the invite to set one. Not even in
			 * break-glass mode, where invite setups stay closed and administrators get a password from the command line.
			 * Core requires a password; the provider swaps it for core's "no password" marker after core hashed it.
			 */
			if ( WPOrgSSOServiceProvider::enforced() ) {
				$request->merge( array( 'password' => User::generateRandomPassword() ) );
				$request->offsetUnset( 'send_invite' );
			}
		}

		if ( 'App\Http\Controllers\UsersController@profileSave' === $action ) {
			$error = self::handle_profile( $request, $user );
			if ( $error ) {
				return redirect()->route( 'users.profile', array( 'id' => $request->route( 'id' ) ) )->withErrors( $error )->withInput();
			}
		}

		// Only answers those who could change the user; core turns away everyone else, without revealing the connection.
		$target = 'App\Http\Controllers\UsersController@ajax' === $action && in_array( $request->input( 'action' ), self::ACCOUNT_AJAX_ACTIONS, true )
			? User::find( (int) $request->input( 'user_id' ) )
			: null;
		if (
			$target instanceof User &&
			$user instanceof User &&
			$user->can( 'update', $target ) &&
			Account::for_user( (int) $target->id )
		) {
			return response()->json(
				array(
					'status' => 'error',
					'msg'    => __( 'The photo is the WordPress.org avatar.' ),
				),
				403
			);
		}

		if ( 'App\Http\Controllers\MailboxesController@ajax' === $action && 'delete_mailbox' === $request->input( 'action' ) ) {
			$error = self::check_mailbox_name( $request, $user );
			if ( $error ) {
				// A 200, like core's own errors: the dialog only shows the message of those.
				return response()->json(
					array(
						'status' => 'error',
						'msg'    => $error,
					)
				);
			}
		}

		return $next( $request );
	}

	/**
	 * Checks that the name of the mailbox being deleted was typed in, exactly; it stands in for core's password check.
	 *
	 * @param Request   $request Request.
	 * @param User|null $user    Logged-in user.
	 * @return string Error message, empty to let core go on.
	 */
	private static function check_mailbox_name( Request $request, ?User $user ): string {
		$mailbox = Mailbox::find( (int) $request->input( 'mailbox_id' ) );

		// Core answers everyone else, without revealing the mailbox's name.
		if ( ! $mailbox instanceof Mailbox || ! $user instanceof User || ! $user->can( 'admin', $mailbox ) ) {
			return '';
		}

		// Core trims input, so spaces around the name don't matter.
		if ( $request->input( 'mailbox_name' ) === (string) $mailbox->name ) {
			return '';
		}

		return __( 'Type the mailbox’s name, :name, to confirm.', array( 'name' => $mailbox->name ) );
	}

	/**
	 * Makes WordPress.org the only way in: ends other sessions, and replaces or closes password pages.
	 *
	 * @param Request   $request Request.
	 * @param User|null $user    Logged-in user.
	 * @param string    $action  Controller action the request is for.
	 * @return Response|null Response to send instead, or null to go on.
	 */
	private static function enforce_login( Request $request, ?User $user, string $action ): ?Response {
		// On the first enforced request, before any session can be from while this module was off.
		$enforced_since = WPOrgSSOServiceProvider::enforced_since();

		if ( $user instanceof User && ! self::may_stay_logged_in( $user, $request, $enforced_since ) ) {
			return self::log_out( $request, __( 'Please log in with your WordPress.org account.' ) );
		}

		// Logged-in users are sent on by core's guest middleware.
		if ( 'App\Http\Controllers\Auth\LoginController@showLoginForm' === $action && ! $user && ! self::wants_password_form( $request ) ) {
			return response()->view(
				WPOrgSSOServiceProvider::ALIAS . '::login',
				array(
					'error'          => (string) $request->session()->get( WPOrgSSOServiceProvider::SESSION_ERROR, '' ),
					'password_login' => WPOrgSSOServiceProvider::password_login_enabled(),
				)
			);
		}

		if ( in_array( $action, self::SETUP_ACTIONS, true ) ) {
			return redirect()->route( 'login' )->with( WPOrgSSOServiceProvider::SESSION_ERROR, __( 'Please log in with your WordPress.org account.' ) );
		}

		if ( 'App\Http\Controllers\UsersController@ajax' === $action && in_array( $request->input( 'action' ), self::PASSWORD_AJAX_ACTIONS, true ) ) {
			return response()->json(
				array(
					'status' => 'error',
					'msg'    => __( 'Users log in with their WordPress.org account.' ),
				),
				403
			);
		}

		/*
		 * In break-glass mode, core checks the password, so a failed login looks the same whoever it's for; the login
		 * listener only lets administrators stay.
		 */
		if ( self::PASSWORD_LOGIN_ACTION === $action && ! WPOrgSSOServiceProvider::password_login_enabled() ) {
			return redirect()->route( 'login' )->with( WPOrgSSOServiceProvider::SESSION_ERROR, __( 'Please log in with your WordPress.org account.' ) );
		}

		if ( ! WPOrgSSOServiceProvider::password_login_enabled() && in_array( $action, self::PASSWORD_PAGES, true ) ) {
			return redirect()->route( 'users.profile', array( 'id' => $request->route( 'id' ) ) );
		}

		return null;
	}

	/**
	 * Ends the session, and sends the user to the login page.
	 *
	 * @param Request $request Request.
	 * @param string  $error   Error the login page shows.
	 * @return Response
	 */
	private static function log_out( Request $request, string $error ): Response {
		\Auth::logout();
		$request->session()->invalidate();

		// Like core's auth middleware: polls get a 401, pages come back after the login.
		if ( $request->expectsJson() ) {
			return response()->json( array( 'message' => 'Unauthenticated.' ), 401 );
		}

		return redirect()->guest( route( 'login' ) )->with( WPOrgSSOServiceProvider::SESSION_ERROR, $error );
	}

	/**
	 * Whether a logged-in user may stay logged in.
	 *
	 * @param User    $user           Logged-in user.
	 * @param Request $request        Request.
	 * @param int     $enforced_since When WordPress.org was first enforced, as a Unix timestamp.
	 * @return bool
	 */
	private static function may_stay_logged_in( User $user, Request $request, int $enforced_since ): bool {
		$session  = $request->session();
		$username = (string) $session->get( WPOrgSSOServiceProvider::SESSION_USERNAME, '' );

		// Also ends sessions of users whose account was changed or disconnected since.
		if ( '' !== $username ) {
			return 0 === strcasecmp( $username, Account::username_for( (int) $user->id ) ) && self::account_still_may_log_in( $username, $request );
		}

		if ( $session->get( WPOrgSSOServiceProvider::SESSION_REFUSED ) ) {
			return false;
		}

		// Break-glass sessions end with break-glass.
		if ( $session->get( WPOrgSSOServiceProvider::SESSION_PASSWORD_LOGIN ) ) {
			return WPOrgSSOServiceProvider::password_login_enabled() && $user->isAdmin();
		}

		/*
		 * From before WordPress.org was enforced: going on for a day keeps whoever switched it on logged in, so they can
		 * connect the accounts. Later, an unmarked session is from while this module was off, and can't be trusted.
		 */
		return time() - $enforced_since < self::CUTOVER_SECONDS;
	}

	/**
	 * Checks again, once an hour, that the session's WordPress.org account may still log in.
	 *
	 * If WordPress.org can't be reached, the session goes on; the next check is an hour later.
	 *
	 * @param string  $username WordPress.org username the session logged in with.
	 * @param Request $request  Request.
	 * @return bool
	 */
	private static function account_still_may_log_in( string $username, Request $request ): bool {
		$checked_at = (int) $request->session()->get( WPOrgSSOServiceProvider::SESSION_CHECKED_AT, 0 );
		if ( time() - $checked_at < self::RECHECK_SECONDS ) {
			return true;
		}

		$request->session()->put( WPOrgSSOServiceProvider::SESSION_CHECKED_AT, time() );

		try {
			// Briefly: it holds up whatever request is due, and a failed check lets the session go on anyway.
			$wporg_user = WordPressOrgUser::find( $username, Client::from_config()->with_timeout( 3 ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] Could not check ' . $username . ' again: ' . $e->getMessage() );

			return true;
		}

		return $wporg_user && '' === $wporg_user->login_error( $username );
	}

	/**
	 * Whether an administrator asked for the password form, and may use it.
	 *
	 * @param Request $request Request.
	 * @return bool
	 */
	private static function wants_password_form( Request $request ): bool {
		return WPOrgSSOServiceProvider::password_login_enabled() && $request->query->has( 'password' );
	}

	/**
	 * Keeps what comes from WordPress.org out of the profile form, or connects a user who isn't yet.
	 *
	 * @param Request   $request   Request.
	 * @param User|null $auth_user Logged-in user.
	 * @return array Errors by field, empty on success.
	 */
	private static function handle_profile( Request $request, ?User $auth_user ): array {
		// Core turns away those who can't change the user, without revealing the connection.
		$user = User::find( (int) $request->route( 'id' ) );
		if ( ! $user || ! $auth_user instanceof User || ! $auth_user->can( 'update', $user ) ) {
			return array();
		}

		if ( ! Account::for_user( (int) $user->id ) ) {
			// Only administrators connect users, and only once; after that, the account can't be switched.
			if ( $auth_user instanceof User && $auth_user->isAdmin() && '' !== self::wporg_username( $request ) ) {
				$error = self::resolve_account( $request, $user );

				return $error ? array( 'wporg_username' => $error ) : array();
			}

			return array();
		}

		// Laravel has read the upload by now, so it can only be refused, not dropped.
		if ( $request->hasFile( 'photo_url' ) ) {
			return array( 'photo_url' => __( 'The photo is the WordPress.org avatar.' ) );
		}

		$request->merge(
			array(
				'first_name' => $user->first_name,
				'last_name'  => $user->last_name,
				'email'      => $user->email,
			)
		);

		return array();
	}

	/**
	 * Looks up the WordPress.org account named on a user form, for connecting it to the user.
	 *
	 * @param Request   $request Request.
	 * @param User|null $user    Existing user to connect, or null for a new one, which is filled in from the account.
	 * @return string Error message, empty on success.
	 */
	private static function resolve_account( Request $request, ?User $user ): string {
		$username = self::wporg_username( $request );
		if ( '' === $username ) {
			return __( 'Enter the WordPress.org username of the user.' );
		}

		$client = Client::from_config();
		if ( ! $client->is_configured() ) {
			return __( 'WordPress.org accounts can’t be looked up until WPORG_API_SECRET is set.' );
		}

		try {
			$wporg_user = WordPressOrgUser::find( $username, $client );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] Lookup failed: ' . $e->getMessage() );

			return __( 'WordPress.org could not be reached. Please try again in a moment.' );
		}

		if ( ! $wporg_user ) {
			return __( 'There is no WordPress.org account with that username.' );
		}

		$connected = Account::user_for( $wporg_user->username );
		if ( $connected ) {
			return __( 'That WordPress.org account already belongs to :name.', array( 'name' => $connected->getFullName() ) );
		}

		if ( ! $user ) {
			$fields = $wporg_user->user_fields();

			// Otherwise core's unique email error asks for another address, which the next submit replaces again.
			$existing = User::query()->where( 'email', $fields['email'] )->first();
			if ( $existing && User::STATUS_DELETED === (int) $existing->status ) {
				return __( 'That WordPress.org account’s email address belongs to :name, a deleted user.', array( 'name' => $existing->getFullName() ) );
			}

			if ( $existing ) {
				return __( 'That WordPress.org account’s email address belongs to :name; connect them on their profile instead.', array( 'name' => $existing->getFullName() ) );
			}

			$request->merge( $fields );
		}

		$request->attributes->set( WPOrgSSOServiceProvider::REQUEST_ACCOUNT, $wporg_user );

		return '';
	}

	/**
	 * The WordPress.org username a user form names.
	 *
	 * @param Request $request Request.
	 * @return string Username, empty if none was given.
	 */
	private static function wporg_username( Request $request ): string {
		$username = $request->input( 'wporg_username', '' );

		return is_string( $username ) ? trim( $username ) : '';
	}
}
