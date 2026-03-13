<?php
/**
 * Plugin Check (PCP) Guide resource.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Resources;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin_Check_Guide class.
 */
class Plugin_Check_Guide {

	/**
	 * Register this resource as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/plugin-check-guide',
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
		return array(
			array(
				'uri'      => 'wporg://plugins/plugin-directory/plugin-check-guide',
				'text'     => <<<'MD'
# Plugin Check (PCP) — Local Testing Guide

Plugin Check is the automated tool WordPress.org uses to validate plugin submissions. Running it locally before submitting catches issues early and speeds up the review process.

## Prerequisites

Plugin Check requires a WordPress environment with WP-CLI. If you don't have one, use `wp-env`:

```bash
npx @wordpress/env start
```

Then prefix all `wp` commands with `npx wp-env run cli`, e.g.:

```bash
npx wp-env run cli wp plugin install plugin-check -- --activate
```

## Installation

```bash
wp plugin install plugin-check --activate
```

Or install from WP Admin: Plugins → Add New → search "Plugin Check".

## Running Plugin Check via WP-CLI

Use the exact flags that WordPress.org uses during submission review:

```bash
wp plugin check {plugin-directory-or-file} \
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
MD,
				'mimeType' => 'text/markdown',
			),
		);
	}
}
