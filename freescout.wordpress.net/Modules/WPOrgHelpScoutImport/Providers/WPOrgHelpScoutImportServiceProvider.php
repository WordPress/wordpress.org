<?php
/**
 * WPOrgHelpScoutImport service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\WPOrgHelpScoutImport\Services\HelpScout;

/**
 * Adds the import page under Manage, for administrators.
 */
final class WPOrgHelpScoutImportServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporghelpscoutimport';

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hide_secret_from_debug_pages();

		$this->app->bind(
			HelpScout::class,
			static function (): HelpScout {
				return HelpScout::from_config();
			}
		);
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

		$this->register_hooks();
	}

	/**
	 * Keeps the app secret off the Whoops error pages that APP_DEBUG shows, which list the environment.
	 *
	 * @return void
	 */
	private function hide_secret_from_debug_pages(): void {
		$blacklist = (array) config( 'app.debug_blacklist', array() );

		foreach ( array( '_ENV', '_SERVER' ) as $key ) {
			$blacklist[ $key ] = (array) ( $blacklist[ $key ] ?? array() );

			if ( ! in_array( 'WPORG_HELPSCOUT_APP_SECRET', $blacklist[ $key ], true ) ) {
				$blacklist[ $key ][] = 'WPORG_HELPSCOUT_APP_SECRET';
			}
		}

		config( array( 'app.debug_blacklist' => $blacklist ) );
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
					\Log::error( '[WPOrgHelpScoutImport] Could not add the menu item: ' . $e->getMessage() );
				}
			}
		);

		// Highlights Manage while the page is open.
		\Eventy::addFilter(
			'menu.selected',
			static function ( $menu = null ) {
				if ( is_array( $menu ) && isset( $menu['manage'] ) && is_array( $menu['manage'] ) ) {
					$menu['manage'][ self::ALIAS ] = 'wporghelpscoutimport.index';
				}

				return $menu;
			}
		);
	}
}
