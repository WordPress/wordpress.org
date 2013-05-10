<?php

class WCB_Post_Metabox extends WCB_Metabox {
	function __construct( $id_base='' ) {
		parent::__construct( $id_base );

		$this->add_save_action( 'save_post' );
	}

	function maybe_save( $post_id, $post ) {
		// Bail if we're autosaving
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;

		// @todo revision check

		// Cap check
		if ( !current_user_can( 'edit_post', $post_id ) )
			return;

		return true;
	}

	/**
	 * If a meta manager exists, returns the metadata.
	 * Meant to be extended.
	 *
	 * @return mixed
	 */
	function render( $post, $instance ) {
		if ( isset( $instance['meta_manager'] ) ) {
			if ( isset( $instance['meta_fields'] ) )
				return $this->render_meta_fields( $post, $instance );
			else
				return $instance['meta_manager']->get( $post->ID );
		}
	}

	function render_meta_fields( $post, $instance ) {
		$manager = $instance['meta_manager'];
		$fields  = $instance['meta_fields'];

		foreach ( $fields as $key => $field ) {
			$meta = $manager->get( $post->ID, $key );
			switch ( $field['type'] ) {
				case 'text': ?>
					<label class="description">
						<?php echo esc_html( $field['label'] ); ?>
						<input type="text" <?php $this->name( $key ); ?> value="<?php echo esc_attr( $meta ); ?>" />
					</label>
				<?php break;
			}
		}
	}

	/**
	 * If a meta manager exists, attempts to update each key
	 * by using the default metabox-generated name for each key.
	 */
	function save( $post_id, $post ) {
		$instance = $this->get_instance();
		if ( isset( $instance['meta_manager'] ) ) {
			$updates = array();
			foreach ( $instance['meta_manager']->keys as $key ) {
				$name = $this->get_name();
				if ( isset( $_POST[ $name ][ $key ] ) )
					$updates[ $key ] = $_POST[ $name ][ $key ];
			}
			$instance['meta_manager']->update( $post_id, $updates );
		}
	}
}

?>