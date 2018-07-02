<?php
/**
 * Back-end output for the widget.
 *
 * @package HelpHub
 */

?>

<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'icon' ) ); ?>">
		<?php esc_html_e( 'Icon (dashicon name or image URL)', 'wporg-forums' ); ?>
	</label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'icon' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'icon' ) ); ?>" type="text" value="<?php echo ( isset( $instance['icon'] ) && ! empty( $instance['icon'] ) ? esc_attr( $instance['icon'] ) : '' ); ?>">
</p>

<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
		<?php esc_html_e( 'Title', 'wporg-forums' ); ?>
	</label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo ( isset( $instance['title'] ) && ! empty( $instance['title'] ) ? esc_attr( $instance['title'] ) : esc_attr__( 'Title', 'wporg-forums' ) ); ?>">
</p>

<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'description' ) ); ?>">
		<?php esc_html_e( 'Description', 'wporg-forums' ); ?>
	</label>
	<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'description' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'description' ) ); ?>" type="text" value="<?php echo ( isset( $instance['description'] ) && ! empty( $instance['description'] ) ? esc_attr( $instance['description'] ) : esc_attr__( 'Block description', 'wporg-forums' ) ); ?>">
</p>

<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'categoryid' ) ); ?>">
		<?php esc_html_e( 'Category link', 'wporg-forums' ); ?>
	</label>
	<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'categoryid' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'categoryid' ) ); ?>">
	<?php
	$categories = get_categories( array(
		'hide_empty' => 0,
	) );

	foreach ( $categories as $category ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $category->term_id ),
			selected( $instance['categoryid'], $category->term_id ),
			esc_html( $category->name )
		);
	}
	?>
	</select>
</p>

<p>
	<label for="<?php echo esc_attr( $this->get_field_id( 'menu' ) ); ?>">
		<?php esc_html_e( 'Link menu', 'wporg-forums' ); ?>
	</label>
	<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'menu' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'menu' ) ); ?>">
		<?php
		$nav_menus = wp_get_nav_menus();

		foreach ( $nav_menus as $nav_menu ) {
			printf(
				'<option value="%s" %s>%s</option>',
				esc_attr( $nav_menu->term_id ),
				selected( $instance['menu'], $nav_menu->term_id ),
				esc_html( $nav_menu->name )
			);
		}
		?>
	</select>
</p>
