<?php
/**
 * WPOrgFreeScoutImport service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\WPOrgFreeScoutImport\Services\RemoteApi;

/**
 * Adds the import pages under Manage, for administrators.
 */
final class WPOrgFreeScoutImportServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgfreescoutimport';

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->app->bind(
			RemoteApi::HTTP,
			static function (): \GuzzleHttp\ClientInterface {
				return RemoteApi::http();
			}
		);
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		try {
			$this->mergeConfigFrom( __DIR__ . '/../Config/config.php', self::ALIAS );
			$this->loadViewsFrom( __DIR__ . '/../Resources/views', self::ALIAS );
			$this->loadRoutesFrom( __DIR__ . '/../Http/routes.php' );
			$this->loadMigrationsFrom( __DIR__ . '/../Database/Migrations' );

			$this->register_hooks();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgFreeScoutImport] Could not boot: ' . $e->getMessage() );
		}
	}

	/**
	 * Registers the Eventy hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		\Eventy::addAction(
			'menu.manage.append',
			static function (): void {
				try {
					$user = auth()->user();
					if ( $user && $user->isAdmin() ) {
						echo view( self::ALIAS . '::menu' )->render();
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgFreeScoutImport] Could not add the menu item: ' . $e->getMessage() );
				}
			}
		);

		// The Users page searches users with FreeScout's own select2; the import page refreshes while running.
		\Eventy::addFilter(
			'javascripts',
			static function ( mixed $javascripts = array() ): mixed {
				$scripts = array(
					'wporgfreescoutimport.users' => 'users.js',
					'wporgfreescoutimport.index' => 'import.js',
				);

				try {
					$route = \Route::current();
					if ( is_array( $javascripts ) && $route && isset( $scripts[ $route->getName() ] ) ) {
						$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/' . $scripts[ $route->getName() ];
					}
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgFreeScoutImport] Could not add its scripts: ' . $e->getMessage() );
				}

				return $javascripts;
			}
		);

		// Highlights Manage while the pages are open.
		\Eventy::addFilter(
			'menu.selected',
			static function ( mixed $menu = null ): mixed {
				if ( is_array( $menu ) && isset( $menu['manage'] ) && is_array( $menu['manage'] ) ) {
					$menu['manage'][ self::ALIAS ] = array( 'wporgfreescoutimport.index', 'wporgfreescoutimport.users' );
				}

				return $menu;
			}
		);
	}
}
