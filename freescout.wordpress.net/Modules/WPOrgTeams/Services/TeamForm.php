<?php
/**
 * The Teams module's team form, with the permissions the team grants.
 *
 * @package WordPressdotorg\FreeScout\WPOrgTeams
 */

declare( strict_types = 1 );

namespace Modules\WPOrgTeams\Services;

use App\User;
use Modules\WPOrgTeams\Http\Middleware\TeamAccess;
use Modules\WPOrgTeams\Providers\WPOrgTeamsServiceProvider;

/**
 * Renders the Teams module's own form and adds the permissions before its Save button.
 *
 * The form has no hook to add fields with. Rendering it, rather than keeping a copy, means the module's updates to it
 * come through; if the Save button can't be found anymore, the form is shown without the permissions.
 */
final class TeamForm {

	/**
	 * Renders the team form.
	 *
	 * @param array $data The Teams module's view data.
	 * @return string HTML.
	 */
	public static function render( array $data ): string {
		$html = \View::make( WPOrgTeamsServiceProvider::TEAMS_VIEWS . '::update', $data )->render();

		try {
			$team   = $data['team'] ?? null;
			$fields = \View::make(
				WPOrgTeamsServiceProvider::ALIAS . '::team_permissions',
				array(
					'permissions' => User::getUserPermissionsList(),
					'checked'     => self::checked( $team instanceof User && $team->exists ? $team : null ),
				)
			)->render();

			$with_fields = self::insert( $html, $fields );
			if ( null === $with_fields ) {
				\Log::error( '[WPOrgTeams] The team form has no Save button to add the permissions before; it shows without them.' );

				return $html;
			}

			return $with_fields;
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgTeams] Could not add the permissions to the team form: ' . $e->getMessage() );

			return $html;
		}
	}

	/**
	 * Adds fields before the form group of a form's submit button.
	 *
	 * @param string $html   The page.
	 * @param string $fields The fields.
	 * @return string|null The page with the fields, or null if there's no submit button in a form group.
	 */
	public static function insert( string $html, string $fields ): ?string {
		$button = strpos( $html, '<button type="submit"' );
		if ( false === $button ) {
			return null;
		}

		$group = strrpos( substr( $html, 0, $button ), '<div class="form-group">' );
		if ( false === $group || false === strpos( substr( $html, 0, $group ), '<form' ) ) {
			return null;
		}

		return substr( $html, 0, $group ) . $fields . substr( $html, $group );
	}

	/**
	 * The permissions to check: what the form sent if it failed validation, or else what the team grants.
	 *
	 * @param User|null $team The team; null for a new one.
	 * @return string[] Permission IDs.
	 */
	private static function checked( ?User $team ): array {
		if ( null !== old( TeamAccess::PERMISSIONS_SHOWN_FIELD ) ) {
			return array_map( 'strval', (array) old( TeamAccess::PERMISSIONS_FIELD, array() ) );
		}

		return $team ? array_map( 'strval', Teams::permissions( $team ) ) : array();
	}
}
