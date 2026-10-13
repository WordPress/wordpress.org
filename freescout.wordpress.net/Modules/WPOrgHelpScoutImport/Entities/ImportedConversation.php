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
 * @property int|null    $creator_id       HelpScout user who started it, if a user did.
 * @property int|null    $assignee_id      HelpScout user or team it was assigned to.
 * @property int|null    $closer_id        HelpScout user who closed it.
 * @property string|null $tags             JSON list of HelpScout's tag names.
 * @property string|null $custom_fields    JSON list of HelpScout's custom fields, with their names and values.
 * @property string|null $written_tags     JSON object of HelpScout's tags the last import gave the Tags module, and
 *                                         whether it added each; null until one did.
 * @property string|null $written_values   JSON object of the values the last import gave the Custom Fields module, by
 *                                         custom field ID; null until one did.
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
	protected $fillable = array( 'helpscout_id', 'helpscout_number', 'conversation_id', 'mailbox_id', 'creator_id', 'assignee_id', 'closer_id', 'tags', 'custom_fields', 'written_tags', 'written_values' );
}
