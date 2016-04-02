<?php

namespace WordPressdotorg\GlotPress\Plugin_Directory\CLI;

use GP;
use WP_CLI;
use WP_CLI_Command;

class Set_Plugin_Project extends WP_CLI_Command {

	/**
	 * Holds the path of the master project.
	 *
	 * @var string
	 */
	private $master_project_path = 'wp-plugins';

	/**
	 * Holds the path of the projects to copy translation sets from.
	 *
	 * @var string
	 */
	private $project_path_for_sets = 'wp/dev';

	/**
	 * Add/update a plugin project.
	 *
	 * ## OPTIONS
	 *
	 * <slug>
	 * : Slug of a plugin
	 *
	 * <type>
	 * : Process type. Accepted values: dev, dev-readme, stable, stable-readme.
	 *
	 */
	public function __invoke( $args, $assoc_args ) {
		if ( ! preg_match( '/^[^\/]+$/', $args[0] ) ) {
			WP_CLI::line( sprintf( "The plugin slug '%s' contains invalid characters.", $args[0] ) );
			exit( 1 );
		}

		$plugin_slug         = $args[0];
		$process_type        = $args[1];
		$plugin_project_path = "{$this->master_project_path}/{$plugin_slug}";

		// Get data for master parent project.
		$master_project = GP::$project->by_path( $this->master_project_path );
		if ( ! $master_project ) {
			WP_CLI::line( sprintf( "The master project '%s' couldn't be found.", $this->master_project_path ) );
			exit( 2 );
		}

		// Get plugin details like title and description.
		$plugin_details = $this->get_plugin_details( $plugin_slug );
		if ( ! $plugin_details ) {
			WP_CLI::line( "The plugin API couldn't be reached." );
			exit( 3 );
		}

		// Get or create the plugin GP project.
		$project_args = array(
			'name'              => $plugin_details->name,
			'slug'              => $plugin_slug,
			'parent_project_id' => $master_project->id,
			'description'       => $plugin_details->short_description . "<br><br><a href='https://wordpress.org/plugins/{$plugin_slug}'>WordPress.org Plugin Page</a>",
			'active'            => 1,
		);

		$plugin_project = GP::$project->by_path( $plugin_project_path );

		// Is this a new or an existing project?
		$new_project_flag = true;

		if ( ! $plugin_project ) {
			WP_CLI::line( "Creating project for {$plugin_project_path}." );

			$plugin_project = GP::$project->create_and_select( $project_args );
			if ( ! $plugin_project ) {
				WP_CLI::line( "Couldn't create project for {$plugin_project_path}." );
				exit( 5 );
			}
		} else {
			WP_CLI::line( "Updating project for {$plugin_project_path}." );

			// This is an existing project.
			$new_project_flag = false;

			// Update the project details.
			$plugin_project->update( $project_args );
		}

		// The current stable SVN tag.
		$stable_tag = empty( $plugin_details->stable_tag ) ? 'trunk' : $plugin_details->stable_tag;

		// Deal with the always-existing dev|dev-readme branch.
		$plugin_dev_branch_project = $this->handle_plugin_project_branches(
			$plugin_project_path,
			$plugin_slug,
			$plugin_details->name,
			$plugin_project->id,
			$process_type,
			$stable_tag,
			'dev'
		);

		// Deal with the stable|stable-readme branch, if needed.
		$plugin_stable_branch_project = false;
		if ( 'trunk' !== $stable_tag ) {
			$plugin_stable_branch_project = $this->handle_plugin_project_branches(
				$plugin_project_path,
				$plugin_slug,
				$plugin_details->name,
				$plugin_project->id,
				$process_type,
				$stable_tag,
				'stable'
			);
		}

		// Deal with initial translation sets.
		$source_project = GP::$project->by_path( $this->project_path_for_sets );
		if ( $source_project && $plugin_dev_branch_project ) {
			$translation_sets = (array) GP::$translation_set->by_project_id( $source_project->id );

			if ( $translation_sets ) {
				$this->add_translation_sets_to_branches( $translation_sets, $plugin_dev_branch_project );

				if ( $plugin_stable_branch_project ) {
					$this->add_translation_sets_to_branches( $translation_sets, $plugin_stable_branch_project );
				}
			}
		}

		WP_CLI::line( ( $new_project_flag ? 'Created' : 'Updated' ) . " https://translate.wordpress.org/projects/{$plugin_project_path}" );
		exit( 0 );
	}

	/**
	 * Creates/updates sub-projects for plugins.
	 *
	 * @param string $plugin_project_path Path to the plugin project.
	 * @param string $plugin_slug         Slug of the plugin.
	 * @param string $plugin_name         Name of the plugin.
	 * @param int    $parent_id           ID of the main project.
	 * @param string $process_type        Type of the project, 'code' or 'readme'.
	 * @param string $stable_tag          The stable SVN tag of the plugin.
	 * @param string $branch_slug         Slug of the project, 'dev' or 'stable'.
	 * @return GP_Project|null A GP_Project instance on success, null on failure.
	 */
	private function handle_plugin_project_branches( $plugin_project_path, $plugin_slug, $plugin_name, $parent_id, $process_type, $stable_tag, $branch_slug ) {
		if ( ! in_array( $branch_slug, array( 'dev', 'stable' ) ) ) {
			$branch_slug = 'dev';
		}

		$is_stable = 'stable' === $branch_slug;

		if ( 'code' !== $process_type ) {
			$process_type = 'readme';
		}

		if ( 'code' === $process_type ) {
			$branch_project_path = "{$plugin_project_path}/{$branch_slug}";
			$source_url_template = sprintf(
				'https://plugins.trac.wordpress.org/browser/%s/%s/%s',
				$plugin_slug,
				$is_stable ? "tags/{$stable_tag}" : 'trunk',
				'%file%#L%line%'
			);

			$project_args = array(
				'name'                => $is_stable ? 'Stable (latest release)' : 'Development (trunk)',
				'slug'                => $branch_slug,
				'parent_project_id'   => $parent_id,
				'description'         => ( $is_stable ? 'Stable' : 'Development' ) . " version of the {$plugin_name} plugin.",
				'source_url_template' => $source_url_template,
				'active'              => 1,
			);

			$branch_project = GP::$project->by_path( $branch_project_path );
			if ( ! $branch_project ) {
				$branch_project = GP::$project->create_and_select( $project_args );
				if ( ! $branch_project ) {
					WP_CLI::line( "Sorry, but couldn't create nonexistent https://translate.wordpress.org/projects/{$branch_project_path}." );
				} else {
					WP_CLI::line( "Created https://translate.wordpress.org/projects/{$branch_project_path}." );
				}
			} else {
				$updated = $branch_project->update( $project_args );
				if ( ! $updated ) {
					WP_CLI::line( "Sorry, but couldn't update https://translate.wordpress.org/projects/{$branch_project_path}." );
				} else {
					WP_CLI::line( "Updated https://translate.wordpress.org/projects/{$branch_project_path}." );
				}
			}
		} else {
			$branch_project_path = "{$plugin_project_path}/{$branch_slug}-readme";

			$project_args = array(
				'name'              => $is_stable ? 'Stable Readme (latest release)' : 'Development Readme (trunk)',
				'slug'              => "{$branch_slug}-readme",
				'parent_project_id' => $parent_id,
				'description'       => ( $is_stable ? 'Stable' : 'Development' ) . " version of the {$plugin_name} plugin's readme.txt file.",
				'active'            => 1,
			);

			$branch_project = GP::$project->by_path( $branch_project_path );
			if ( ! $branch_project ) {
				$branch_project = GP::$project->create_and_select( $project_args );
				if ( ! $branch_project ) {
					WP_CLI::line( "Sorry, but couldn't create nonexistent https://translate.wordpress.org/projects/{$branch_project_path}." );
				} else {
					WP_CLI::line( "Created https://translate.wordpress.org/projects/{$branch_project_path}." );
				}
			} else {
				$updated = $branch_project->update( $project_args );
				if ( ! $updated ) {
					WP_CLI::line( "Sorry, but couldn't update https://translate.wordpress.org/projects/{$branch_project_path}." );
				} else {
					WP_CLI::line( "Updated https://translate.wordpress.org/projects/{$branch_project_path}." );
				}
			}
		}

		return $branch_project;
	}

	/**
	 * Adds translation sets to a new plugin project.
	 *
	 * @param GP_Translation_Set[] $translation_sets Array of translation sets.
	 * @param GP_Project           $project          The project to add the sets to.
	 */
	private function add_translation_sets_to_branches( $translation_sets, $project ) {
		if ( empty( $project ) || empty( $project->id ) ) {
			return;
		}

		$existing_sets = array();
		foreach ( GP::$translation_set->by_project_id( $project->id ) as $set ) {
			$existing_sets[ $set->locale . ':' . $set->slug ] = true;
		}

		foreach ( $translation_sets as $ts ) {
			if ( isset( $existing_sets[ $ts->locale . ':' . $ts->slug ] ) ) {
				// This translation set already exists.
				continue;
			}

			$new_ts = GP::$translation_set->create( array(
				'project_id' => $project->id,
				'name'       => $ts->name,
				'locale'     => $ts->locale,
				'slug'       => $ts->slug,
			) );

			if ( empty( $new_ts ) ) {
				WP_CLI::line( "Sorry, but couldn't create nonexistent https://translate.wordpress.org/projects/{$project->path}/{$ts->locale}/{$ts->slug}." );
			} else {
				WP_CLI::line( "Created https://translate.wordpress.org/projects/{$project->path}/{$ts->locale}/{$ts->slug}." );
			}
		}
	}

	/**
	 * Retrieves the details of a plugin from the wordpress.org API.
	 *
	 * @param string $slug Slug of a plugin
	 * @return object|null JSON object on success, null on failure.
	 */
	private function get_plugin_details( $slug ) {
		$http_context = $this->get_http_context();
		$json = @file_get_contents( "https://api.wordpress.org/plugins/info/1.0/{$slug}.json?fields=stable_tag", false, $http_context );

		$details = $json && '{' == $json[0] ? json_decode( $json ) : null;

		return $details;
	}

	/**
	 * Creates a stream context with a default HTTP user-agent.
	 *
	 * @return resource Stream context with a default HTTP user-agent.
	 */
	private function get_http_context() {
		return stream_context_create( array(
			'http' => array(
				'user_agent' => 'WordPress.org Translate',
			),
		) );
	}
}
