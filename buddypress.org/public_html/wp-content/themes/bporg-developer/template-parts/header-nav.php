<?php
/**
 * The navigation part of our theme's Header.
 *
 * @package bporg-developer
 * @subpackage TemplateParts
 * @since 1.0.0
 */
?>
<div id="nav">
	<a href="#" id="bb-menu-icon"></a>

    <?php wp_nav_menu( array(
        'container'      => '',
        'menu_class'     => 'menu',
        'menu_id'        => 'bb-nav',
        'theme_location' => 'header-nav-menu',
    ) ); ?>

</div>
