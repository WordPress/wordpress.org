<?php
/**
 * Factory for creating GlotPress translations in tests.
 *
 * @package wporg-gp-translation-events
 */

namespace Wporg\TranslationEvents\Tests;

use GP_UnitTest_Factory;

/**
 * Creates originals and translations in a shared translation set for tests.
 */
class Translation_Factory {
	/**
	 * The GlotPress factory used to create originals and translations.
	 *
	 * @var GP_UnitTest_Factory
	 */
	private GP_UnitTest_Factory $gp_factory;

	/**
	 * The translation set shared by every translation this factory creates.
	 *
	 * @var object
	 */
	private $set;

	/**
	 * Creates the shared translation set with a project and locale.
	 *
	 * @param GP_UnitTest_Factory $gp_factory The GlotPress factory to build with.
	 */
	public function __construct( GP_UnitTest_Factory $gp_factory ) {
		$this->gp_factory = $gp_factory;
		$this->set        = $this->gp_factory->translation_set->create_with_project_and_locale();
	}

	/**
	 * Creates an original and a waiting translation for the given user.
	 *
	 * @param int                    $user_id    The user the translation is attributed to.
	 * @param DateTimeImmutable|null $date_added Optional creation time for the original and translation.
	 *
	 * @return object The created translation.
	 */
	public function create( int $user_id, $date_added = null ) {
		$original = $this->gp_factory->original->create(
			array(
				'project_id' => $this->set->project->id,
				'status'     => '+active',
				'singular'   => 'foo',
			)
		);
		if ( $date_added ) {
			$original->update( array( 'date_added' => $date_added->format( 'Y-m-d H:i:s' ) ) );
		}

		$translation = $this->gp_factory->translation->create(
			array(
				'user_id'            => $user_id,
				'translation_set_id' => $this->set->id,
				'original_id'        => $original->id,
				'status'             => 'waiting',
			)
		);
		if ( $date_added ) {
			$translation->update( array( 'date_added' => $date_added->format( 'Y-m-d H:i:s' ) ) );
		}
		return $translation;
	}
}
