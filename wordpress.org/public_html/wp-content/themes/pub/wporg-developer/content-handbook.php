<?php
/**
 * Displays the content and meta information for a post object.
 *
 * @package wporg
 */
?>

<h1><?php the_title(); ?></h1>

<?php
/*
 * Content
 */
?>

<?php the_content( __( '(More ...)' , 'wporg' ) ); ?>

<?php edit_post_link( __( 'Edit', 'wporg' ), '<span class="edit-link">', '</span>' ); ?>

<div class="bottom-of-entry">&nbsp;</div>
