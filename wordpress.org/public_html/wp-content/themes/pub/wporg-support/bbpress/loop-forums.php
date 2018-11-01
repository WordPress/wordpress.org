<?php
/**
 * Forums Loop
 *
 * @package bbPress
 * @subpackage Theme
 */

// Exit if accessed directly
defined( 'ABSPATH' ) || exit;

do_action( 'bbp_template_before_forums_loop' ); ?>

<ul id="forums-list-<?php bbp_forum_id(); ?>" class="bbp-forums">

	<li class="bbp-header">

		<ul class="forum-titles">
			<li class="bbp-forum-info"><?php esc_attr_e( 'Forum', 'wporg-forums' ); ?></li>
			<li class="bbp-forum-topic-count"><?php esc_attr_e( 'Topics', 'wporg-forums' ); ?></li>
			<li class="bbp-forum-reply-count"><?php esc_attr_e( 'Posts', 'wporg-forums' ); ?></li>
		</ul>

	</li><!-- .bbp-header -->

	<li class="bbp-body">

		<?php
		while ( bbp_forums() ) :
			bbp_the_forum();
			bbp_get_template_part( 'loop', 'single-forum' );

			bbp_list_forums(
				array(
					'before'           => '',
					'after'            => '',
					'link_before'      => '<ul class="forum"><li class="bbp-forum-info">&mdash; <span class="bbp-forum-title">',
					'link_after'       => '',
					'count_before'     => '</span></li><li class="bbp-forum-reply-count">',
					'count_after'      => '</li></ul>',
					'separator'        => '',
					'show_topic_count' => false,
					'show_reply_count' => true,
				)
			);
		endwhile;
		?>

	</li><!-- .bbp-body -->

	<li class="bbp-footer">

		<div class="tr">
			<p class="td colspan4">&nbsp;</p>
		</div><!-- .tr -->

	</li><!-- .bbp-footer -->

</ul><!-- .forums-directory -->

<?php do_action( 'bbp_template_after_forums_loop' ); ?>
