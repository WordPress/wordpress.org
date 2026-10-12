<?php
/**
 * Which HelpScout saved reply a FreeScout saved reply was imported from.
 *
 * @package WordPressdotorg\FreeScout\WPOrgHelpScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgHelpScoutImport\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * Row in wporghelpscoutimport_saved_replies.
 *
 * @property int         $id
 * @property int         $helpscout_id
 * @property int         $mailbox_id   FreeScout mailbox it was imported into.
 * @property int         $saved_reply_id
 * @property string|null $source_hash  Hash of HelpScout's name and text when it was last imported.
 * @property string|null $written_hash Hash of the name and text it was given in FreeScout; null if it was FreeScout's.
 * @property string|null $images       JSON object of its images' copies' attachment IDs, by their URL in HelpScout's
 *                                    text.
 * @property int|null    $run_id       Run that last checked it.
 */
final class ImportedSavedReply extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected $table = 'wporghelpscoutimport_saved_replies';

	/**
	 * Mass-assignable attributes.
	 *
	 * @var array
	 */
	protected $fillable = array( 'helpscout_id', 'mailbox_id', 'saved_reply_id', 'source_hash', 'written_hash', 'images', 'run_id' );

	/**
	 * Hash of a saved reply's name and text, to tell whether either changed.
	 *
	 * @param string $name Name.
	 * @param string $text Text.
	 * @return string
	 */
	public static function hash( string $name, string $text ): string {
		return hash( 'sha256', $name . "\0" . $text );
	}
}
