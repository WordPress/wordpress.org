<?php

/**
 * Single Topic Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>

<header class="page-header">
	<h1 class="page-title"><?php printf( __( '%s Forum', 'wporg-forums' ), bbp_get_topic_title() ); ?></h1>
	<p><?php bbp_forum_content(); ?></p>
</header>

<div id="bbpress-forums">

	<?php bbp_breadcrumb(); ?>

	<?php if ( post_password_required() ) : ?>

		<?php bbp_get_template_part( 'form', 'protected' ); ?>

	<?php else : ?>

		<?php if ( bbp_get_forum_subforum_count() && bbp_has_forums() ) : ?>

			<?php bbp_get_template_part( 'loop', 'forums' ); ?>

		<?php endif; ?>

		<?php if ( !bbp_is_forum_category() && bbp_has_topics() ) : ?>

			<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

			<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

			<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

			<?php if ( ! bb_base_topic_search_query( false ) ) bbp_get_template_part( 'form',       'topic'     ); ?>

		<?php elseif( !bbp_is_forum_category() ) : ?>

			<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

			<?php if ( ! bb_base_topic_search_query( false ) ) bbp_get_template_part( 'form',       'topic'     ); ?>

		<?php endif; ?>

	<?php endif; ?>

</div>
