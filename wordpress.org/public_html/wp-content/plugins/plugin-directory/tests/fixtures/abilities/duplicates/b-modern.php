<?php
// Newer code path: the same ability with full details.
wp_register_ability( 'dupe/export', array(
	'label'       => 'Export',
	'description' => 'Exports content.',
	'category'    => 'tools',
) );
