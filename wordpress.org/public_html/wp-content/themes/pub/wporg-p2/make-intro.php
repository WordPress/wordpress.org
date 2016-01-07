<?php
$welcome = get_page_by_path( 'welcome' );

if ( $welcome ) {
	setup_postdata( $welcome );
?>
<div class="make-welcome">
	<?php
	the_content();
	edit_post_link( __( 'Edit', 'p2' ), '<p class="make-welcome-edit">', '</p>', $welcome->ID );
	?>
</div>
<?php
	wp_reset_postdata();
}
