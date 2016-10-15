<div id="bbpress-forums">

	<?php do_action( 'bbp_template_before_forums_index' ); ?>

	<?php if ( bbp_has_forums() ) : ?>
		
		<?php if ( is_front_page() ) : ?>
			<?php bbp_get_template_part( 'loop',     'forums-homepage'    ); ?>
		<?php else : ?>
			<?php bbp_get_template_part( 'loop',     'forums'    ); ?>
		<?php endif; ?>

	<?php else : ?>

		<?php bbp_get_template_part( 'feedback', 'no-forums' ); ?>

	<?php endif; ?>

	<?php do_action( 'bbp_template_after_forums_index' ); ?>

</div>
