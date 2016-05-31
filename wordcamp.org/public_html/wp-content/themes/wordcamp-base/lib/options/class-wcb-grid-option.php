<?php

class WCB_Grid_Option extends WCB_Array_Option {
	var $label;
	var $type;
	var $keys = array('visible', 'layout', 'front_only');

	function WCB_Grid_Option( $args ) {
		parent::WCB_Array_Option( $args );
		$defaults = array(
			'label' => '',
			'type' => 'sidebar'
		);
		extract( wp_parse_args( $args, $defaults ) );
		$this->label = $label;
		$this->type  = $type;
	}

	function validate_visible( $input ) {
		return $input == 'on';
	}

	function validate_front_only( $input ) {
		return $this->type != 'content' && $input == 'on';
	}

	function validate_layout( $input ) {
		$input = unserialize( $input );
		if ( ! is_array( $input ) )
			return null;

		// Match the input to a pre-existing layout.
		$verbose_input = $this->verbose_row( $input );
		foreach ( $this->get_layouts() as $set ) {
			foreach ( $set as $layout ) {
				if ( $this->verbose_row( $layout ) == $verbose_input )
					return serialize( $input );
			}
		}

		return null;
	}

	function get_layouts() {
		switch( $this->type ) {
		case 'content':
			return array(
				array( // Two columns, left column content
					array( array(12, 'content')    ),
					array( array(9,  'content'), 3 ),
					array( array(8,  'content'), 4 ),
					array( array(6,  'content'), 6 ),
				),
				array( // Two columns, right column content
					array( 3, array(9, 'content') ),
					array( 4, array(8, 'content') ),
					array( 6, array(6, 'content') ),
				),
				array( // Three columns
					array(    3, array(6, 'content'), 3    ),
					array(       array(6, 'content'), 3, 3 ),
					array( 3, 3, array(6, 'content')       ),
				),
			);
		case 'sidebar':
		default:
			return array(
				array( // Evenly spaced columns
					array( 12 ),
					array( 6, 6 ),
					array( 4, 4, 4 ),
					array( 3, 3, 3, 3 ),
				),
				array( // Two uneven columns
					array( 9, 3 ),
					array( 8, 4 ),
					array( 3, 9 ),
					array( 4, 8 ),
				),
				array( // Three columns
					array( 3, 6, 3 ),
					array( 6, 3, 3 ),
					array( 3, 3, 6 ),
				),
			);
		}
	}

	function render_visibility() {
		$visibility_id = esc_attr( "grid-visibility-$this->key" );
		$grid_row_id = esc_attr( "grid-row-$this->key" );
		?>
		<label class="description visibility-description" for="<?php echo $visibility_id; ?>">
			<input type="hidden" class="grid-row-id" value="<?php echo $grid_row_id; ?>" />
			<input type="checkbox" id="<?php echo $visibility_id; ?>" <?php
				$this->name('visible');
				checked( $this->get_option('visible') );
				?> />
			<?php echo esc_html( $this->label ); ?>
		</label>
		<?php
	}

	function render_layout() {
		$layout = $this->get_option('layout');
		?>
		<div id="<?php echo esc_attr("grid-row-$this->key"); ?>" class="grid-row-layout clearfix <?php echo $this->get_option('visible') ? 'visible' : ''; ?>">
			<div class="description row-name"><?php echo esc_html( $this->label ); ?></div>
			<input class="signature" type="hidden" <?php $this->name('layout'); ?> value="<?php echo esc_attr( serialize( $layout ) ); ?>"/>
			<?php $this->render_row( $layout ); ?>
			<div class="edit"><a href="#"><?php echo esc_html_e( 'Edit' , 'wordcamporg'); ?></a></div>
			<?php if ( $this->type != 'content' ):
				$front_page_id = esc_attr( "front-page-only-$this->key" );
				?>
				<label class="description front-page" for="<?php echo $front_page_id; ?>">
					<input type="checkbox" id="<?php echo $front_page_id; ?>" <?php
						$this->name('front_only');
						checked( $this->get_option('front_only') );
						?> />
					<?php esc_html_e( 'Front page only', 'wordcamporg' ); ?>
				</label>
			<?php endif; ?>
			<div class="picker">
				<div class="directions">
					<?php esc_html_e( 'Choose a new row layout.', 'wordcamporg' ); ?>
					<a href="#" class="cancel"><?php esc_html_e( 'Cancel' , 'wordcamporg'); ?></a>
				</div>
				<?php
				$current_layout = $this->verbose_row( $this->get_option('layout') );
				foreach ( $this->get_layouts() as $rows ): ?>
					<div class="row-config-column clearfix">
					<?php
					foreach ( $rows as $row ):
						$active = $this->verbose_row( $row ) == $current_layout; ?>
						<a href="#" class="grid-row-selector <?php echo $active ? 'active' : ''; ?>">
							<input class="grid-row-signature" type="hidden" value="<?php echo esc_attr( serialize( $row ) ); ?>"/>
							<?php $this->render_row( $row ); ?>
						</a>
					<?php endforeach; ?>
					</div>
				<?php endforeach; ?>
			</div>
		</div>
		<?php
	}

	// Rows can use integers as a shorthand for array( int, 'sidebar' )
	// For comparison purposes, it's nice to have a standard.
	function verbose_row( $row ) {
		foreach ( $row as $i => $v ) {
			if ( is_integer( $v ) )
				$row[ $i ] = array( $v, 'sidebar' );
		}
		return $row;
	}

	function render_row( $row ) {
		echo '<div class="grid-row container_12">';

		foreach ( $row as $width ) {
			// $width is either an integer, and of $type 'sidebar'...
			$type = 'sidebar';

			// ...or formatted array( $width, $type ).
			if ( is_array( $width ) )
				list( $width, $type ) = $width;

			$class = "cell grid_$width $type";

			echo '<div class="' . esc_attr( $class ) . '"></div>';
		}

		echo '</div>';
	}
}

?>