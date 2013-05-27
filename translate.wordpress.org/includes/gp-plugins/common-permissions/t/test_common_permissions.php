<?php
require_once dirname( __FILE__ ) . '/../../../t/init.php';

class GP_Test_Common_Permissions extends GP_UnitTestCase {

	function test_common_permissions_one_level_only() {
		GP::$plugins->common_permissions->permissions_map = array(
			$this->fixtures->projects->generic->id => $this->fixtures->projects->generic2->id,
		);
		$this->assertFalse( $this->fixtures->users->validator_for_generic->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic2->id ) );
		$this->assertTrue( $this->fixtures->users->validator_for_generic2->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic->id ) );		
		$this->assertTrue( $this->fixtures->users->validator_for_generic->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic->id ) );
		$this->assertTrue( $this->fixtures->users->validator_for_generic2->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic2->id ) );
	}	
	
	function test_common_permissions_empty() {
	
		GP::$plugins->common_permissions->permissions_map = array();
		$this->assertFalse( $this->fixtures->users->validator_for_generic->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic2->id ) );
		$this->assertFalse( $this->fixtures->users->validator_for_generic2->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic->id ) );
		$this->assertTrue( $this->fixtures->users->validator_for_generic->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic->id ) );
		$this->assertTrue( $this->fixtures->users->validator_for_generic2->can( 'approve', 'translation-set', $this->fixtures->sets->default_in_generic2->id ) );
	}	
}
