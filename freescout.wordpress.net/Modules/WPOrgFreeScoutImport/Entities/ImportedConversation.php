<?php
/**
 * Which conversation of the other FreeScout a conversation was imported from.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporgfreescoutimport_conversations.
 *
 * @property int         $id
 * @property int         $source_id
 * @property int         $source_number The number it had in the other FreeScout.
 * @property int         $conversation_id
 * @property int|null    $creator_id    Source user who started it.
 * @property int|null    $assignee_id   Source user it was assigned to.
 * @property int|null    $closer_id     Source user who closed it.
 * @property string|null $written_tags  The source's tags the last import gave it, as JSON.
 */
final class ImportedConversation extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporgfreescoutimport_conversations';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected $fillable = array( 'source_id', 'source_number', 'conversation_id', 'creator_id', 'assignee_id', 'closer_id', 'written_tags' );
}
