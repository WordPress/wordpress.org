<?php

namespace Dotorg\SlackTracHooks;

class Core_Trac extends Trac {
	protected $commit_channels = array( '#core', '#core-commits' );
	protected $commit_username = 'WordPress commit';
	protected $commit_range = array( 3, 5 );
	protected $ticket_range = array( 3, 5 );

	protected $ticket_channels  = array( '#core', '#core-newtickets' );
	protected $ticket_username  = 'WordPress Trac';
	protected $firehose_channel = '#core-firehose';

	/**
	 * File paths that cause commits to be piped to particular channels.
	 * Start regex matches with # as your delimiter.
	 */
	protected $commit_path_filters = array(
		'wp-content/themes'       => '#core-themes',
		'customize'               => '#core-customize',
		'editor-expand.js'        => '#feature-focus',
		'wp-admin/css/edit.css'   => '#feature-focus',
		'wp-admin/css/editor.css' => '#feature-focus',
		'press-this.php'          => '#feature-pressthis',
	);

	/**
	 * Components or focuses that cause new tickets to be piped to particular channels.
	 */
	protected $ticket_component_filters = array(
		'Customize'     => '#core-customize',
		'Bundled Theme' => '#core-themes',
		'Press This'    => '#feature-pressthis',
	);
}

class Meta_Trac extends Trac {
	protected $commit_channels = array( '#meta', '#meta-commits' );
	protected $commit_username = 'WordPress.org Meta commit';

	protected $ticket_channels = array( '#meta-newtickets' );

	protected $commit_path_filters = array(
		'translate.wordpress.org/' => '#meta-i18n',
		'global.wordpress.org/'    => '#meta-i18n',
		'translations'             => '#meta-i18n',
		'developer-reference/'     => '#meta-devhub',
		'wporg-developer/'         => '#meta-devhub',
	);

	protected $ticket_component_filters = array(
		'International Forums'          => '#meta-i18n',
		'International Sites (Rosetta)' => '#meta-i18n',
		'translate.wordpress.org'       => '#meta-i18n',
		'developer.wordpress.org'       => '#meta-devhub',
	);
}

class bbPress_Trac extends Trac {
	protected $commit_channels = array( '#bbpress', '#bbpress-commits' );
	protected $commit_username = 'bbPress commit';
	protected $ticket_channels = array( '#bbpress', '#bbpress-newtickets' );
	protected $color    = '#080';
	protected $emoji    = ':bbpress:';
}

class BuddyPress_Trac extends Trac {
	protected $commit_channels = array( '#buddypress', '#buddypress-commits' );
	protected $commit_username = 'BuddyPress commit';
	protected $ticket_channels = array( '#buddypress', '#buddypress-newtickets' );
	protected $color    = '#d84800';
	protected $emoji    = ':buddypress:';
}

class Dotorg_Trac extends Trac {
	protected $commit_channels = 'dotorg';
	protected $commit_username = 'Private dotorg commit';
	protected $ticket_channels = 'dotorg';
}

class Deploy_Trac extends Trac {
	protected $commit_channels = 'dotorg';
	protected $commit_username = 'Deploy commit';
	protected $ticket_channels = 'dotorg';
}

class GlotPress_Trac extends Trac {
	protected $commit_channels = '#glotpress';
	protected $commit_username = 'GlotPress commit';
	protected $ticket_channels = '#glotpress';
}
