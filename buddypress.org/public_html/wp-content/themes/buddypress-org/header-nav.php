<ul id="nav" class="menu">
	<li <?php if ( is_page( 'about'    ) ) : ?>class="current"<?php endif; ?>><a href="<?php bloginfo( 'url' ); ?>/about/">About</a></li>
	<li <?php if ( is_page( 'plugins'  ) ) : ?>class="current"<?php endif; ?>><a href="<?php bloginfo( 'url' ); ?>/plugins/">Plugins</a></li>
	<li <?php if ( is_page( 'themes'   ) ) : ?>class="current"<?php endif; ?>><a href="<?php bloginfo( 'url' ); ?>/themes/">Themes</a></li>
	<li><a href="http://codex.buddypress.org/">Documentation</a></li>
	<li <?php if ( is_post_type_archive( 'post' ) || is_singular( 'post' ) || is_date() || is_tag() || is_category() || is_home() ) : ?>class="current"<?php endif; ?>><a href="<?php bloginfo( 'url' ); ?>/blog/">Blog</a></li>
	<li <?php if ( function_exists( 'is_bbpress' ) && is_bbpress() ) : ?>class="current"<?php endif; ?>><a href="<?php bloginfo( 'url' ); ?>/support/">Support</a></li>
	<li class="download<?php if ( is_page( 'download' ) ) : ?> current<?php endif; ?>"><a href="<?php bloginfo( 'url' ); ?>/download/">Download</a></li>
</ul>