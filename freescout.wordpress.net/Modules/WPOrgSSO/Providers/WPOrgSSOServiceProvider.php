<?php
/**
 * WPOrgSSO service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSSO
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSSO\Providers;

use App\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Modules\WPOrgSSO\Console\ConnectAccount;
use Modules\WPOrgSSO\Console\SetPassword;
use Modules\WPOrgSSO\Entities\Account;
use Modules\WPOrgSSO\Http\Middleware\RequireWordPressOrgLogin;
use Modules\WPOrgSSO\Services\Client;
use Modules\WPOrgSSO\Services\Saml;
use Modules\WPOrgSSO\Services\UserSync;
use Modules\WPOrgSSO\Services\WordPressOrgUser;

/**
 * Signs agents in with their WordPress.org account, and connects every user to one.
 */
final class WPOrgSSOServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgsso';

	/**
	 * Session key of the IDs of pending login requests.
	 *
	 * @var string
	 */
	public const SESSION_REQUEST_IDS = 'wporgsso.request_ids';

	/**
	 * Session key of the WordPress.org username the session logged in with.
	 *
	 * @var string
	 */
	public const SESSION_USERNAME = 'wporgsso.username';

	/**
	 * Session key of when the WordPress.org account was last checked for this session.
	 *
	 * @var string
	 */
	public const SESSION_CHECKED_AT = 'wporgsso.checked_at';

	/**
	 * Session key of the error shown on the login page.
	 *
	 * @var string
	 */
	public const SESSION_ERROR = 'wporgsso.error';

	/**
	 * Session key marking an administrator's password login in break-glass mode.
	 *
	 * @var string
	 */
	public const SESSION_PASSWORD_LOGIN = 'wporgsso.password_login';

	/**
	 * Session key marking a login without WordPress.org that happened after it was enforced, which is ended.
	 *
	 * @var string
	 */
	public const SESSION_REFUSED = 'wporgsso.refused';

	/**
	 * Request attribute carrying the WordPress.org account a user form resolved.
	 *
	 * @var string
	 */
	public const REQUEST_ACCOUNT = 'wporgsso.account';

	/**
	 * Request attribute marking a login as one through WordPress.org.
	 *
	 * @var string
	 */
	public const REQUEST_SSO_LOGIN = 'wporgsso.sso_login';

	/**
	 * Registers the module's dependencies.
	 *
	 * @return void
	 */
	public function register(): void {
		require_once __DIR__ . '/../vendor/autoload.php';
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->mergeConfigFrom( __DIR__ . '/../Config/config.php', self::ALIAS );
		$this->loadViewsFrom( __DIR__ . '/../Resources/views', self::ALIAS );
		$this->loadRoutesFrom( __DIR__ . '/../Http/routes.php' );
		$this->loadMigrationsFrom( __DIR__ . '/../Database/Migrations' );
		$this->commands( array( ConnectAccount::class, SetPassword::class ) );

		$this->app['router']->pushMiddlewareToGroup( 'web', RequireWordPressOrgLogin::class );

		$this->register_hooks();
	}

	/**
	 * Whether administrators may log in with their FreeScout password.
	 *
	 * @return bool
	 */
	public static function password_login_enabled(): bool {
		return (bool) config( self::ALIAS . '.password_login' );
	}

	/**
	 * Whether WordPress.org is the only way in: it takes both the identity provider and the API.
	 *
	 * @return bool
	 */
	public static function enforced(): bool {
		if ( ! Saml::from_config()->is_configured() ) {
			return false;
		}

		if ( ! Client::from_config()->is_configured() ) {
			// Once an hour, not on every request.
			if ( \Cache::add( self::ALIAS . '.misconfigured', 1, 60 ) ) {
				\Log::error( '[WPOrgSSO] The identity provider is configured, but not WPORG_API_SECRET; logins stay as they are.' );
			}

			return false;
		}

		return true;
	}

	/**
	 * Whether FreeScout passwords still work: before WordPress.org is enforced, and in break-glass mode.
	 *
	 * @return bool
	 */
	public static function passwords_available(): bool {
		return self::password_login_enabled() || ! self::enforced();
	}

	/**
	 * Registers the Eventy hooks and event listeners.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		\Eventy::addFilter(
			'javascripts',
			static function ( array $javascripts ): array {
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/users.js';

				return $javascripts;
			}
		);

		// Reset links would get around WordPress.org's two-factor authentication, even in break-glass mode.
		\Eventy::addFilter(
			'auth.password_reset_available',
			static function ( bool $available ): bool {
				return $available && ! self::enforced();
			}
		);

		\Eventy::addAction(
			'user.create.before_email',
			static function (): void {
				// Without the API, core's own form stays, rather than one that can't look anyone up.
				if ( Client::from_config()->is_configured() ) {
					self::render( 'create_user', array( 'passwords' => ! self::enforced() ) );
				}
			}
		);

		\Eventy::addAction(
			'user.edit.before_first_name',
			static function ( User $user ): void {
				$account = Account::for_user( (int) $user->id );

				self::render(
					'edit_user',
					array(
						'username'        => $account ? $account->username : '',
						'can_connect'     => ! $account && (bool) optional( auth()->user() )->isAdmin() && Client::from_config()->is_configured(),
						'password_login'  => self::passwords_available(),
						'password_emails' => ! self::enforced(),
					)
				);
			}
		);

		\Eventy::addFilter(
			'user.create_save',
			static function ( User $user, Request $request ): User {
				$wporg_user = $request->attributes->get( self::REQUEST_ACCOUNT );
				if ( $wporg_user instanceof WordPressOrgUser && $user->id ) {
					// There's nothing to invite them to once they log in with WordPress.org.
					if ( self::enforced() ) {
						$user->invite_state = User::INVITE_STATE_ACTIVATED;
					}

					self::connect( $user, $wporg_user );
				}

				return $user;
			},
			20,
			2
		);

		// Connects an existing user an administrator named an account for.
		\Eventy::addFilter(
			'user.save_profile',
			static function ( User $user, Request $request ): User {
				$wporg_user = $request->attributes->get( self::REQUEST_ACCOUNT );
				if ( $wporg_user instanceof WordPressOrgUser && $user->id && ! Account::for_user( (int) $user->id ) ) {
					self::connect( $user, $wporg_user );
				}

				return $user;
			},
			20,
			2
		);

		\Eventy::addAction(
			'user.deleted',
			static function ( User $user ): void {
				$account = Account::for_user( (int) $user->id );
				if ( $account ) {
					$account->delete();
				}
			}
		);

		/*
		 * In break-glass mode, only administrators may log in with a password. Everyone else gets core's answer to a
		 * wrong password, after the same password check and attempt count, so the form confirms nobody's password.
		 */
		\Eventy::addFilter(
			'login.custom_check',
			static function ( $errors, $request = null ) {
				if ( ! $request instanceof Request || ! self::password_login_enabled() || ! self::enforced() ) {
					return $errors;
				}

				$email = $request->input( 'email' );
				$user  = is_string( $email ) ? User::query()->where( 'email', $email )->first() : null;
				if ( ! $user || $user->isAdmin() ) {
					return $errors;
				}

				$password = $request->input( 'password' );
				\Hash::check( is_string( $password ) ? $password : '', (string) $user->password );

				$controller = $request->route() ? $request->route()->getController() : null;
				app( RateLimiter::class )->hit(
					Str::lower( $email ) . '|' . $request->ip(),
					$controller && method_exists( $controller, 'decayMinutes' ) ? (int) $controller->decayMinutes() : 1
				);

				return array( 'email' => array( trans( 'auth.failed' ) ) );
			},
			20,
			2
		);

		/*
		 * Only an administrator's password login in break-glass mode may stay; the middleware ends the session of any
		 * other login without WordPress.org, like one from a reset link or a remember-me cookie, on its next request.
		 * Sessions from before WordPress.org was enforced go on, so whoever switches it on isn't logged out.
		 */
		\Event::listen(
			Login::class,
			static function ( Login $event ): void {
				if ( request()->attributes->get( self::REQUEST_SSO_LOGIN ) || ! self::enforced() ) {
					return;
				}

				$route = request()->route();
				if (
					self::password_login_enabled() &&
					$event->user instanceof User &&
					$event->user->isAdmin() &&
					$route && RequireWordPressOrgLogin::PASSWORD_LOGIN_ACTION === $route->getActionName()
				) {
					request()->session()->put( self::SESSION_PASSWORD_LOGIN, true );
					\Log::warning( '[WPOrgSSO] Login without WordPress.org by ' . $event->user->email . '.' );

					return;
				}

				// Without a session, like on the command line, there's nothing to end.
				if ( request()->hasSession() ) {
					request()->session()->put( self::SESSION_REFUSED, true );
				}
				\Log::warning( '[WPOrgSSO] Refused a login without WordPress.org by ' . $event->user->email . '.' );
			}
		);
	}

	/**
	 * Connects a user to the account a user form named, and fills them in from it.
	 *
	 * @param User             $user       FreeScout user.
	 * @param WordPressOrgUser $wporg_user WordPress.org account.
	 * @return void
	 */
	private static function connect( User $user, WordPressOrgUser $wporg_user ): void {
		try {
			Account::connect( (int) $user->id, $wporg_user->username );
		} catch ( \Illuminate\Database\QueryException $e ) {
			// Only if another administrator connected the same account a moment ago; the user stays unconnected.
			\Log::error( '[WPOrgSSO] Could not connect user ' . $user->id . ' to ' . $wporg_user->username . ': ' . $e->getMessage() );
			\Session::flash( 'flash_error_floating', __( 'The user was saved, but not connected: :username belongs to another user now.', array( 'username' => $wporg_user->username ) ) );

			return;
		}

		UserSync::sync( $user, $wporg_user );
	}

	/**
	 * Renders a module view, logging instead of failing the page.
	 *
	 * @param string $view View name.
	 * @param array  $data View data.
	 * @return void
	 */
	private static function render( string $view, array $data ): void {
		try {
			echo \View::make( self::ALIAS . '::' . $view, $data )->render();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSSO] Could not render ' . $view . ': ' . $e->getMessage() );
		}
	}
}
