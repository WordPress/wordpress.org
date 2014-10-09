<?php

$sites = array( 'core', 'community', 'polyglots', 'docs', 'support', 'themes' );
$site = trim( home_url( '', 'relative' ), '/' );
if ( ! in_array( $site, $sites ) ) {
	return;
}

$welcome = get_page_by_path( 'welcome' );

setup_postdata( $welcome );
?>
<style>
</style>
<div class="make-welcome">
<?php the_content(); ?>
</div>
<?php
wp_reset_postdata();

