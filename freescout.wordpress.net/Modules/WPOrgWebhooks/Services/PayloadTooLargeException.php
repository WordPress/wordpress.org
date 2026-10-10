<?php
/**
 * An event too big for api.wordpress.org to take.
 *
 * @package WordPressdotorg\FreeScout\WPOrgWebhooks
 */

declare( strict_types = 1 );

namespace Modules\WPOrgWebhooks\Services;

use RuntimeException;

/**
 * Thrown when api.wordpress.org's web server refused a request for its size: webhook.php never saw it, so a smaller
 * one can be sent instead.
 */
final class PayloadTooLargeException extends RuntimeException {
}
