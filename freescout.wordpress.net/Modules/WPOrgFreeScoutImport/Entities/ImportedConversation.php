<?php
/**
 * Which conversation of a source a conversation was imported from.
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
 * @property int         $remote_id
 * @property int         $remote_number The number it had there.
 * @property int         $conversation_id
 * @property int|null    $creator_id    Remote user who started it.
 * @property int|null    $assignee_id   Remote user it was assigned to.
 * @property int|null    $closer_id     Remote user who closed it.
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
	protected $fillable = array( 'source_id', 'remote_id', 'remote_number', 'conversation_id', 'creator_id', 'assignee_id', 'closer_id', 'written_tags' );
}
