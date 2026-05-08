<?php
/**
 * Plugin Check (PCP) Guide resource.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources;

use WordPressdotorg\Abilities\Plugins\Plugin_Directory\Ability_Base;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Check_Guide class.
 */
class Plugin_Check_Guide extends Ability_Base {

	/**
	 * Register this resource as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins--plugin-directory--plugin-check-guide',
			array(
				'label'               => 'Plugin Check (PCP) Guide',
				'description'         => 'How to install, run, and interpret the Plugin Check plugin locally before submitting to WordPress.org.',
				'category'            => 'wporg-plugins-plugin-directory',
				'execute_callback'    => array( __CLASS__, 'execute' ),
				'permission_callback' => '__return_true',
				'meta'                => array(
					'mcp' => array( 'type' => 'resource' ),
					'uri' => 'wporg://plugins/plugin-directory/plugin-check-guide',
				),
			)
		);
	}

	/**
	 * Return the resource content.
	 *
	 * @return array MCP resource contents array.
	 */
	public static function execute(): array {
		/*
		 * The blueprint below intentionally activates `--all` plugins and copies the
		 * Plugin Check object-cache.php drop-in. Both are required for runtime checks
		 * (enqueued script size/scope, non-blocking scripts, etc.) to register —
		 * Plugin Check gates them on `is_plugin_active()` and on the drop-in being
		 * present. Without both, only static checks run.
		 *
		 * The `cp` step must remain the LAST step. Plugin Check registers a WP-CLI
		 * `after_invoke` hook that auto-deletes the drop-in whenever any `wp plugin`
		 * subcommand finishes, so any `wp plugin …` step added after the `cp` will
		 * silently revert runtime checks to static-only.
		 */
		return array(
			array(
				'uri'      => 'wporg://plugins/plugin-directory/plugin-check-guide',
				'text'     => <<<'MD'
# Plugin Check (PCP) — Local Testing Guide

Plugin Check is the automated tool WordPress.org uses to validate plugin submissions. Running it locally before submitting catches issues early and speeds up the review process.

## Prerequisites

Running Plugin Check via WordPress Playground CLI requires only **Node.js** (v20+). No Docker, no local WordPress installation. Playground boots a temporary WordPress instance via WebAssembly.

If you already have a WordPress environment with WP-CLI, you can skip Playground and run `wp plugin check` directly (see "WP-CLI Flags Reference" below).

## Running Plugin Check via Playground CLI

Create one temporary file and run one command. This installs Plugin Check automatically.

### 1. Create `blueprint.json`

```json
{
  "steps": [
    {"step": "installPlugin", "pluginData": {"resource": "wordpress.org/plugins", "slug": "plugin-check"}},
    {"step": "wp-cli", "command": "wp plugin activate --all"},
    {"step": "cp",
      "fromPath": "/wordpress/wp-content/plugins/plugin-check/drop-ins/object-cache.copy.php",
      "toPath": "/wordpress/wp-content/object-cache.php"}
  ]
}
```

### 2. Run the check

```bash
npx @wp-playground/cli php \
  --blueprint=blueprint.json \
  --mount=/path/to/my-plugin:/wordpress/wp-content/plugins/my-plugin \
  --quiet \
  -- /tmp/wp-cli.phar plugin check my-plugin \
  --categories=plugin_repo --format=json \
  --error-severity=7 --warning-severity=6 \
  --include-low-severity-errors --exclude-checks=prefixing
```

Results are printed to stdout. `Success: Checks complete. No errors found.` means the plugin passed all checks.

For **remote zip files**, replace the `--mount` for the plugin with an `installPlugin` blueprint step:

```json
{"step": "installPlugin", "pluginData": {"resource": "url", "url": "https://example.com/my-plugin.zip"}}
```

For **local zip files**, extract the zip to a temporary directory and mount the extracted folder.

## WP-CLI Flags Reference

These are the exact flags WordPress.org uses during submission review:

```bash
wp plugin check {plugin-slug} \
  --categories=plugin_repo \
  --format=json \
  --error-severity=7 \
  --warning-severity=6 \
  --include-low-severity-errors \
  --exclude-checks=prefixing
```

## Understanding Results

Results are classified by severity:

- **ERROR** (blocking): Must be fixed before the plugin can be approved
- **ERROR (low severity)**: May be false positives, but reviewers will look at them
- **WARNING** (non-blocking): Should be fixed but will not block approval
- **WARNING (low severity)**: Recommendations for improvement

## Fix Priority

1. Fix all ERRORs first — these block submission
2. Review low-severity errors for false positives
3. Address WARNINGs to improve plugin quality

## Important Notes

- Plugin Check is a helpful pre-submission tool but NOT a guarantee of approval
- All submissions are also manually reviewed by the WordPress.org plugin review team
- A clean PCP report speeds up the review process significantly
- More info: https://wordpress.org/plugins/plugin-check/
MD
				,
				'mimeType' => 'text/markdown',
			),
		);
	}
}
