<?php

namespace Dotorg\Slack\Trac\Tracs;
use Dotorg\Slack\Trac\Trac;

class Core extends Trac {
	protected $name = 'WordPress';
	protected $primary_channel  = '#core';
	protected $commits_channel  = '#core-commits';
	protected $tickets_channel  = '#core-newtickets';
	protected $firehose_channel = '#core-firehose';

	protected $primary_channel_ticket_format = 'title';

	/**
	 * File paths that cause commits to be piped to particular channels.
	 * Start regex matches with # as your delimiter.
	 */
	protected $commit_path_filters = array(
		'wp-content/themes'                => '#core-themes',
		'wp-admin/css'                     => '#core-css',
		'wp-includes/css'                  => '#core-css',
		'customize'                        => '#core-customize',
		'editor-expand.js'                 => '#core-editor',
		'wp-admin/css/edit.css'            => '#core-editor',
		'wp-admin/js/post.js'              => '#core-editor',
		'wp-admin/edit-form-advanced.php'  => '#core-editor',
		'wp-includes/css/editor.css'       => '#core-editor',
		'wp-includes/js/tinymce'           => '#core-editor',
		'class-wp-editor.php'              => '#core-editor',
		'press-this.php'                   => '#core-pressthis',
		'#wp-(admin|includes)/ms-#'        => '#core-multisite',
		'wp-admin/network'                 => '#core-multisite',
		'#wp-admin/includes/(ms|network)#' => '#core-multisite',
		'rest-api'                         => '#core-restapi',
		'#wp-admin/.*(image|media)\b#'     => '#core-media',
		'#wp-includes/.*(image|media)\b#'  => '#core-media',
		'#wp-admin/.*(privacy|personal)#'  => '#core-privacy',
		'wp-includes/sitemaps'             => '#core-sitemaps',
		'#wp-admin/includes/.*upgrader#'   => '#core-upgrade-install',
		'#wp-admin/includes/.*install#'    => '#core-upgrade-install',
		'application-passwords'            => '#core-passwords',
		'auth-app'                         => '#core-passwords',
		'wp-includes/interactivity-api'    => '#core-interactivity-api',
		'wp-includes/html-api'             => '#core-html-api',
	);

	/**
	 * Components or focuses that cause new tickets to be piped to particular channels.
	 */
	protected $ticket_component_filters = array(
		'Bundled Theme'          => '#core-themes',
		'Customize'              => '#core-customize',
		'Date/Time'              => '#core-datetime',
		'Press This'             => '#core-pressthis',
		'multisite'              => '#core-multisite',
		'Networks and Sites'     => '#core-multisite',
		'REST API'               => '#core-restapi',
		'rest-api'               => '#core-restapi',
		'I18N'                   => '#core-i18n',
		'Media'                  => '#core-media',
		'docs'                   => '#core-docs',
		'css'                    => '#core-css',
		'javascript'             => '#core-js',
		'coding-standards'       => '#core-coding-standards',
		'performance'            => '#core-performance',
		'Privacy'                => '#core-privacy',
		'privacy'                => '#core-privacy',
		'Site Health'            => '#core-site-health',
		'Sitemaps'               => '#core-sitemaps',
		'Upgrade/Install'        => '#core-upgrade-install',
		'Application Passwords'  => [ '#core-passwords' => true, '#core-restapi' => true ],
		'Login and Registration' => '#core-passwords',
		'HTML API'               => '#core-html-api',
		'Interactivity API'      => '#core-interactivity-api',
	);
}

class Meta extends Trac {
	protected $name = 'WordPress.org Meta';
	protected $primary_channel  = '#meta';
	protected $commits_channel  = '#meta-commits';
	protected $firehose_channel = '#meta-firehose';

	protected $bypass_primary_channel_for_commit_filter_matches = true;
	protected $bypass_primary_channel_for_ticket_filter_matches = true;

	protected $commit_path_filters = array(
		'translate.wordpress.org/'              => [ '#meta-i18n' => true, '#polyglots' => true ],
		'global.wordpress.org/'                 => [ '#meta-i18n' => true, '#polyglots' => true ],
		'plugins/rosetta'                       => [ '#meta-i18n' => true, '#polyglots' => true ],
		'plugins/wporg-gp-'                     => [ '#meta-i18n' => true, '#polyglots' => true ],
		'translations'                          => [ '#meta-i18n' => true, '#polyglots' => true ],
		'mu-plugins/pub/locales'                => '#meta-i18n',
		'developer-reference/'                  => '#meta-devhub',
		'wporg-developer/'                      => '#meta-devhub',
		'trac.wordpress.org/'                   => '#meta-tracdev',
		'svn.wordpress.org/'                    => '#meta-tracdev',
		'wordpress.org/public_html/style/trac/' => '#meta-tracdev',
		'trac-notifications/'                   => '#meta-tracdev',
		'wordcamp.org/'                         => '#meta-wordcamp',
		'wporg-photos/'                         => [ '#meta' => true, '#photos' => true ],
		'photo-directory/'                      => [ '#meta' => true, '#photos' => true ],
		'wporg-themes/'                         => [ '#meta' => true, '#themereview' => true ],
		'theme-directory/'                      => [ '#meta' => true, '#themereview' => true ],
		'wp-themes.com/'                        => [ '#meta' => true, '#themereview' => true ],
		'wporg-plugins/'                        => [ '#meta' => true, '#pluginreview' => true ],
		'wporg-plugins-2024/'                   => [ '#meta' => true, '#pluginreview' => true ],
		'plugin-directory/'                     => [ '#meta' => true, '#pluginreview' => true ],
		'plugins/support-forums/'               => [ '#meta' => true, '#forums' => true ],
		'plugins/wporg-bbp-'                    => [ '#meta' => true, '#forums' => true ],
		'themes/pub/wporg-support/'             => [ '#meta' => true, '#forums' => true ],
		'themes/pub/wporg-support-2024/'        => [ '#meta' => true, '#forums' => true ],
	);

	protected $ticket_component_filters = array(
		'International Forums'          => [ '#meta-i18n' => true, '#polyglots' => true ],
		'International Sites (Rosetta)' => [ '#meta-i18n' => true, '#polyglots' => true ],
		'Translate Site & Plugins'      => [ '#meta-i18n' => true, '#polyglots' => true ],
		'Developer Hub'                 => '#meta-devhub',
		'Trac'                          => '#meta-tracdev',
		'WordPress.tv'                  => '#wptv',
		'WordCamp Site & Plugins'       => '#meta-wordcamp',
		'HelpHub'                       => '#meta-helphub',
		'Theme Review'                  => '#themereview',
		'Photo Directory'               => [ '#meta' => true, '#photos' => true ],
		'Theme Directory'               => [ '#meta' => true, '#themereview' => true ],
		'Plugin Directory'              => [ '#meta' => true, '#pluginreview' => true ],
		'Support Forums'                => [ '#meta' => true, '#forums' => true ],
	);
}

class bbPress extends Trac {
	protected $primary_channel  = '#bbpress';
	protected $commits_channel  = '#bbpress-commits';
	protected $tickets_channel  = '#bbpress-newtickets';
	protected $firehose_channel = '#bbpress-firehose';

	protected $primary_channel_ticket_format = 'title';

	protected $commit_path_filters = array(
		'branches/1.' => '#meta',
	);

	protected $color = '#2d8e42';
	protected $icon  = ':bbpress:';
}

class BuddyPress extends Trac {
	protected $primary_channel  = '#buddypress';
	protected $commits_channel  = '#buddypress-commits';
	protected $tickets_channel  = '#buddypress-newtickets';
	protected $firehose_channel = '#buddypress-firehose';

	protected $primary_channel_ticket_format = 'title';

	protected $color = '#d84800';
	protected $icon  = ':buddypress:';
}

class Dotorg extends Trac {
	protected $name = 'Private Dotorg';
	protected $public = false;
	protected $primary_channel  = 'dotorg';
	protected $firehose_channel = 'dotorg';
}

class Deploy extends Trac {
	protected $public = false;
	protected $tickets = false;

	protected $primary_channel  = 'dotorg';
	protected $firehose_channel = 'dotorg';
}

class GlotPress extends Trac {
	protected $primary_channel = '#glotpress';
	protected $firehose_channel = '#glotpress-firehose';
}

class Build extends Trac {
	protected $name = 'WordPress Build';
	protected $tickets = false;
}

class BackPress extends Trac {
	protected $commits_channel = '#meta';
}

class SupportPress extends Trac {
}

class Design extends Trac {
	protected $commit_template = 'https://core.trac.wordpress.org/changeset/design/%s';
	protected $commit_info_template = 'https://core.trac.wordpress.org/log/%s?rev=%s&format=changelog&limit=1&verbose=on';
}

class Plugins extends Trac {
}

class Themes extends Trac {
}

class i18n extends Trac {
	protected $name = 'WordPress i18n';
	protected $tickets = false;
}

class Unit_Tests extends Trac {
	protected $dormant = true;
	protected $slug = 'unit-tests';
	protected $name = 'Unit Tests (Old)';
}

class MU extends Trac {
	protected $dormant = true;
	protected $name = 'WordPress MU';
}

class OpenAtd extends Trac {
	protected $dormant = true;
	protected $name = 'After the Deadline';
}

class Code extends Trac {
	protected $dormant = true;
	protected $name = 'Code Repo';
}

class GSoC extends Trac {
	protected $dormant = true;
}

class Security extends Trac {
	protected $public = false;
	protected $commits = false;
}

class WordCamp extends Trac {
	protected $name = 'Private WordCamp.org';
	protected $public = false;
}

