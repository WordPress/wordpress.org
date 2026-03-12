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
			'wporg/plugins/plugin-directory/run-plugin-check',
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
		$plugin_path = $input['plugin_path'] ?? '{plugin_path}';

		$text = <<<MD
Run the WordPress Plugin Check (PCP) tool against the plugin at `{$plugin_path}`.

## Step 1: Set Up a WordPress Environment

If you already have a WordPress installation with WP-CLI available, skip to Step 2.

Otherwise, use `wp-env` to spin up a local environment:

```bash
npx @wordpress/env start
```

All `wp` commands below can be run inside `wp-env` by prefixing them with `npx wp-env run cli`:

```bash
npx wp-env run cli wp plugin install plugin-check -- --activate
```

## Step 2: Install Plugin Check

If not already installed:

```bash
wp plugin install plugin-check --activate
```

## Step 3: Run the Check

Use the exact flags that WordPress.org uses during submission review:

```bash
wp plugin check {$plugin_path} \\
  --categories=plugin_repo \\
  --format=json \\
  --error-severity=7 \\
  --warning-severity=6 \\
  --include-low-severity-errors \\
  --exclude-checks=prefixing
```

## Step 4: Interpret Results

Parse the JSON output and categorize issues:
- **ERROR**: Must fix — these block WordPress.org approval
- **ERROR (low severity)**: Review carefully — may be false positives but reviewers will check
- **WARNING**: Should fix — improves quality but does not block approval
- **WARNING (low severity)**: Optional improvements

## Step 5: Fix Issues

Address issues in this priority order:
1. All ERRORs (blocking)
2. Low-severity errors that are not false positives
3. WARNINGs that indicate real problems

For each issue, identify the file and line number, explain the problem, and apply the fix.

## Step 6: Re-run

After fixing issues, re-run the check to confirm a clean result.
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
