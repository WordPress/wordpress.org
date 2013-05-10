<?php

class WCB_Sidebar_Row extends WCB_Container {
	function WCB_Sidebar_Row( $args ) {
		$defaults = array(
			'id'    => '',
			'class' => '',
			'name'  => '',
			'grid'  => array(),
			'width' => 12
		);
		extract( wp_parse_args( $args, $defaults ) );

		if ( empty( $id ) || empty( $name ) || empty( $grid ) )
			return;

		parent::WCB_Container( array(
			'id' => $id,
			'class' => "grid_$width sidebar-row $class",
		) );

		$sidebar_index = 1;
		foreach ( $grid as $index => $cell ) {

			if ( is_numeric( $cell ) ) {
				$cols = $cell;
				$type = 'sidebar';
			} else {
				list( $cols, $type ) = $cell;
			}

			$class = "grid_$cols sidebar-cell $id";

			if ( $index == 0 )
				$class .= " alpha";
			if ( $index == count( $grid ) - 1 )
				$class .= " omega";

			switch ( $type ) {
				case 'sidebar':
					$sidebar_name = count( $grid ) == 1 ? $name : "$name $sidebar_index";
					$this->add( new WCB_Sidebar( array(
						'id'    => "$id-$sidebar_index",
						'name'  => $sidebar_name,
						'class' => $class
					) ) );
					$sidebar_index++;
					break;
				case 'content':
					$this->add( new WCB_Content( array(
						'class' => $class
					) ) );
					break;
			}
		}
	}
}

?>