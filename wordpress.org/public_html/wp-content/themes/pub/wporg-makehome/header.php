<?php

\WordPressdotorg\skip_to( '#headline' );

echo do_blocks( '<!-- wp:wporg/global-header /-->' );

?>

<div id="headline">
		<div class="wrapper">
				<h2><a href="<?php echo esc_url( home_url() ); ?>"><?php bloginfo( 'name' ); ?></a></h2>
		</div>
</div>
