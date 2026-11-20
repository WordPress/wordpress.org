<?php
/**
 * HelpScout's links to conversations.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSite
 */

declare( strict_types = 1 );

namespace Modules\WPOrgSite\Services;

use App\Conversation;
use App\User;
use Illuminate\Support\Facades\Schema;

/**
 * Finds the FreeScout conversation a HelpScout conversation was imported as, from the HelpScout ID in its links.
 *
 * The importer's table of IDs goes with the importer, so this keeps a copy.
 */
final class HelpScoutLinks {

	/**
	 * The table of HelpScout IDs and the conversations they were imported as.
	 *
	 * @var string
	 */
	public const TABLE = 'wporgsite_helpscout_conversations';

	/**
	 * The importer's table, copied when this one is created.
	 *
	 * @var string
	 */
	public const IMPORTER_TABLE = 'wporghelpscoutimport_conversations';

	/**
	 * The importer's model of that table; saving one adds it here.
	 *
	 * @var string
	 */
	public const IMPORTER_MODEL = 'Modules\WPOrgHelpScoutImport\Entities\ImportedConversation';

	/**
	 * Records the conversation a HelpScout conversation was imported as.
	 *
	 * @param int $helpscout_id    HelpScout's conversation ID.
	 * @param int $conversation_id FreeScout's conversation ID.
	 * @return void
	 */
	public static function record( int $helpscout_id, int $conversation_id ): void {
		\DB::table( self::TABLE )->updateOrInsert( array( 'helpscout_id' => $helpscout_id ), array( 'conversation_id' => $conversation_id ) );
	}

	/**
	 * Copies the IDs the importer has recorded so far into the new table.
	 *
	 * @return void
	 */
	public static function copy_imported(): void {
		if ( ! Schema::hasTable( self::IMPORTER_TABLE ) ) {
			return;
		}

		$grammar = \DB::getQueryGrammar();
		\DB::statement( 'INSERT INTO ' . $grammar->wrapTable( self::TABLE ) . ' (helpscout_id, conversation_id) SELECT helpscout_id, conversation_id FROM ' . $grammar->wrapTable( self::IMPORTER_TABLE ) );
	}

	/**
	 * The conversation a HelpScout conversation was imported as, if the agent may see it.
	 *
	 * @param int  $helpscout_id HelpScout's conversation ID.
	 * @param User $user         Agent.
	 * @return Conversation|null The conversation, or null if there's none they may see.
	 */
	public static function conversation( int $helpscout_id, User $user ): ?Conversation {
		$conversation_id = \DB::table( self::TABLE )->where( 'helpscout_id', $helpscout_id )->value( 'conversation_id' );
		$conversation    = $conversation_id ? Conversation::find( (int) $conversation_id ) : null;

		return $conversation instanceof Conversation && $user->can( 'view', $conversation ) ? $conversation : null;
	}
}
