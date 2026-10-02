<?php
/**
 * A HelpScout user the importer met in a conversation.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporghelpscoutimport_people.
 *
 * HelpScout no longer lists users deleted from it, but their threads still name them; this keeps who they were.
 *
 * @property int         $id
 * @property int         $helpscout_user_id
 * @property string      $first_name
 * @property string      $last_name
 * @property string|null $email
 */
final class Person extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporghelpscoutimport_people';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected $fillable = array( 'helpscout_user_id', 'first_name', 'last_name', 'email' );
}
