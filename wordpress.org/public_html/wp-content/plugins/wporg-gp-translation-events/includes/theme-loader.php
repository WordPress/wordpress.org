<?php

namespace Wporg\TranslationEvents;

class Theme_Loader {
	private string $theme;

	public function __construct( string $theme ) {
		$this->theme = $theme;
	}

	public function load(): void {
		if ( str_ends_with( get_stylesheet_directory(), $this->theme ) ) {
			// Our theme is already the active theme, there's nothing to do here.
			return;
		}

		if ( class_exists( 'WP_Theme_JSON_Resolver_Gutenberg' ) ) {
			// We must clean cached theme.json data to force a new parse of theme.json of the child and parent themes.
			\WP_Theme_JSON_Resolver_Gutenberg::clean_cached_data();
		}

		add_filter(
			'template',
			function (): string {
				// TODO: Calculate automatically.
				return 'wporg-parent-2021';
			}
		);
		add_filter(
			'stylesheet',
			function (): string {
				return $this->theme;
			}
		);

		global $wp_stylesheet_path, $wp_template_path;
		$wp_stylesheet_path = get_stylesheet_directory();
		$wp_template_path   = get_template_directory();

		foreach ( wp_get_active_and_valid_themes() as $theme ) {
			if ( file_exists( $theme . '/functions.php' ) ) {
				include $theme . '/functions.php';
			}
		}

		do_action( 'wporg_translate_events_theme_init' );

		$this->dequeue_unwanted_assets();
	}

	private function dequeue_unwanted_assets(): void {
		// Dequeue styles and scripts from glotpress and from the pub/wporg theme.
		// The WordPress.org theme enqueues styles in wp_enqueue_scripts, so we need to dequeue in both styles and scripts.
		foreach ( array( 'wp_enqueue_styles', 'wp_enqueue_scripts' ) as $action ) {
			add_action(
				$action,
				function (): void {
					wp_styles()->remove(
						array(
							'wporg-style',
						)
					);
					wp_scripts()->remove(
						array(
							'wporg-plugins-skip-link-focus-fix',
						)
					);
				},
				9999 // Run as late as possible to make sure the styles/scripts are not enqueued after we dequeue them.
			);
		}
	}
}
