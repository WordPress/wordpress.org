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
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgSSO\Console\ConnectAccount;
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
	 * Request attribute carrying the WordPress.org account a user form resolved.
	 *
	 * @var string
	 */
	public const REQUEST_ACCOUNT = 'wporgsso.account';

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
		$this->commands( array( ConnectAccount::class ) );

		$this->app['router']->pushMiddlewareToGroup( 'web', RequireWordPressOrgLogin::class );

		$this->register_hooks();
	}

	/**
	 * Request attribute marking a login as one through WordPress.org.
	 *
	 * @var string
	 */
	public const REQUEST_SSO_LOGIN = 'wporgsso.sso_login';

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

		\Eventy::addFilter(
			'auth.password_reset_available',
			static function ( bool $available ): bool {
				return $available && self::passwords_available();
			}
		);

		\Eventy::addAction(
			'user.create.before_email',
			static function (): void {
				// Without the API, core's own form stays, rather than one that can't look anyone up.
				if ( Client::from_config()->is_configured() ) {
					self::render( 'create_user', array( 'passwords' => self::passwords_available() ) );
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
						'username'       => $account ? $account->username : '',
						'can_connect'    => ! $account && (bool) optional( auth()->user() )->isAdmin() && Client::from_config()->is_configured(),
						'password_login' => self::passwords_available(),
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
					if ( ! self::passwords_available() ) {
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

		\Event::listen(
			Login::class,
			static function ( Login $event ): void {
				if ( ! request()->attributes->get( self::REQUEST_SSO_LOGIN ) ) {
					\Log::warning( '[WPOrgSSO] Password login by ' . $event->user->email . '.' );
				}
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
