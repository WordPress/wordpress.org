<?php
/**
 * OpenAI Models registry for WordPress.org GlotPress.
 *
 * Single source of truth for the OpenAI models offered to translators on
 * translate.wordpress.org, plus the resolver that migrates stored values
 * that are no longer in the offered list to the cheapest current model.
 *
 * @package WordPressdotorg\GlotPress\Customizations\AI
 * @license http://www.gnu.org/licenses/gpl-2.0.html GNU General Public License
 */

namespace WordPressdotorg\GlotPress\Customizations\AI;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of supported OpenAI models and migration logic for retired ones.
 */
class OpenAI_Models {

	/**
	 * Models currently offered in the /settings/ dropdown.
	 * Ordered by tier (Expensive → Medium → Cheap) and by price within tier.
	 *
	 * @var string[]
	 */
	const KEPT = array(
		'gpt-5.5-pro',
		'gpt-5.5',
		'gpt-5.4',
		'gpt-5.4-mini',
		'gpt-5.4-nano',
	);

	/**
	 * Same set as KEPT, grouped for UI rendering as <optgroup> labels.
	 *
	 * Tier label strings are English here; the settings template wraps each
	 * label in __( ..., 'glotpress' ) via an explicit literal lookup so PHPCS
	 * accepts the i18n calls.
	 *
	 * @var array<string, string[]>
	 */
	const TIERS = array(
		'Expensive' => array( 'gpt-5.5-pro' ),
		'Medium'    => array( 'gpt-5.5', 'gpt-5.4' ),
		'Cheap'     => array( 'gpt-5.4-mini', 'gpt-5.4-nano' ),
	);

	/**
	 * Model id used when the stored value is not in KEPT. Deliberately the
	 * cheapest model so a silent migration never pushes a user into a more
	 * expensive tier without their explicit click.
	 *
	 * @var string
	 */
	const FALLBACK = 'gpt-5.4-nano';

	/**
	 * Resolve a stored model id to a currently-supported one.
	 *
	 * If the stored value is in KEPT, returns it unchanged. Otherwise returns
	 * FALLBACK and idempotently writes that value back to the current user's
	 * `gp_default_sort` option so storage converges on the new schema.
	 *
	 * @param string $stored The model id read from the user option (or supplied via config).
	 * @return string A model id guaranteed to be in KEPT.
	 */
	public static function resolve_for_current_user( string $stored ): string {
		if ( in_array( $stored, self::KEPT, true ) ) {
			return $stored;
		}

		self::persist_migration( self::FALLBACK );
		return self::FALLBACK;
	}

	/**
	 * Write the migrated model back into the current user's `gp_default_sort`
	 * option, only when the value actually changes. No-op for logged-out
	 * callers (CLI, cron) and for users whose option already matches.
	 *
	 * @param string $new_model The replacement model id (must be in KEPT).
	 */
	private static function persist_migration( string $new_model ): void {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		$opts = get_user_option( 'gp_default_sort', $user_id );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}

		if ( isset( $opts['openai_model'] ) && $opts['openai_model'] === $new_model ) {
			return;
		}

		$opts['openai_model'] = $new_model;
		update_user_option( $user_id, 'gp_default_sort', $opts );
	}
}
