<?php

function twentyten_posted_on() {
	$meta = array();
	$meta['author'] = sprintf( __( '%1$s <span class="meta-sep">by</span> %2$s', 'wordcamporg' ),
		sprintf( '<a href="%1$s" title="%2$s" rel="bookmark"><span class="entry-date">%3$s</span></a>',
			get_permalink(),
			esc_attr( get_the_time() ),
			get_the_date()
		),
		sprintf( '<span class="author vcard"><a class="url fn n" href="%1$s" title="%2$s">%3$s</a></span>',
			get_author_posts_url( get_the_author_meta( 'ID' ) ),
			sprintf( esc_attr__( 'View all posts by %s', 'wordcamporg' ), get_the_author() ),
			get_the_author()
		)
	);

	$meta['sep'] = ' <span class="meta-sep meta-sep-bull">&bull;</span> ';
	$meta['comments'] = array(
		'before'    => '<span class="comments-link">',
		'after'     => '</span>',
		'zero'      => __( 'Leave a comment', 'wordcamporg' ),
		'one'       => __( '1 Comment', 'wordcamporg' ),
		'many'      => __( '% Comments', 'wordcamporg' ),
	);

	$meta['edit'] = array(
		'title'     => __( 'Edit', 'wordcamporg' ),
		'before'    => '<span class="edit-link">',
		'after'     => '</span>',
	);

	$meta['br'] = '<br />'; // Just to have.

	$meta['order'] = array( 'author', 'sep', 'comments', 'edit' );

	$meta = apply_filters( 'wcb_entry_meta', $meta );

	if ( !is_array( $meta ) || ! isset( $meta['order'] ) )
		return;

	foreach ( $meta['order'] as $type ) {
		$content = $meta[ $type ];
		switch ( $type ) {
			case 'comments':
				echo $content['before'];
				comments_popup_link( $content['zero'], $content['one'], $content['many'] );
				echo $content['after'];
				break;
			case 'edit':
				if ( isset( $meta['sep'] ) )
					$content['before'] = $meta['sep'] . $content['before'];
				edit_post_link( $content['title'], $content['before'], $content['after'] );
				break;
			default:
				echo $content;
		}
	}
}

?>