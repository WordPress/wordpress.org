<?php

namespace Wporg\TranslationEvents\Routes;

use GP_Route;
use Wporg\TranslationEvents\Templates;
use Wporg\TranslationEvents\Theme_Loader;

abstract class Route extends GP_Route {
	private Theme_Loader $theme_loader;
	private bool $use_theme = false;

	public function __construct() {
		parent::__construct();
		$this->theme_loader = new Theme_Loader( 'wporg-translate-events-2024' );
	}

	public function tmpl( $template, $args = array(), $honor_api = true ) {
		$this->set_notices_and_errors();
		$this->header( 'Content-Type: text/html; charset=utf-8' );

		if ( ! $this->use_theme ) {
			$this->enqueue_legacy_styles();
			Templates::render( $template, $args );
			return;
		}

		$json = wp_json_encode( $args );
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo do_blocks( "<!-- wp:wporg-translate-events-2024/page-events-$template $json /-->" );
	}

	protected function use_theme( bool $also_in_production = false ): void {
		if ( $also_in_production ) {
			$this->use_theme = true;
		} else {
			// Only enable if new design has been explicitly enabled.
			$this->use_theme = defined( 'TRANSLATION_EVENTS_NEW_DESIGN' ) && TRANSLATION_EVENTS_NEW_DESIGN;
		}

		if ( ! $this->use_theme ) {
			return;
		}

		$this->theme_loader->load();
	}

	private function enqueue_legacy_styles(): void {
		wp_register_style(
			'translation-events-css',
			plugins_url( '/assets/css/translation-events.css', realpath( __DIR__ . '/../' ) ),
			array( 'dashicons' ),
			filemtime( __DIR__ . '/../../assets/css/translation-events.css' )
		);
		wp_enqueue_style( 'translation-events-css' );
	}
}
