<?php
/**
 * Template Name: Full Width (No Sidebar)
 *
 * A custom page template without sidebar.
 */

$structure = wcb_get('structure');
$structure->full_width_content();

include WCB_DIR . '/page.php';

?>