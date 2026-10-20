<?php
/**
 * WPOrgSidebar module configuration.
 *
 * @package WordPressdotorg\FreeScout\WPOrgSidebar
 */

declare( strict_types = 1 );

return array(
	'name'    => 'WPOrgSidebar',

	// Base URL of the WordPress.org helpdesk endpoints, with a trailing slash.
	'api_url' => env( 'WPORG_API_URL', 'https://api.wordpress.org/dotorg/freescout/' ),

	// Shared secret used to sign requests; must match FREESCOUT_SECRET on api.wordpress.org.
	'secret'  => env( 'WPORG_API_SECRET', '' ),

	/*
	 * Customer sidebar panels, keyed by ID, rendered in this order; `notes` and `attachments` add those to the payload.
	 * Panels with `per_mailbox` only show in the mailboxes chosen in their section under Manage » Settings.
	 */
	'panels'  => array(
		'profile'        => array(
			'title'       => 'WordPress.org',
			'endpoint'    => 'profile.php',
			// Offers the account a bounce names.
			'attachments' => true,
		),
		'forums'         => array(
			'title'       => 'Forum Notes',
			'endpoint'    => 'forums.php',
			'per_mailbox' => true,
		),
		'plugins-themes' => array(
			'title'       => 'Plugins & Themes',
			'endpoint'    => 'plugins-themes.php',
			'per_mailbox' => true,
			// Reviewers link plugins in notes.
			'notes'       => true,
		),
		'dpo'            => array(
			'title'       => 'Privacy Requests',
			'endpoint'    => 'dpo.php',
			'per_mailbox' => true,
		),
	),

	// Defaults for the options FreeScout saves the module's settings in.
	'options' => array(
		'mailboxes_forums'         => array( 'default' => array() ),
		'mailboxes_plugins-themes' => array( 'default' => array() ),
		'mailboxes_dpo'            => array( 'default' => array() ),
	),
);
