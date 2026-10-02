<?php
/**
 * WPOrgSidebar service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Providers;

use App\Conversation;
use App\Mailbox;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgSidebar\Services\Client;
use Modules\WPOrgSidebar\Services\Panels;

/**
 * Registers the WordPress.org panels in the conversation sidebar, and their settings.
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

		$this->register_settings();
	}

	/**
	 * Adds a section under Manage » Settings for each panel with `per_mailbox`, to choose the mailboxes it shows in.
	 *
	 * @return void
	 */
	private function register_settings(): void {
		\Eventy::addFilter(
			'settings.sections',
			static function ( array $sections ): array {
				$order = 600;
				foreach ( Panels::per_mailbox() as $panel_id => $panel ) {
					$sections[ Panels::SECTION_PREFIX . $panel_id ] = array(
						'title' => __( ':panel Panel', array( 'panel' => $panel['title'] ) ),
						'icon'  => 'list-alt',
						'order' => $order++,
					);
				}

				return $sections;
			}
		);

		\Eventy::addFilter(
			'settings.section_settings',
			static function ( array $settings, string $section ): array {
				$panel_id = Panels::panel_for_section( $section );
				if ( '' === $panel_id ) {
					return $settings;
				}

				return array( Panels::option( $panel_id ) => Panels::mailbox_ids( $panel_id ) );
			},
			20,
			2
		);

		\Eventy::addFilter(
			'settings.section_params',
			static function ( array $params, string $section ): array {
				$panel_id = Panels::panel_for_section( $section );
				if ( '' === $panel_id ) {
					return $params;
				}

				$params['template_vars'] = array(
					'panel'     => Panels::per_mailbox()[ $panel_id ],
					'option'    => Panels::option( $panel_id ),
					'mailboxes' => Mailbox::query()->orderBy( 'name' )->get(),
				);

				return $params;
			},
			20,
			2
		);

		\Eventy::addFilter(
			'settings.view',
			static function ( string $view, string $section ): string {
				return '' === Panels::panel_for_section( $section ) ? $view : self::ALIAS . '::settings';
			},
			20,
			2
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
					'panels'          => Panels::for_mailbox( (int) $conversation->mailbox_id ),
					'conversation_id' => (int) $conversation->id,
				)
			)->render();
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not render panels: ' . $e->getMessage() );
		}
	}
}
