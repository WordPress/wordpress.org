<?php

if ( ! class_exists( 'WCB_Widget_Sponsors' ) ) :
class WCB_Widget_Sponsors extends WP_Widget {

	function WCB_Widget_Sponsors() {
		$widget_ops = array(
			'classname' => 'wcb_widget_sponsors',
			'description' => __( 'Your WordCamp&#8217;s Sponsors', 'wordcampbase' ),
		);
		$this->WP_Widget( 'wcb_sponsors', __('Sponsors', 'wordcampbase'), $widget_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );
		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		echo $before_widget;
		if ( $title )
			echo $before_title . $title . $after_title;

		// Fetch sponsors
		$terms = wcb_get_option('sponsor_level_order');

		foreach ( $terms as $term ):
			$sponsors = wcb_sponsor_query( array(
				'taxonomy' => $term->taxonomy,
				'term'     => $term->slug,
			) );

			if ( ! wcb_have_sponsors() )
				continue;

			// Open sponsor level ?>
			<div <?php wcb_sponsor_level_class( $term ); ?>>
			<h4 class="sponsor-level-title"><?php echo esc_html( $term->name ); ?></h4><?php

			while ( wcb_have_sponsors() ):
				wcb_the_sponsor();
				?><a class="sponsor-logo" href="<?php the_permalink(); ?>" title="<?php printf( esc_attr__( 'Permalink to %s', 'wordcampbase' ), the_title_attribute( 'echo=0' ) ); ?>" rel="bookmark"><?php
					if ( has_post_thumbnail() )
						the_post_thumbnail();
					else
						the_title();
				?></a><?php
			endwhile;

			// Close sponsor level. ?>
			</div><?php
		endforeach;

		echo $after_widget;
	}

	function form( $instance ) {
		$instance = wp_parse_args( (array) $instance, array( 'title' => '') );
		$title = $instance['title'];
?>
		<p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wordcampbase'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo esc_attr($title); ?>" /></label></p>
<?php
	}

	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;
		$new_instance = wp_parse_args((array) $new_instance, array( 'title' => ''));
		$instance['title'] = strip_tags($new_instance['title']);
		return $instance;
	}

}
endif; // class_exists