<?php
/**
 * WordPress.org login and account lookup.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Http\Controllers;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Providers\WPOrgSSOServiceProvider;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\Passwords;
use Modules\WPOrgSSO\Services\Saml;
use Modules\WPOrgSSO\Services\UserSync;
use Modules\WPOrgSSO\Services\WordPressOrgUser;

/**
 * Handles the SAML login with login.wordpress.org.
 *
 * The identity provider posts its response cross-site, where the session cookie doesn't come along.
 * So the ACS validates it and hands it over to complete(), which checks it answers this browser's request.
 */
final class SsoController extends Controller {

	/**
	 * How long a validated response waits for the browser to pick it up, in minutes.
	 *
	 * @var int
	 */
	private const HANDOFF_MINUTES = 2;

	/**
	 * How many logins a session can have pending at once, e.g. one per tab.
	 *
	 * @var int
	 */
	private const MAX_PENDING = 5;

	/**
	 * Sends the browser to login.wordpress.org.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function start( Request $request ): RedirectResponse {
		if ( ! WPOrgSSOServiceProvider::enforced() ) {
			return redirect()->route( 'login' );
		}

		$login   = Saml::from_config()->login_request();
		$pending = (array) $request->session()->get( WPOrgSSOServiceProvider::SESSION_REQUEST_IDS, array() );

		$pending[] = $login['request_id'];
		$request->session()->put( WPOrgSSOServiceProvider::SESSION_REQUEST_IDS, array_slice( $pending, -self::MAX_PENDING ) );

		return redirect()->away( $login['url'] );
	}

	/**
	 * Receives the identity provider's response.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function acs( Request $request ): RedirectResponse {
		$complete = rtrim( (string) config( 'app.url' ), '/' ) . '/wporgsso/complete';

		try {
			$result = Saml::from_config()->validate( (string) $request->input( 'SAMLResponse', '' ) );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] ' . $e->getMessage() );

			return redirect()->away( $complete, 303 );
		}

		// Only responses signed by login.wordpress.org get this far, so nobody else can fill the cache.
		$key = Str::random( 40 );
		\Cache::put( self::handoff_key( $key ), $result, self::HANDOFF_MINUTES );

		return redirect()->away( $complete . '?key=' . $key, 303 );
	}

	/**
	 * Logs the browser in, if the response answers its login request.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function complete( Request $request ): RedirectResponse {
		$key     = $request->query( 'key', '' );
		$result  = is_string( $key ) && '' !== $key ? \Cache::pull( self::handoff_key( $key ) ) : null;
		$pending = (array) $request->session()->get( WPOrgSSOServiceProvider::SESSION_REQUEST_IDS, array() );

		// Refuses responses to another browser's request, so nobody can log someone else into their account.
		$index = is_array( $result ) ? array_search( (string) $result['in_response_to'], $pending, true ) : false;
		if ( false === $index ) {
			return self::fail( __( 'Your WordPress.org login expired or could not be verified. Please try again.' ) );
		}

		unset( $pending[ $index ] );
		$request->session()->put( WPOrgSSOServiceProvider::SESSION_REQUEST_IDS, array_values( $pending ) );

		$username = (string) $result['username'];
		$user     = Account::user_for( $username );

		if ( ! $user || ! $user->isActive() ) {
			return self::fail( __( 'The WordPress.org account :username has no access to this helpdesk.', array( 'username' => $username ) ) );
		}

		try {
			$wporg_user = WordPressOrgUser::find( $username, Client::from_config() );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] Could not check ' . $username . ': ' . $e->getMessage() );

			return self::fail( __( 'WordPress.org could not be reached. Please try again in a moment.' ) );
		}

		if ( ! $wporg_user ) {
			return self::fail( __( 'The WordPress.org account :username has no access to this helpdesk.', array( 'username' => $username ) ) );
		}

		$error = $wporg_user->login_error( $username );
		if ( $error ) {
			return self::fail( $error );
		}

		// Core only activates users through the invite setup, which is closed now; sync saves it.
		$user->invite_state = User::INVITE_STATE_ACTIVATED;
		Passwords::clear_unless_break_glass( $user );
		UserSync::sync( $user, $wporg_user );

		$request->session()->put( WPOrgSSOServiceProvider::SESSION_USERNAME, $username );
		$request->session()->put( WPOrgSSOServiceProvider::SESSION_CHECKED_AT, time() );

		// Tells the login listener this isn't a password login.
		$request->attributes->set( WPOrgSSOServiceProvider::REQUEST_SSO_LOGIN, true );
		\Auth::login( $user );

		return redirect()->intended( route( 'dashboard' ) );
	}

	/**
	 * Serves FreeScout's service provider metadata.
	 *
	 * @return Response
	 */
	public function metadata(): Response {
		return response( Saml::from_config()->metadata(), 200, array( 'Content-Type' => 'application/xml' ) );
	}

	/**
	 * Looks up a WordPress.org account for the user forms.
	 *
	 * @param Request $request Request.
	 * @return JsonResponse
	 */
	public function lookup( Request $request ): JsonResponse {
		$this->authorize( 'create', User::class );

		$username = $request->query( 'username', '' );

		try {
			$wporg_user = WordPressOrgUser::find( is_string( $username ) ? $username : '', Client::from_config() );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] Lookup failed: ' . $e->getMessage() );

			return response()->json( array( 'error' => __( 'WordPress.org could not be reached.' ) ), 502 );
		}

		if ( ! $wporg_user ) {
			return response()->json( array( 'error' => __( 'There is no WordPress.org account with that username.' ) ), 404 );
		}

		$user    = Account::user_for( $wporg_user->username );
		$details = $wporg_user->to_array();

		// Private on WordPress.org, and any account can be looked up; the form fills the email in when the user is created.
		if ( ! $request->user()->isAdmin() ) {
			unset( $details['email'], $details['two_factor'], $details['blocked'] );
		}

		return response()->json(
			array(
				'user'         => $details,
				'connected_to' => $user ? $user->getFullName() : null,
			)
		);
	}

	/**
	 * Cache key of a validated response.
	 *
	 * @param string $key Random key the browser carries.
	 * @return string
	 */
	private static function handoff_key( string $key ): string {
		return WPOrgSSOServiceProvider::ALIAS . '.handoff.' . $key;
	}

	/**
	 * Sends the browser back to the login page with an error.
	 *
	 * @param string $message Error message.
	 * @return RedirectResponse
	 */
	private static function fail( string $message ): RedirectResponse {
		return redirect()->route( 'login' )->with( WPOrgSSOServiceProvider::SESSION_ERROR, $message );
	}
}
