<?php

/**
 * Posts Loop
 *
 * @todo Front-side action buttons
 * @todo Better excerpt styling
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<ul class="bbp-topics">
	<li class="bbp-header">
		<ul class="forum-titles">
			<li class="bbp-topic-title"><?php esc_html_e( 'Title', 'wporg-forums' ); ?></li>
			<li class="bbp-post-excerpt"><?php esc_html_e( 'Excerpt', 'wporg-forums' ); ?></li>
		</ul>
	</li>

	<li class="bbp-body">

		<?php while ( bbp_topics() ) : bbp_the_topic(); ?>

		<ul>
			<li class="bbp-topic-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a><br />

			<span class="bbp-topic-started-by"><?php printf( __( 'Started by: %1$s', 'wporg-forums' ), bbp_get_topic_author_link( array( 'size' => '14' ) ) ); ?></span>

			<span class="bbp-topic-started-in"><?php printf( __( 'in: <a href="%1$s">%2$s</a>', 'wporg-forums' ), bbp_get_forum_permalink( bbp_get_topic_forum_id() ), bbp_get_forum_title( bbp_get_topic_forum_id() ) ); ?></span></li>

			<li class="bbp-post-excerpt"><?php the_excerpt(); ?></li>
		</ul>

		<?php endwhile; ?>

	</li>

	<li class="bbp-footer">
		<div class="tr">
			<p>
				<span class="td colspan2">&nbsp;</span>
			</p>
		</div><!-- .tr -->
	</li>
</ul>
