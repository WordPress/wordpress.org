<?php
/**
 * WPOrgSearch service provider.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSearch
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSearch\Providers;

use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Modules\WPOrgSearch\Services\Search;

/**
 * Takes over core's conversation search, to run it faster.
 */
final class WPOrgSearchServiceProvider extends ServiceProvider {

	/**
	 * Module alias.
	 *
	 * @var string
	 */
	public const ALIAS = 'wporgsearch';

	/**
	 * Registers the module.
	 *
	 * @return void
	 */
	public function register(): void {
		// One per request: it remembers whether the search got the default date, for the page to say so.
		$this->app->singleton( Search::class );
	}

	/**
	 * Boots the module.
	 *
	 * @return void
	 */
	public function boot(): void {
		$this->register_hooks();
	}

	/**
	 * Registers the Eventy hooks.
	 *
	 * Callbacks take untyped, optional arguments and check them, so a change in what core passes can't throw where
	 * core doesn't catch it. If anything fails, core's own search runs.
	 *
	 * @return void
	 */
	private function register_hooks(): void {
		\Eventy::addFilter(
			'search.filters',
			static function ( $filters = array(), $mode = null, $request = null ) {
				try {
					if ( ! is_array( $filters ) || ! $request instanceof Request ) {
						return $filters;
					}

					return app( Search::class )->default_filters( $filters, self::search_query( $request ) );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSearch] Could not set the default search filters: ' . $e->getMessage() );

					return $filters;
				}
			},
			20,
			3
		);

		\Eventy::addFilter(
			'search.conversations.perform',
			static function ( $result = '', $q = '', $filters = array(), $user = null ) {
				// Another module searched already.
				if ( '' !== $result || ! $user instanceof User ) {
					return $result;
				}

				try {
					return app( Search::class )->conversations( trim( (string) $q ), is_array( $filters ) ? $filters : array(), $user );
				} catch ( \Throwable $e ) {
					\Log::error( '[WPOrgSearch] Could not search conversations: ' . $e->getMessage() );

					return $result;
				}
			},
			20,
			4
		);

		\Eventy::addAction(
			'search.display_filters',
			static function (): void {
				if ( app( Search::class )->timed_out() ) {
					echo '<div class="col-sm-12 text-danger">' . e( 'This search took too long and was stopped. Pick a later After date or add another filter.' ) . '</div>';
				}

				if ( app( Search::class )->was_defaulted() ) {
					echo '<div class="col-sm-12 text-help">' . e( 'Searches without other filters only cover conversations started in the past year, so they don’t time out. To search older ones, pick an earlier After date or add another filter.' ) . '</div>';
				}
			},
			20,
			0
		);
	}

	/**
	 * The search query in a request, where core looks for it: the search page's, or its AJAX pages'.
	 *
	 * @param Request $request Request.
	 * @return string
	 */
	private static function search_query( Request $request ): string {
		$q = $request->input( 'q' );
		if ( ! is_string( $q ) || '' === $q ) {
			$q = $request->input( 'filter.q' );
		}

		return is_string( $q ) ? trim( $q ) : '';
	}
}
