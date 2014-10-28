<?php
$welcome = get_page_by_path( 'welcome' );

if ( $welcome ) {
	setup_postdata( $welcome );
?>
<style>
</style>
<div class="make-welcome">
<?php the_content(); ?>
</div>
<?php
	wp_reset_postdata();
}


