<?php

namespace SlackCommitHook;

class Core_Trac extends Trac {
	protected $channels = array( '#core', '#core-commits' );
	protected $username = 'WordPress commit';
	protected $ticket_range = array( 3, 5 );
	protected $commit_range = array( 3, 5 );

	/**
	 * File paths that cause commits to be piped to particular channels.
	 * Start regex matches with # as your delimiter.
	 */
	protected $channel_matcher = array(
		'wp-content/themes'       => '#core-themes',
		'customize'               => '#core-customize',
		'editor-expand.js'        => '#feature-focus',
		'wp-admin/css/edit.css'   => '#feature-focus',
		'wp-admin/css/editor.css' => '#feature-focus',
		'press-this.php'          => '#feature-pressthis',
	);
}

class Meta_Trac extends Trac {
	protected $channels = array( '#meta', '#meta-commits' );
	protected $username = 'WordPress.org Meta commit';

	protected $channel_matcher = array(
		'translate.wordpress.org/' => '#meta-i18n',
		'global.wordpress.org/'    => '#meta-i18n',
		'translations'             => '#meta-i18n',
		'developer-reference/'     => '#meta-devhub',
		'wporg-developer/'         => '#meta-devhub',
	);
}

class bbPress_Trac extends Trac {
	protected $channels = array( '#bbpress', '#bbpress-commits' );
	protected $username = 'bbPress commit';
	protected $color    = '#080';
	protected $emoji    = ':bbpress:';
}

class BuddyPress_Trac extends Trac {
	protected $channels = array( '#buddypress', '#buddypress-commits' );
	protected $username = 'BuddyPress commit';
	protected $color    = '#d84800';
	protected $emoji    = ':buddypress:';
}

class Dotorg_Trac extends Trac {
	protected $channels = 'dotorg';
	protected $username = 'Private dotorg commit';
}

class Deploy_Trac extends Trac {
	protected $channels = 'dotorg';
	protected $username = 'Deploy commit';
}

class GlotPress_Trac extends Trac {
	protected $channels = '#glotpress';
	protected $username = 'GlotPress commit';
}
