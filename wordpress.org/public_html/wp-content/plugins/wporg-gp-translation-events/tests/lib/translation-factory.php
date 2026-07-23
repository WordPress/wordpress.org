<?php

namespace Wporg\TranslationEvents\Tests;

use GP_UnitTest_Factory;

class Translation_Factory {
	private GP_UnitTest_Factory $gp_factory;
	private $set;

	public function __construct( GP_UnitTest_Factory $gp_factory ) {
		$this->gp_factory = $gp_factory;
		$this->set        = $this->gp_factory->translation_set->create_with_project_and_locale();
	}

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
