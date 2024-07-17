<?php

namespace Wporg\TranslationEvents;

/**
 * Legacy (non-theme) templates.
 */
class Templates {
	private const LEGACY_TEMPLATE_DIRECTORY = __DIR__ . '/../templates/';

	public static function header( array $data = array() ) {
		self::part( 'header', $data );
	}

	public static function footer( array $data = array() ) {
		self::part( 'footer', $data );
	}

	public static function part( string $template, array $data = array() ) {
		self::render( "parts/$template", $data );
	}

	public static function render( string $template, array $data = array() ) {
		gp_tmpl_load( $template, $data, self::LEGACY_TEMPLATE_DIRECTORY );
	}
}
