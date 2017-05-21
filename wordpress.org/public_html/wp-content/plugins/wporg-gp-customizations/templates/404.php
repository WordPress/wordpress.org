<?php
gp_title( __( 'Not Found &lt; GlotPress', 'glotpress' ) );

gp_tmpl_header();
?>

<h2>Page not found</h2>

<p>The page you were looking for could not be found. I’m sorry, it’s not your fault&hellip; probably. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Return to the homepage</a></p>

<?php
gp_tmpl_footer();
