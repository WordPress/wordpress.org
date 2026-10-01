<?php
/**
 * Which HelpScout conversation a FreeScout conversation was imported from.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporghelpscoutimport_conversations.
 *
 * @property int         $id
 * @property int         $helpscout_id
 * @property int         $helpscout_number Number HelpScout showed, which links and notes refer to.
 * @property int         $conversation_id
 * @property string|null $tags             JSON list of HelpScout's tag names.
 * @property string|null $custom_fields    JSON list of HelpScout's custom fields, with their names and values.
 */
final class ImportedConversation extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporghelpscoutimport_conversations';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected $fillable = array( 'helpscout_id', 'helpscout_number', 'conversation_id', 'tags', 'custom_fields' );
}
