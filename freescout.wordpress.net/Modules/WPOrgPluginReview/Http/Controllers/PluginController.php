<?php
/**
 * Plugin controller.
 *
 * @package WordPressdotorg\FreeScout\WPOrgPluginReview
 */

declare( strict_types = 1 );

namespace Modules\WPOrgPluginReview\Http\Controllers;

use App\Conversation;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Modules\WPOrgPluginReview\Services\Dns;
use Modules\WPOrgPluginReview\Services\Plugins;
use Modules\WPOrgPluginReview\Services\Review;
use Modules\WPOrgPluginReview\Services\Reviewers;

/**
 * Tells the Plugin Review panel what isn't in the review emails: the plugin as it is now on WordPress.org, and, for
 * ownership reviews, whether its domains carry the author's verification record.
 *
 * Asked for after the page loads, as WordPress.org and DNS can be slow.
 */
final class PluginController extends Controller {

	/**
	 * How many an agent may ask for a minute.
	 *
	 * @var int
	 */
	public const MAX_PER_MINUTE = 60;

	/**
	 * Returns the plugin of the conversation's latest review, and the ownership check.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @return JsonResponse Plugin, null if it isn't known; owner, null without an ownership review.
	 */
	public function show( int $conversation_id ): JsonResponse {
		// Not the throttle middleware: in this Laravel, it shares a counter with core's upload limit.
		$limiter = app( RateLimiter::class );
		$key     = 'wporgpluginreview.plugin.' . auth()->id();
		if ( $limiter->tooManyAttempts( $key, self::MAX_PER_MINUTE ) ) {
			abort( 429 );
		}
		$limiter->hit( $key );

		$conversation = Conversation::findOrFail( $conversation_id );
		if ( ! auth()->user()->can( 'view', $conversation ) || ! app( Reviewers::class )->includes( auth()->user() ) ) {
			abort( 403 );
		}

		$review = Review::latest( $conversation );
		if ( ! $review ) {
			abort( 404 );
		}

		$plugin = self::plugin( (int) $review['review_id']['plugin_id'], (string) $review['review_id']['slug'], $conversation );

		return response()->json(
			array(
				'plugin' => $plugin,
				'owner'  => $review['owner'] ? self::owner( $review['owner'], $plugin ) : null,
			)
		);
	}

	/**
	 * The plugin as it is now; never throws, as the panel works without it.
	 *
	 * @param int          $plugin_id    Plugin ID, 0 if the review doesn't say.
	 * @param string       $slug         The plugin's slug, as the review knew it; empty if it doesn't say.
	 * @param Conversation $conversation Conversation.
	 * @return array|null
	 */
	private static function plugin( int $plugin_id, string $slug, Conversation $conversation ): ?array {
		if ( ( ! $plugin_id && '' === $slug ) || ! Plugins::is_configured() ) {
			return null;
		}

		try {
			return Plugins::get( $plugin_id, $slug, $conversation );
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgPluginReview] Could not get plugin ' . ( $plugin_id ? $plugin_id : $slug ) . ': ' . $e->getMessage() );

			return null;
		}
	}

	/**
	 * Whether the domains the plugin declares carry the author's record, and whether the submitter's email address is at
	 * one of them: at the domain itself, not at a subdomain of it.
	 *
	 * @param array      $owner  What the review says about the owner, see Review::latest().
	 * @param array|null $plugin The plugin as it is now.
	 * @return array Author, plugin, and email: whether each matches; email is null without a submitter.
	 */
	private static function owner( array $owner, ?array $plugin ): array {
		$submitter = is_array( $plugin['submitter'] ?? null ) ? $plugin['submitter'] : null;

		// Review IDs only name the account when the review tools knew it; the submitter is the same person.
		$username = '' !== $owner['username'] ? $owner['username'] : (string) ( $submitter['username'] ?? '' );
		$dns      = app( Dns::class );
		$email    = null;

		if ( $submitter ) {
			$host  = strtolower( substr( (string) strrchr( (string) $submitter['email'], '@' ), 1 ) );
			$email = '' !== $host && in_array( $host, array( $owner['author_host'], $owner['plugin_host'] ), true );
		}

		return array(
			'author' => $dns->verifies( $owner['author_host'], $username ),
			'plugin' => $dns->verifies( $owner['plugin_host'], $username ),
			'email'  => $email,
		);
	}
}
