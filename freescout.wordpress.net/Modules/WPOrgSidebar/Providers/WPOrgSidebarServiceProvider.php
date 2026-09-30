<?php
/**
 * WPOrgSidebar service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Providers;

use App\Conversation;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgSidebar\Services\Client;

/**
 * Registers the WordPress.org panels in the conversation sidebar.
 */
final class WPOrgSidebarServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgsidebar';

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
		$this->loadViewsFrom( __DIR__ . '/../Resources/views', self::ALIAS );
		$this->loadRoutesFrom( __DIR__ . '/../Http/routes.php' );

		$this->register_hooks();
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

	/**
	 * Registers the Eventy hooks.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		\Eventy::addFilter(
			'stylesheets',
			static function ( array $styles ): array {
				$styles[] = \Module::getPublicPath( self::ALIAS ) . '/css/sidebar.css';

				return $styles;
			}
		);

		\Eventy::addFilter(
			'javascripts',
			static function ( array $javascripts ): array {
				$javascripts[] = \Module::getPublicPath( self::ALIAS ) . '/js/sidebar.js';

				return $javascripts;
			}
		);

		\Eventy::addAction(
			'conversation.after_customer_sidebar',
			static function ( Conversation $conversation ): void {
				self::render_panels( $conversation );
			}
		);
	}

	/**
	 * Renders the panel placeholders; sidebar.js fills them in.
	 *
	 * @param Conversation $conversation Conversation being viewed.
	 * @return void
	 */
	private static function render_panels( Conversation $conversation ): void {
		try {
			if ( ! Client::from_config()->is_configured() ) {
				return;
			}

			echo \View::make(
				self::ALIAS . '::sidebar',
				array(
					'panels'          => (array) config( self::ALIAS . '.panels' ),
					'conversation_id' => (int) $conversation->id,
				)
			)->render();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not render panels: ' . $e->getMessage() );
		}
	}
}
