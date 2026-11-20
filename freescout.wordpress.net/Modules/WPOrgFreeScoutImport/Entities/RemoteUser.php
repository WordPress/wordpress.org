<?php
/**
 * The user here that a source's user is credited to.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporgfreescoutimport_users.
 *
 * @property int  $id
 * @property int  $source_id
 * @property int  $remote_user_id
 * @property int  $user_id
 * @property bool $created Whether an import created the user, rather than finding them here.
 */
final class RemoteUser extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporgfreescoutimport_users';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected $fillable = array( 'source_id', 'remote_user_id', 'user_id', 'created' );

	/**
	 * Attribute casts.
	 *
	 * @var array
	 */
	protected $casts = array( 'created' => 'boolean' );
}
