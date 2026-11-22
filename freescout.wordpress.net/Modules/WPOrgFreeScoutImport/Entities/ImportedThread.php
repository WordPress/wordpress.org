<?php
/**
 * Which thread of a source a thread was imported from.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporgfreescoutimport_threads.
 *
 * @property int      $id
 * @property int      $source_id
 * @property int      $remote_id
 * @property int      $thread_id
 * @property int|null $remote_user_id     Remote user who wrote it, for replies, notes, and line items.
 * @property int|null $remote_assignee_id Remote user the conversation was assigned to then.
 */
final class ImportedThread extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporgfreescoutimport_threads';

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
	protected $fillable = array( 'source_id', 'remote_id', 'thread_id', 'remote_user_id', 'remote_assignee_id' );
}
