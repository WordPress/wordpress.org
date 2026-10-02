<?php
/**
 * Saves a sender's WordPress.org avatar as their photo.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSidebar\Jobs;

use App\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Downloads the avatar once, so agents' browsers never load it from Gravatar.
 */
final class SyncSenderAvatar implements ShouldQueue {
	use Dispatchable;
	use InteractsWithQueue;
	use Queueable;
	use SerializesModels;

	/**
	 * Hosts avatars may come from.
	 *
	 * @var string[]
	 */
	private const HOSTS = array( 'gravatar.com', 'secure.gravatar.com', '0.gravatar.com', '1.gravatar.com', '2.gravatar.com' );

	/**
	 * Sender ID.
	 *
	 * @var int
	 */
	public $customer_id;

	/**
	 * Avatar URL, which answers 404 if the account has none.
	 *
	 * @var string
	 */
	public $avatar_url;

	/**
	 * Constructor.
	 *
	 * @param int    $customer_id Sender ID.
	 * @param string $avatar_url  Avatar URL.
	 */
	public function __construct( int $customer_id, string $avatar_url ) {
		$this->customer_id = $customer_id;
		$this->avatar_url  = $avatar_url;
	}

	/**
	 * Whether an avatar URL is one this job fetches.
	 *
	 * @param string $url Avatar URL.
	 * @return bool
	 */
	public static function is_avatar_url( string $url ): bool {
		return 'https' === parse_url( $url, PHP_URL_SCHEME ) && in_array( parse_url( $url, PHP_URL_HOST ), self::HOSTS, true );
	}

	/**
	 * Updates the photo; failures are logged, not retried, since the sidebar tries again later.
	 *
	 * @return void
	 */
	public function handle(): void {
		$customer = Customer::find( $this->customer_id );

		// A photo an agent uploaded stays.
		if ( ! $customer || ( $customer->photo_url && Customer::PHOTO_TYPE_GRAVATAR !== (int) $customer->photo_type ) ) {
			return;
		}

		try {
			// False when the account has no avatar, which answers 404.
			if ( $customer->setPhotoFromRemoteFile( $this->avatar_url ) ) {
				$customer->photo_type = Customer::PHOTO_TYPE_GRAVATAR;
				$customer->save();
			} elseif ( $customer->photo_url && $this->avatar_is_gone() ) {
				$customer->removePhoto();
				$customer->photo_type = null;
				$customer->save();
			}
		} catch ( \Throwable $e ) {
			\Log::error( '[WPOrgSidebar] Could not update the photo of sender ' . $customer->id . ': ' . $e->getMessage() );
		}
	}

	/**
	 * Whether the account's avatar was removed; an unreachable Gravatar is inconclusive, so the copy stays.
	 *
	 * @return bool
	 */
	private function avatar_is_gone(): bool {
		$context = stream_context_create(
			array(
				'http' => array(
					'method'          => 'HEAD',
					'timeout'         => 10,
					// Stays on the Gravatar host is_avatar_url() allowed, and leaves one status line to check.
					'follow_location' => 0,
				),
			)
		);
		$headers = get_headers( $this->avatar_url, false, $context );

		return is_array( $headers ) && 1 === preg_match( '#^HTTP/\S+ 404\b#', $headers[0] );
	}
}
