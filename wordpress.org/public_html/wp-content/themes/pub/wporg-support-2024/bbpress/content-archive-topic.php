<?php if ( bbp_is_forum_archive() || bbp_is_topic_archive() || bbp_is_search() ) : ?>

	<div class="bbp-search-form">

		<?php bbp_get_template_part( 'form', 'search' ); ?>

	</div>

<?php endif; ?>

<?php if ( bbp_is_topic_tag() ) : ?>

	<header id="topic-tag" class="page-header bbp-topic-tag">
		<h1 class="page-title"><?php printf( esc_html__( 'Topic Tag: %s', 'wporg-forums' ), '<span>' . bbp_get_topic_tag_name() . '</span>' ); ?></h1>
		<?php bbp_topic_tag_description(); ?>
	</header>

<?php endif; ?>

<div id="bbpress-forums">

	<?php do_action( 'bbp_template_before_topics_index' ); ?>

	<?php if ( bbp_has_topics() ) : ?>

		<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

		<?php bbp_get_template_part( 'loop',       'topics'    ); ?>

		<?php bbp_get_template_part( 'pagination', 'topics'    ); ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'feedback',   'no-topics' ); ?>

	<?php endif; ?>

	<?php do_action( 'bbp_template_after_topics_index' ); ?>

</div>
