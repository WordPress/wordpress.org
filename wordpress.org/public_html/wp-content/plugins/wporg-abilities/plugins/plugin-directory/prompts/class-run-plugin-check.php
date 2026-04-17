<?php
/**
 * Run Plugin Check prompt.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Prompts
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Run_Plugin_Check class.
 */
class Run_Plugin_Check {

	/**
	 * Register this prompt as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins--plugin-directory--run-plugin-check',
			array(
				'label'               => 'Run Plugin Check',
				'description'         => 'Instructions for running the Plugin Check (PCP) plugin locally to validate a plugin before submission.',
				'category'            => 'wporg-plugins-plugin-directory',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'plugin_path' => array(
							'type'        => 'string',
							'description' => 'Path to the plugin directory or main plugin file.',
						),
					),
					'required'   => array( 'plugin_path' ),
				),
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp'         => array( 'type' => 'prompt' ),
					'annotations' => array( 'readonly' => true ),
				),
			)
		);
	}

	/**
	 * Return the prompt messages.
	 *
	 * @param array $input The prompt arguments.
	 * @return array MCP prompt messages.
	 */
	public static function execute( array $input ): array {
		// Defensive fallback — input_schema marks plugin_path as required but the framework may not enforce it.
		$plugin_path     = sanitize_text_field( $input['plugin_path'] ?? '{plugin_path}' );
		$plugin_path_arg = escapeshellarg( $plugin_path );

		$text = <<<MD
Run the WordPress Plugin Check (PCP) tool against the plugin at `{$plugin_path}`.

Read the `wporg://plugins/plugin-directory/plugin-check-guide` resource for setup instructions, result interpretation, and fix priorities.

Follow the guide to create `blueprint.json`, then run:

```bash
npx @wp-playground/cli php \\
  --blueprint=blueprint.json \\
  --mount={$plugin_path_arg}:/wordpress/wp-content/plugins/\$(basename {$plugin_path_arg}) \\
  --quiet \\
  -- /tmp/wp-cli.phar plugin check \$(basename {$plugin_path_arg}) \\
  --categories=plugin_repo --format=json \\
  --error-severity=7 --warning-severity=6 \\
  --include-low-severity-errors --exclude-checks=prefixing
```

Fix all ERRORs and WARNINGs, then re-run to confirm a clean result. Clean up the blueprint file when done.
MD;

		return array(
			'messages' => array(
				array(
					'role'    => 'user',
					'content' => array(
						'type' => 'text',
						'text' => $text,
					),
				),
			),
		);
	}
}
