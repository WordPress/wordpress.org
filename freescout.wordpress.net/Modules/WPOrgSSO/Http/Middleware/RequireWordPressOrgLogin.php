<?php
/**
 * Makes WordPress.org the only way in.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Http\Middleware;

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
 * - Logs out anyone who didn't log in through WordPress.org, however they got in.
 * - Replaces the login form, and closes password logins, resets, and invite setups.
 * - Fills in new users from their WordPress.org account, and connects the account.
 * - Keeps what comes from WordPress.org, and passwords, out of the profile form.
 */
final class RequireWordPressOrgLogin {

	/**
	 * How often a session's WordPress.org account is checked again, in seconds.
	 *
	 * FreeScout keeps busy sessions alive, so without this, a blocked account would keep its session.
	 *
	 * @var int
	 */
	private const RECHECK_SECONDS = 3600;

	/**
	 * Core actions that sign in with a FreeScout password; with the break-glass switch on, open to administrators.
	 *
	 * @var string[]
	 */
	private const PASSWORD_ACTIONS = array(
		'App\Http\Controllers\Auth\LoginController@login',
		'App\Http\Controllers\Auth\ForgotPasswordController@showLinkRequestForm',
		'App\Http\Controllers\Auth\ForgotPasswordController@sendResetLinkEmail',
		'App\Http\Controllers\Auth\ResetPasswordController@showResetForm',
		'App\Http\Controllers\Auth\ResetPasswordController@reset',
	);

	/**
	 * Core actions that create a password for a new user; nobody needs them.
	 *
	 * @var string[]
	 */
	private const SETUP_ACTIONS = array(
		'App\Http\Controllers\Auth\RegisterController@showRegistrationForm',
		'App\Http\Controllers\Auth\RegisterController@register',
		'App\Http\Controllers\OpenController@userSetup',
		'App\Http\Controllers\OpenController@userSetupSave',
	);

	/**
	 * Core actions that set or send passwords for a logged-in user.
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

			// Agents log in through WordPress.org, so they never need the password or the invite to set one.
			if ( ! WPOrgSSOServiceProvider::passwords_available() ) {
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

		if (
			'App\Http\Controllers\UsersController@ajax' === $action &&
			in_array( $request->input( 'action' ), self::ACCOUNT_AJAX_ACTIONS, true ) &&
			Account::for_user( (int) $request->input( 'user_id' ) )
		) {
			return response()->json(
				array(
					'status' => 'error',
					'msg'    => __( 'The photo is the WordPress.org avatar.' ),
				),
				403
			);
		}

		return $next( $request );
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
		if ( $user instanceof User && ! self::may_stay_logged_in( $user, $request ) ) {
			\Auth::logout();
			$request->session()->invalidate();

			// Like core's auth middleware: polls get a 401, pages come back after the login.
			if ( $request->expectsJson() ) {
				return response()->json( array( 'message' => 'Unauthenticated.' ), 401 );
			}

			return redirect()->guest( route( 'login' ) )->with( WPOrgSSOServiceProvider::SESSION_ERROR, __( 'Please log in with your WordPress.org account.' ) );
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
			return redirect()->route( 'login' );
		}

		if ( in_array( $action, self::PASSWORD_ACTIONS, true ) && ! self::may_use_password( $request ) ) {
			$error = WPOrgSSOServiceProvider::password_login_enabled()
				? __( 'Only administrators can log in with a password.' )
				: __( 'Please log in with your WordPress.org account.' );

			return redirect()->route( 'login' )->with( WPOrgSSOServiceProvider::SESSION_ERROR, $error );
		}

		if ( ! WPOrgSSOServiceProvider::password_login_enabled() ) {
			if ( in_array( $action, self::PASSWORD_PAGES, true ) ) {
				return redirect()->route( 'users.profile', array( 'id' => $request->route( 'id' ) ) );
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
		}

		return null;
	}

	/**
	 * Whether a logged-in user may stay logged in.
	 *
	 * @param User    $user    Logged-in user.
	 * @param Request $request Request.
	 * @return bool
	 */
	private static function may_stay_logged_in( User $user, Request $request ): bool {
		$username  = (string) $request->session()->get( WPOrgSSOServiceProvider::SESSION_USERNAME, '' );
		$connected = Account::username_for( (int) $user->id );

		// Also ends sessions of users whose account was changed or disconnected since.
		if ( '' !== $username && 0 === strcasecmp( $username, $connected ) ) {
			return self::account_still_may_log_in( $username, $request );
		}

		return WPOrgSSOServiceProvider::password_login_enabled() && $user->isAdmin();
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
	 * Whether a password page may be used: only in break-glass mode, and only for administrators' accounts.
	 *
	 * @param Request $request Request.
	 * @return bool
	 */
	private static function may_use_password( Request $request ): bool {
		if ( ! WPOrgSSOServiceProvider::password_login_enabled() ) {
			return false;
		}

		// The forms themselves don't name an account yet.
		if ( $request->isMethod( 'GET' ) ) {
			return true;
		}

		$user = User::query()->where( 'email', (string) $request->input( 'email', '' ) )->first();

		return $user && $user->isAdmin();
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
		$user = User::find( (int) $request->route( 'id' ) );
		if ( ! $user ) {
			return array();
		}

		if ( ! Account::for_user( (int) $user->id ) ) {
			// Only administrators connect users, and only once; after that, the account can't be switched.
			if ( $auth_user instanceof User && $auth_user->isAdmin() && '' !== trim( (string) $request->input( 'wporg_username', '' ) ) ) {
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
		$username = trim( (string) $request->input( 'wporg_username', '' ) );
		if ( '' === $username ) {
			return __( 'Enter the WordPress.org username of the user.' );
		}

		try {
			$wporg_user = WordPressOrgUser::find( $username, Client::from_config() );
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
			$request->merge( $wporg_user->user_fields() );
		}

		$request->attributes->set( WPOrgSSOServiceProvider::REQUEST_ACCOUNT, $wporg_user );

		return '';
	}
}
