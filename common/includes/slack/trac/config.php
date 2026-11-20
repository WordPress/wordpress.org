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
	 * Post only the first line of each commit message to #core.
	 *
	 * @var string
	 */
	protected $primary_channel_commit_format = 'summary';

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
		'application-passwords'            => array(
			'#core-passwords' => true,
			'#core-restapi'   => true,
		),
		'auth-app'                         => array(
			'#core-passwords' => true,
			'#core-restapi'   => true,
		),
		'wp-includes/interactivity-api'    => '#core-interactivity-api',
		'wp-includes/html-api'             => '#core-html-api',
		'.devcontainer'                    => '#core-build-test-tools',
		'.github'                          => '#core-build-test-tools',
		'tools'                            => '#core-build-test-tools',
		'.prettier.js'                     => '#core-build-test-tools',
		'.env.example'                     => '#core-build-test-tools',
		'composer.json'                    => '#core-build-test-tools',
		'docker-compose.yml'               => '#core-build-test-tools',
		'Gruntfile.js'                     => '#core-build-test-tools',
		'webpack.config.js'                => '#core-build-test-tools',
		'wp-tests-config-sample.php'       => '#core-build-test-tools',
		'#\.n(v|p)mrc#'                    => '#core-build-test-tools',
		'#\.version-support-[a-z]+\.json#' => '#core-build-test-tools',
		'#^package(-lock)?\.json$#'        => '#core-build-test-tools',
		'#^php[a-z]+\.xml\.dist$#'         => '#core-build-test-tools',
	);

	/**
	 * Workflow keywords that cause ticket updates to be piped to particular channels.
	 * These trigger when the keyword is added to a ticket.
	 *
	 * @var array
	 */
	protected $ticket_keyword_filters = array(
		'needs-testing' => '#core-test',
	);

	/**
	 * Focuses that cause new tickets to be piped to particular channels.
	 * These also match commit messages that start with the focus.
	 *
	 * Unlike components, focuses post to the focus channel itself.
	 *
	 * @var array
	 */
	protected $ticket_component_filters = array(
		'coding-standards' => '#core-coding-standards',
		'css'              => '#core-css',
		'docs'             => '#core-docs',
		'javascript'       => '#core-js',
		'multisite'        => '#core-multisite',
		'performance'      => '#core-performance',
		'privacy'          => '#core-privacy',
		'rest-api'         => '#core-restapi',
	);

	/**
	 * Maps each Core Trac component to its component specific channel.
	 *
	 * Notifications for a component go to that channel's -firehose, -newtickets and -commits
	 * channels, not to the channel itself.
	 *
	 * Subcomponents map to their parent's channel, unless they have their own. Components
	 * that belong to more than one area map to an array of channels.
	 *
	 * Some components have no discussion channel, or an archived one, but still have
	 * -firehose, -newtickets and -commits channels.
	 *
	 * Commit messages are matched against every key, so keys can also be commit message prefixes
	 * that differ from the component name, such as "Customizer" or "Bundled Themes".
	 *
	 * Retired components have an empty value, and only post to the global channels.
	 *
	 * @var array
	 */
	protected $component_channels = array(
		'AI'                     => '#core-ai',
		'Build/Test Tools'       => '#core-build-test-tools',
		'Bundled Theme'          => '#core-themes',
		'Editor'                 => '#core-editor',
		'HTML API'               => '#core-html-api',
		'I18N'                   => '#core-i18n',
		'Import'                 => '#core-importers',
		'Interactivity API'      => '#core-interactivity-api',
		'Media'                  => '#core-media',
		'Networks and Sites'     => '#core-multisite',
		'Privacy'                => '#core-privacy',
		'REST API'               => '#core-restapi',
		'Themes'                 => '#core-themes',
		'Upgrade/Install'        => '#core-upgrade-install',

		// Subcomponents.
		'Abilities API'          => '#core-ai',
		'Application Passwords'  => array( '#core-passwords', '#core-restapi' ),
		'Autosave'               => '#core-editor',
		'Canonical'              => '#core-permalinks',
		'Charset'                => '#core-formatting',
		'Embeds'                 => '#core-media',
		'Emoji'                  => '#core-formatting',
		'Gallery'                => '#core-media',
		'Login and Registration' => array( '#core-passwords', '#core-users' ),
		'Menus'                  => '#core-themes',
		'Notes'                  => '#core-notes',
		'Pings/Trackbacks'       => '#core-comments',
		'Post Formats'           => '#core-posts-post-types',
		'Post Thumbnails'        => '#core-posts-post-types',
		'Quick/Bulk Edit'        => '#core-editor',
		'Revisions'              => '#core-posts-post-types',
		'Rewrite Rules'          => '#core-permalinks',
		'Role/Capability'        => '#core-users',
		'Shortcodes'             => '#core-formatting',
		'TinyMCE'                => '#core-editor', // #core-tinymce has been archived since 2025-06-24.
		'Upload'                 => '#core-media',
		'Widgets'                => '#core-themes',

		// Components with an archived component specific channel.
		'Bootstrap/Load'         => '#core-bootstrap',
		'Comments'               => '#core-comments',
		'Customize'              => '#core-customize',
		'Date/Time'              => '#core-datetime',
		'HTTP API'               => '#core-http-api',
		'Site Health'            => '#core-site-health',
		'Sitemaps'               => '#core-sitemaps',

		// Components that have never had a component specific channel.
		'Administration'         => '#core-administration',
		'Cache API'              => '#core-cache-api',
		'Cron API'               => '#core-cron-api',
		'Database'               => '#core-database',
		'Export'                 => '#core-export',
		'External Libraries'     => '#core-external-libraries',
		'Feeds'                  => '#core-feeds',
		'Filesystem API'         => '#core-filesystem-api',
		'Formatting'             => '#core-formatting',
		'General'                => '#core-general',
		'Help/About'             => '#core-help-about',
		'Mail'                   => '#core-mail',
		'Options, Meta APIs'     => '#core-options-meta-apis',
		'Permalinks'             => '#core-permalinks',
		'Plugins'                => '#core-plugins',
		'Posts, Post Types'      => '#core-posts-post-types',
		'Query'                  => '#core-query',
		'Script Loader'          => '#core-script-loader',
		'Security'               => '#core-security',
		'Taxonomy'               => '#core-taxonomy',
		'Toolbar'                => '#core-toolbar',
		'Users'                  => '#core-users',
		'XML-RPC'                => '#core-xml-rpc',

		// Commit message prefixes that differ from the component name.
		'About'                  => '#core-help-about',
		'Admin'                  => '#core-administration',
		'Admin Bar'              => '#core-toolbar',
		'App Passwords'          => array( '#core-passwords', '#core-restapi' ),
		'Block'                  => '#core-editor',
		'Blocks'                 => '#core-editor',
		'Bootstrap'              => '#core-bootstrap',
		'Build'                  => '#core-build-test-tools',
		'Bundled Themes'         => '#core-themes',
		'Cache'                  => '#core-cache-api',
		'Cron'                   => '#core-cron-api',
		'Customizer'             => '#core-customize',
		'Default Themes'         => '#core-themes',
		'Email'                  => '#core-mail',
		'Feed'                   => '#core-feeds',
		'Filesystem'             => '#core-filesystem-api',
		'Global Styles'          => '#core-editor',
		'HTTP'                   => '#core-http-api',
		'Importer'               => '#core-importers',
		'Importers'              => '#core-importers',
		'Install'                => '#core-upgrade-install',
		'KSES'                   => '#core-formatting',
		'L10n'                   => '#core-i18n',
		'List Tables'            => '#core-administration',
		'Load'                   => '#core-bootstrap',
		'Nav Menus'              => '#core-themes',
		'Network'                => '#core-multisite',
		'oEmbed'                 => '#core-media',
		'Options'                => '#core-options-meta-apis',
		'Post Types'             => '#core-posts-post-types',
		'Posts'                  => '#core-posts-post-types',
		'Rewrite'                => '#core-permalinks',
		'Script Modules'         => '#core-script-loader',
		'Scripts'                => '#core-script-loader',
		'Site Editor'            => '#core-editor',
		'Tests/Build'            => '#core-build-test-tools',
		'Theme JSON'             => '#core-editor',
		'Update/Install'         => '#core-upgrade-install',
		'Updates'                => '#core-upgrade-install',
		'Upgrade'                => '#core-upgrade-install',
		'Upgrader'               => '#core-upgrade-install',
		'Upgrades'               => '#core-upgrade-install',
		'Uploads'                => '#core-media',
		'WP_Debug_Data'          => '#core-site-health',
		'WPDB'                   => '#core-database',

		// Retired components.
		'Text Changes'           => '',
		'WordPress.org Site'     => '',
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
		'wporg-themes/'                         => [ '#meta' => true, '#themes' => true ],
		'theme-directory/'                      => [ '#meta' => true, '#themes' => true ],
		'wp-themes.com/'                        => [ '#meta' => true, '#themes' => true ],
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
		'Theme Review'                  => '#themes',
		'Photo Directory'               => [ '#meta' => true, '#photos' => true ],
		'Theme Directory'               => [ '#meta' => true, '#themes' => true ],
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
	protected $firehose_channel = '#themes-review-firehose';
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

