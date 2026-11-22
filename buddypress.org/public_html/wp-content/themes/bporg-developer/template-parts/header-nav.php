<?php
/**
 * BuddyPress.org navigation for Developer Resources.
 *
 * @package bporg-developer
 */
?>
<nav id="nav" aria-label="<?php esc_attr_e( 'BuddyPress.org', 'bporg-developer' ); ?>">
	<button type="button" id="bb-menu-icon" aria-controls="bb-nav" aria-expanded="false"><span class="screen-reader-text"><?php esc_html_e( 'Menu', 'bporg-developer' ); ?></span></button>
	<ul id="bb-nav" class="menu">
		<li><a href="https://buddypress.org/about/"><?php esc_html_e( 'About', 'bporg-developer' ); ?></a></li>
		<li><a href="https://buddypress.org/blog/"><?php esc_html_e( 'News', 'bporg-developer' ); ?></a></li>
		<li><a href="https://codex.buddypress.org/"><?php esc_html_e( 'Codex', 'bporg-developer' ); ?></a></li>
		<li class="current"><a href="https://developer.buddypress.org/" aria-current="page"><?php esc_html_e( 'Develop', 'bporg-developer' ); ?></a></li>
		<li><a href="https://buddypress.trac.wordpress.org/"><?php esc_html_e( 'Make', 'bporg-developer' ); ?></a></li>
		<li><a href="https://buddypress.org/support/"><?php esc_html_e( 'Forums', 'bporg-developer' ); ?></a></li>
		<li><a href="https://buddypress.org/download/"><?php esc_html_e( 'Download', 'bporg-developer' ); ?></a></li>
	</ul>
</nav>
