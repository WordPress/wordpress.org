<?php
/**
 * Prepare Plugin for Submission prompt.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Prompts
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Prepare_Plugin class.
 */
class Prepare_Plugin {

	/**
	 * Register this prompt as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/prepare-plugin-for-submission',
			array(
				'label'               => 'Prepare Plugin for Submission',
				'description'         => 'Guided workflow to prepare a WordPress plugin for submission to the WordPress.org plugin directory.',
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
Prepare the plugin at `{$plugin_path}` for submission to the WordPress.org plugin directory. Follow each step in order and report results:

## Step 1: Review Guidelines and FAQ

Read the `wporg://plugins/plugin-directory/plugin-faq` resource to understand the submission and review process.

Read the `wporg://plugins/plugin-directory/plugin-guidelines` resource and verify the plugin complies with all requirements, especially:
- GPL-compatible license for all code, libraries, and assets
- No obfuscated code
- No tracking without opt-in consent
- Proper sanitization, escaping, and nonce usage
- Developer has permission to upload this plugin for others to use and share

Do not proceed until the plugin adheres to all guidelines.

## Step 2: Check Plugin Headers

Open the main plugin file and verify these headers are present and valid:
- Plugin Name (required)
- Description (under 140 characters)
- Version (numeric, e.g., 1.0.0)
- Author
- License (must be GPLv2 or later compatible)
- Text Domain (must match the intended plugin slug)

Read the `wporg://plugins/plugin-directory/plugin-headers` resource for the full specification.

## Step 3: Validate readme.txt

Use the `wporg/validate-readme` tool with the contents of `{$plugin_path}/readme.txt`.

If no readme.txt exists, create one following the `wporg://plugins/plugin-directory/readme-standard` resource.

## Step 4: Run Plugin Check Locally

Run the Plugin Check (PCP) plugin locally using WP-CLI:

```bash
wp plugin check {$plugin_path} --categories=plugin_repo --format=json --error-severity=7 --warning-severity=6 --include-low-severity-errors --exclude-checks=prefixing
```

If PCP is not installed, install it first: `wp plugin install plugin-check --activate`

Fix all ERRORs before proceeding. See `wporg://plugins/plugin-directory/plugin-check-guide` for details.

## Step 5: Check Slug Availability

Choose a slug based on the plugin name (lowercase, hyphens, 5+ characters).

Review `wporg://plugins/plugin-directory/reserved-slugs` to ensure it is not reserved or trademarked.

## Step 6: Submission Readiness Report

Summarize the results as a pass/fail checklist. These match the confirmations required when submitting:
- [ ] Developer has read the FAQ
- [ ] Plugin complies with all Plugin Developer Guidelines
- [ ] Developer has permission to upload this plugin for others to use and share
- [ ] Plugin, all included libraries, and any other included assets are GPL or GPL-compatible
- [ ] Plugin has been tested with Plugin Check and all issues resolved (apart from false positives)
- [ ] Plugin headers complete and valid
- [ ] readme.txt validates without errors
- [ ] Slug is available and not reserved/trademarked

All confirmations must be true before the plugin can be submitted via `wporg/submit-plugin`.

Constraints:
- Only 1 plugin can be in the submission queue at a time
- Plugin slugs must be 5+ characters, alphanumeric and hyphens only
- No reserved or trademarked terms in the slug
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
