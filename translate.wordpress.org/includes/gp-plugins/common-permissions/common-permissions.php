<?php

class GP_Common_Permissions_Plugin extends GP_Plugin {
	var $id = 'common-permissions';
	
	var $permissions_map = array( 17 => 1 );
	
	function __construct() {
		parent::__construct();
		$this->add_filter( 'can_user', array( 'args' => 2 ) );
	}
	
	function can_user( $verdict, $args ) {		
		if ( !( $verdict === false && $args['action'] == 'approve' && $args['object_type'] == GP::$validator_permission->object_type
				&& $args['object_id'] && $args['user'] ) ) {
			return $verdict;
		}
		list( $project_id, $locale_slug, $set_slug ) = GP::$validator_permission->project_id_locale_slug_set_slug( $args['object_id'] );
		if ( !gp_array_get( $this->permissions_map, $project_id ) ) {
			return $verdict;
		}
		return $args['user']->can( 'approve', $args['object_type'],
			GP::$validator_permission->object_id( gp_array_get( $this->permissions_map, $project_id ), $locale_slug, $set_slug ) );
	}
}

GP::$plugins->common_permissions = new GP_Common_Permissions_Plugin;
