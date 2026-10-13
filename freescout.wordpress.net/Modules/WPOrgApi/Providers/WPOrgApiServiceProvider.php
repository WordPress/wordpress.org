<?php
/**
 * WPOrgApi service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgApi
 */

declare( strict_types = 1 );

namespace Modules\WPOrgApi\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the endpoints WordPress.org calls.
 */
final class WPOrgApiServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgapi';

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function register(): void {
		$this->hide_secret_from_debug_pages();
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->mergeConfigFrom( __DIR__ . '/../Config/config.php', self::ALIAS );
		$this->loadRoutesFrom( __DIR__ . '/../Http/routes.php' );
	}

	/**
	 * Keeps the signing secret off the Whoops error pages that APP_DEBUG shows, which list the environment.
	 *
	 * @return void
	 */
	private function hide_secret_from_debug_pages(): void {
		$blacklist = (array) config( 'app.debug_blacklist', array() );

		foreach ( array( '_ENV', '_SERVER' ) as $key ) {
			$blacklist[ $key ] = (array) ( $blacklist[ $key ] ?? array() );

			if ( ! in_array( 'WPORG_API_SECRET', $blacklist[ $key ], true ) ) {
				$blacklist[ $key ][] = 'WPORG_API_SECRET';
			}
		}

		config( array( 'app.debug_blacklist' => $blacklist ) );
	}
}
