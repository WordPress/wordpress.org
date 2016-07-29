<?php
namespace WordPressdotorg\Make\Breathe;

function styles() {
	wp_dequeue_style('breathe-style');
	wp_enqueue_style( 'p2-breathe', get_template_directory_uri() . '/style.css' );

	// Cacheing hack
	wp_enqueue_style( 'wporg-breathe', get_stylesheet_uri(), array( 'p2-breathe' ), '20160729' );
}
add_action( 'wp_enqueue_scripts', __NAMESPACE__ . '\styles', 11 );

function inline_scripts() {
	?>
	<script type="text/javascript">
		var el = document.getElementById( 'make-welcome-hide' );
		if ( el ) {
			el.addEventListener( 'click', function( e ) {
				document.cookie = el.dataset.cookie + '=' + el.dataset.hash + '; expires=Fri, 31 Dec 9999 23:59:59 GMT';
				jQuery( '.make-welcome' ).slideUp();
			} );
		}
	</script>
	<?php
}
add_action( 'wp_footer', __NAMESPACE__ . '\inline_scripts' );
