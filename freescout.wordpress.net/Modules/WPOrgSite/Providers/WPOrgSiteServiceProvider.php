<?php
/**
 * WPOrgSite service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Providers;

use Illuminate\Support\ServiceProvider;
use Modules\WPOrgSite\Http\Middleware\LockModules;

/**
 * Adapts FreeScout to how WordPress.org runs it.
 */
final class WPOrgSiteServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgsite';

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->app['router']->pushMiddlewareToGroup( 'web', LockModules::class );

		\Eventy::addFilter(
			'stylesheets',
			static function ( array $styles ): array {
				$styles[] = \Module::getPublicPath( self::ALIAS ) . '/css/site.css';

				return $styles;
			}
		);

		\Eventy::addFilter(
			'javascripts',
			static function ( array $javascripts ): array {
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/modules.js';

				return $javascripts;
			}
		);
	}
}
