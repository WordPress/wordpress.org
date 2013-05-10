<?php
/**
 * bbPress wrapper template.
 *
 * @package bbPress
 */

get_header( 'bbpress' ); ?>

<div class="sleeve_main">

	<div id="main">
		<h2><?php the_title(); ?></h2>

		<ul id="postlist">
			<li>
				<?php the_content(); ?>
			</li>
		</ul>
		
	</div> <!-- main -->

</div> <!-- sleeve -->

<?php get_footer( 'bbpress' ); ?>