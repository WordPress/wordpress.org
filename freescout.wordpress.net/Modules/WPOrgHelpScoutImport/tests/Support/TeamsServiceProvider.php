<?php
/**
 * Stands in for the Teams module's service provider, which lists its teams, without the module.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\Teams\Providers;

use App\User;
use Illuminate\Support\Collection;

if ( ! class_exists( TeamsServiceProvider::class ) ) {
	/**
	 * Lists the teams tests made, as the module lists its own.
	 */
	final class TeamsServiceProvider {

		/**
		 * IDs of the teams tests made.
		 *
		 * @var int[]
		 */
		public static $team_ids = array();

		/**
		 * The teams tests made.
		 *
		 * @return Collection
		 */
		public static function getTeams(): Collection { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- The module's name for it.
			return User::query()->whereKey( self::$team_ids )->get();
		}
	}
}
