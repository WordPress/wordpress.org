<?php
/**
 * Which HelpScout thread a FreeScout thread was imported from.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporghelpscoutimport_threads.
 *
 * @property int $id
 * @property int $helpscout_id
 * @property int $thread_id
 */
final class ImportedThread extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporghelpscoutimport_threads';

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
	protected $fillable = array( 'helpscout_id', 'thread_id' );
}
