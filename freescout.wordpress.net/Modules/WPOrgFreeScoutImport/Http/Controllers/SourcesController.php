<?php
/**
 * Adds, changes, and deletes the FreeScouts imported from, and takes their Message-ID files.
 *
 * @package WordPressdotorg\FreeScout\WPOrgFreeScoutImport
 */

declare( strict_types = 1 );

namespace Modules\WPOrgFreeScoutImport\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\WPOrgFreeScoutImport\Entities\Source;
use Modules\WPOrgFreeScoutImport\Jobs\ApplyMessageIds;
use Modules\WPOrgFreeScoutImport\Services\MessageIds;
use Modules\WPOrgFreeScoutImport\Services\People;

/**
 * A source is a FreeScout with the API & Webhooks module: its address, its global API key, and a name for the pages.
 */
final class SourcesController extends Controller {

	/**
	 * Why an address isn't taken.
	 *
	 * @var string
	 */
	private const BAD_URL = 'Enter the FreeScout’s https address, like https://scout.wc-us.org.';

	/**
	 * Adds a source.
	 *
	 * @param Request $request Request.
	 * @return RedirectResponse
	 */
	public function store( Request $request ): RedirectResponse {
		$source = new Source();
		if ( ! $source->configure( (string) $request->input( 'url' ), (string) $request->input( 'name' ), (string) $request->input( 'api_key' ) ) ) {
			return ImportController::back_with_error( __( self::BAD_URL ) );
		}

		$source->save();

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Added :name.', array( 'name' => $source->name ) ) );
	}

	/**
	 * Changes a source's name, address, or API key.
	 *
	 * @param Request $request Request.
	 * @param int     $id      Source ID.
	 * @return RedirectResponse
	 */
	public function update( Request $request, int $id ): RedirectResponse {
		$source = Source::query()->findOrFail( $id );
		$url    = (string) $source->url;

		if ( ! $source->configure( (string) $request->input( 'url' ), (string) $request->input( 'name' ), (string) $request->input( 'api_key' ) ) ) {
			return ImportController::back_with_error( __( self::BAD_URL ) );
		}

		$source->save();

		// Another address may be another FreeScout, with other users.
		if ( $url !== $source->url ) {
			People::forget_directory( (int) $source->id );
		}

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Saved :name.', array( 'name' => $source->name ) ) );
	}

	/**
	 * Forgets a source's API key, like after its last import.
	 *
	 * @param int $id Source ID.
	 * @return RedirectResponse
	 */
	public function forget( int $id ): RedirectResponse {
		$source          = Source::query()->findOrFail( $id );
		$source->api_key = null;
		$source->save();

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Forgot :name’s API key.', array( 'name' => $source->name ) ) );
	}

	/**
	 * Deletes a source nothing was imported from, like one added by mistake.
	 *
	 * @param int $id Source ID.
	 * @return RedirectResponse
	 */
	public function delete( int $id ): RedirectResponse {
		$source = Source::query()->findOrFail( $id );
		if ( $source->has_runs() ) {
			return ImportController::back_with_error( __( ':name can’t be deleted: what was imported from it is matched up by it. Forget its API key instead.', array( 'name' => $source->name ) ) );
		}

		People::forget_directory( (int) $source->id );
		$source->delete();

		return redirect()->route( 'wporgfreescoutimport.index' )->with( 'flash_success', __( 'Deleted :name.', array( 'name' => $source->name ) ) );
	}

	/**
	 * Keeps the Message-IDs of the file bin/export-message-ids.php wrote on a source's server, and gives them to
	 * threads imported already.
	 *
	 * @param Request $request Request.
	 * @param int     $id      Source ID.
	 * @return RedirectResponse
	 */
	public function message_ids( Request $request, int $id ): RedirectResponse {
		$source = Source::query()->findOrFail( $id );
		$file   = $request->file( 'message_ids' );
		if ( ! $file || ! $file->isValid() ) {
			return ImportController::back_with_error( __( 'Choose the file of Message-IDs.' ) );
		}

		try {
			$count = MessageIds::store( (int) $source->id, (string) $file->getRealPath() );
		} catch ( \Throwable $e ) {
			return ImportController::back_with_error( $e->getMessage() );
		}

		if ( ! $count ) {
			return ImportController::back_with_error( __( 'The file has no Message-IDs: each line needs a thread ID and a Message-ID, separated by a comma.' ) );
		}

		ApplyMessageIds::dispatch();

		return redirect()->route( 'wporgfreescoutimport.index' )->with(
			'flash_success',
			__(
				'Kept :count Message-IDs from :name. Threads imported already get theirs on the queue.',
				array(
					'count' => number_format( $count ),
					'name'  => $source->name,
				)
			)
		);
	}
}
