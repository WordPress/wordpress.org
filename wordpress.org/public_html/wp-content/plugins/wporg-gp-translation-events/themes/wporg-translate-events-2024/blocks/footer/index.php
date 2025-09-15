<?php namespace Wporg\TranslationEvents\Theme_2024;

register_block_type(
	'wporg-translate-events-2024/footer',
	array(
		'render_callback' => function () {
			ob_start();
			require __DIR__ . '/render.php';
			return ob_get_clean();
		},
	)
);
