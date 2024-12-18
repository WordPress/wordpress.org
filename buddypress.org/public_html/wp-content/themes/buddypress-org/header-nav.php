<?php

// "current" CSS class vars
$_is_about    = is_page( 'about' );
$_is_bbpress  = ( ( function_exists( 'is_bbpress' ) && is_bbpress() ) && ! is_front_page() );
$_is_codex    = false;
$_is_news     = ( is_home() || is_singular( 'post' ) || ( is_archive() && ! is_post_type_archive() ) );
$_is_download = is_page( 'download' );

?>
<div id="nav">
	<a href="#" id="bb-menu-icon"></a>
	<ul id="bb-nav" class="menu">
		<li class="menu-item<?php if ( true === $_is_about ) : ?> current <?php endif; ?>">
			<a href="https://buddypress.org/about/">
				<?php _e( 'About', 'bborg' ); ?>
			</a>
		</li>
		<li class="menu-item<?php if ( true === $_is_news ) : ?> current <?php endif; ?>">
			<a href="https://buddypress.org/blog/">
				<?php _e( 'News', 'bborg' ); ?>
			</a>
		</li>
		<li class="menu-item<?php if ( true === $_is_codex ) : ?> current <?php endif; ?>">
			<a href="https://codex.buddypress.org/">
				<?php _e( 'Codex', 'bborg' ); ?>
			</a>
		</li>
		<li class="menu-item">
			<a href="https://developer.buddypress.org">
				<?php _e( 'Develop', 'bborg' ); ?>
			</a>
		</li>
		<li class="menu-item">
			<a href="https://buddypress.trac.wordpress.org">
				<?php _e( 'Make', 'bborg' ); ?>
			</a>
		</li>
		<li class="menu-item<?php if ( true === $_is_bbpress ) : ?> current <?php endif; ?>">
			<a href="https://buddypress.org/support/">
				<?php _e( 'Forums', 'bborg' ); ?>
			</a>
		</li>
		<li class="menu-item <?php if ( true === $_is_download ) : ?> current <?php endif; ?>">
			<a href="https://buddypress.org/download/">
				<?php _e( 'Download', 'bborg' ); ?>
			</a>
		</li>
	</ul>
</div>