<?php
/**
 * Address Review Feedback prompt.
 *
 * @package WordPressdotorg\Abilities\Plugins\Plugin_Directory\Prompts
 */

declare( strict_types = 1 );

namespace WordPressdotorg\Abilities\Plugins\Plugin_Directory\Prompts;

defined( 'ABSPATH' ) || exit;

/**
 * Address_Review_Feedback class.
 */
class Address_Review_Feedback {

	/**
	 * Register this prompt as an ability.
	 */
	public static function register(): void {
		wp_register_ability(
			'wporg/plugins/plugin-directory/address-review-feedback',
			array(
				'label'               => 'Address Review Feedback',
				'description'         => 'Guided workflow to understand and address plugin reviewer feedback after submission.',
				'category'            => 'wporg-plugins-plugin-directory',
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'plugin_slug' => array(
							'type'        => 'string',
							'description' => 'The plugin slug as assigned during submission.',
						),
					),
					'required'   => array( 'plugin_slug' ),
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
		// Defensive fallback — input_schema marks plugin_slug as required but the framework may not enforce it.
		$plugin_slug = $input['plugin_slug'] ?? '{plugin_slug}';

		$text = <<<MD
Help me address the review feedback for my plugin `{$plugin_slug}` submitted to the WordPress.org plugin directory.

## Step 1: Fetch Plugin Status and Review Feedback

Use the `wporg/plugins/plugin-directory/get-plugin-status` tool with slug `{$plugin_slug}` to retrieve the latest reviewer feedback. If the plugin status is not "pending", there is no actionable feedback to address.

## Step 2: Parse Each Issue

For each issue raised by the reviewer:
- Identify what the reviewer is asking for
- Locate the relevant file(s) and line(s) in the plugin
- Explain why this is a problem (reference the plugin guidelines if applicable)

## Step 3: Apply Fixes

For each issue:
- Show the current code
- Explain the fix
- Apply the change

## Step 4: Re-validate

After all fixes:
1. Re-run Plugin Check locally to ensure no new issues were introduced:
```bash
wp plugin check {$plugin_slug} --categories=plugin_repo --format=json --error-severity=7 --warning-severity=6 --include-low-severity-errors --exclude-checks=prefixing
```
2. Review the fixes against `wporg://plugins/plugin-directory/plugin-guidelines`

## Step 5: Resubmit

Use the `wporg/submit-plugin` tool to upload the updated plugin ZIP for `{$plugin_slug}`.

The WordPress.org review team will be notified of the new version automatically.
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
