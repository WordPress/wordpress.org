<?php

namespace WordPressdotorg\GlotPress\Customizations\CLI;

use GP;
use GP_Locales;
use WP_CLI;
use WP_CLI_Command;

class Init_Locale extends WP_CLI_Command {

	/**
	 * Initialize a new locale.
	 *
	 * ## OPTIONS
	 *
	 * <locale>
	 * : WordPress locale of a new locale.
	 *
	 * [--name]
	 * : Name of the locale.
	 *
	 * [--slug]
	 * : Slug of the locale.
	 */
	public function __invoke( $args, $assoc_args ) {
		global $wpdb;

		$gp_locale = GP_Locales::by_field( 'wp_locale', $args[0] );
		if ( ! $gp_locale ) {
			WP_CLI::error( sprintf( "There is no locale for '%s'.", $args[0] ) );
		}

		$name = empty( $assoc_args['name'] ) ? $gp_locale->english_name : $assoc_args['name'];
		$slug = empty( $assoc_args['slug'] ) ? 'default' : $assoc_args['slug'];

		WP_CLI::confirm(
			sprintf(
				"Data:\nWP Locale: %s\nName: %s\nSlug: %s\nDo you want to create translation sets for %s?",
				$gp_locale->wp_locale,
				$name,
				$slug,
				$gp_locale->wp_locale
			)
		);

		// WordPress + Meta
		$projects = array(
			'wp/dev',
			'wp/dev/admin',
			'wp/dev/admin/network',
			'wp/dev/twentyten',
			'wp/dev/twentyeleven',
			'wp/dev/twentytwelve',
			'wp/dev/twentythirteen',
			'wp/dev/twentyfourteen',
			'wp/dev/twentyfifteen',
		);

		if ( 'default' === $slug ) {
			$projects = array_merge( $projects, array(
				'meta/rosetta',
				'meta/themes',
				'meta/plugins',
				'meta/plugins-v3',
				'meta/forums',
				'bbpress/1.1.x',
			) );
		}

		if ( 0 !== strpos( 'en_', $gp_locale->wp_locale ) ) {
			$projects[] = 'wp/dev/cc';
		}

		// Themes
		$theme_projects = $wpdb->get_col( "SELECT path FROM {$wpdb->gp_projects} WHERE `parent_project_id` = 523" );
		$projects = array_merge( $projects, $theme_projects );

		// Plugins
		$_plugin_projects = $wpdb->get_col( "SELECT path FROM {$wpdb->gp_projects} WHERE `parent_project_id` = 17" );
		$plugin_projects = array();
		foreach ( $_plugin_projects as $plugin_project ) {
			$plugin_projects[] = $plugin_project . '/dev';
			$plugin_projects[] = $plugin_project . '/dev-readme';
			$plugin_projects[] = $plugin_project . '/stable';
			$plugin_projects[] = $plugin_project . '/stable-readme';
		}
		unset( $_plugin_projects );
		$projects = array_merge( $projects, $plugin_projects );

		foreach ( $projects as $project_path ) {
			$project = GP::$project->by_path( $project_path );
			if ( ! $project ) {
				continue;
			}

			$new_set = array(
				'name'       => $name,
				'slug'       => $slug,
				'project_id' => $project->id,
				'locale'     => $gp_locale->slug,
			);
			$set = GP::$translation_set->create( $new_set );
			if ( $set ) {
				WP_CLI::line(
					sprintf(
						'%s added to %s.',
						$gp_locale->wp_locale,
						$project_path
					)
				);
			} else {
				WP_CLI::warning(
					sprintf(
						'%s couldn\'t be added to %s.',
						$gp_locale->wp_locale,
						$project_path
					)
				);
			}
		}

		WP_CLI::line( 'Done!' );
	}
}
