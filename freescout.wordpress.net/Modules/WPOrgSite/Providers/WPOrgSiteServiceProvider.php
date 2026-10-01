<?php
/**
 * WPOrgSite service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;
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

		// Modules come with deploys, so the Modules page only lists installed ones; site.css hides the empty directory.
		\View::composer(
			'modules/modules',
			static function ( View $view ): void {
				$view->with(
					array(
						'modules_directory'   => array(),
						'third_party_modules' => array(),
					)
				);
			}
		);

		$images = array(
			'layout.favicon'     => 'wordpress-mark.svg',
			'layout.header_logo' => 'wordpress-mark-white.svg',
			'login.banner'       => 'wordpress-logo.svg',
		);

		foreach ( $images as $filter => $image ) {
			\Eventy::addFilter( $filter, static fn(): string => asset( \Module::getPublicPath( self::ALIAS ) . '/img/' . $image ) );
		}

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
