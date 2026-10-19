<?php
/**
 * An event api.wordpress.org never received.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Services;

use RuntimeException;

/**
 * Thrown when a request certainly didn't reach webhook.php, so sending it again can't count it twice.
 */
final class NotDeliveredException extends RuntimeException {
}
