<?php 

/**
 * Single Reply Content Part
 *
 * @package bbPress
 * @subpackage Theme
 */

?>
	
<?php bbp_breadcrumb(); ?>

<div id="bbpress-forums">

	<?php do_action( 'bbp_template_before_single_reply' ); ?>

	<?php if ( post_password_required() ) : ?>

		<?php bbp_get_template_part( 'form', 'protected' ); ?>

	<?php else : ?>

		<ul id="topic-<?php bbp_topic_id(); ?>-replies" class="forums bbp-replies">

			<li class="bbp-body">

				<?php bbp_get_template_part( 'loop', 'single-reply' ); ?>

			</li><!-- .bbp-body -->

		</ul><!-- #topic-<?php bbp_topic_id(); ?>-replies -->

	<?php endif; ?>

	<?php do_action( 'bbp_template_after_single_reply' ); ?>

</div>