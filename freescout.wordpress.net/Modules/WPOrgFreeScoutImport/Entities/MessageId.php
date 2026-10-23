<?php
/**
 * The Message-ID of an email in a source, which its API doesn't give.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporgfreescoutimport_message_ids.
 *
 * @property int    $id
 * @property int    $source_id
 * @property int    $remote_thread_id
 * @property string $message_id Without angle brackets, as FreeScout stores it.
 */
final class MessageId extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporgfreescoutimport_message_ids';

	/**
	 * The table has no timestamps.
	 *
	 * @var bool
	 */
	public $timestamps = false;

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected $fillable = array( 'source_id', 'remote_thread_id', 'message_id' );
}
