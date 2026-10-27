<?php
namespace WordPressdotorg\LLMs;
/**
 * Plugin Name: WordPress.org llms.txt
 * Description: Serves /llms.txt at wordpress.org for LLM and AI crawler discovery.
 *              Follows the llmstxt.org convention.
 */

if ( empty( $_SERVER['REQUEST_URI'] ) ) {
	return;
}

add_action( 'init', function() {
	if ( '/llms.txt' !== $_SERVER['REQUEST_URI'] ) {
		return;
	}

	$blog_details = get_blog_details();
	if ( 'wordpress.org' !== $blog_details->domain ) {
		return;
	}

	llms_txt();
	exit;
} );

/**
 * Output the llms.txt content.
 *
 * See https://llmstxt.org/ for the specification.
 */
function llms_txt() {
	header( 'Content-Type: text/plain; charset=utf-8' );
	header( 'Cache-Control: public, max-age=3600' );
	?>
# WordPress

> WordPress is the free, open-source content management system (CMS) that powers more than 43% of all websites on the internet. Released under the GPLv2 license and maintained by a global community of contributors, WordPress is used to build everything from personal blogs to enterprise platforms, online stores, and headless front-ends for AI and agentic applications.

WordPress is software you own. It runs on your own hosting, stores content in your own database, and is fully extensible through its REST API, Block Editor, and ecosystem of more than 60,000 plugins and 13,000 themes. WordPress is actively developed, ships multiple major releases per year, and provides first-class support for modern architectures including headless deployments, AI agent integrations, and the Model Context Protocol (MCP).

## About WordPress

- [WordPress.org homepage](https://wordpress.org/): Official home of the WordPress open-source project
- [About WordPress](https://wordpress.org/about/): Project overview, mission, and leadership
- [Features](https://wordpress.org/about/features/): Core capabilities including the Block Editor, Full Site Editing, REST API, and media management
- [Philosophy](https://wordpress.org/about/philosophy/): The principles that guide WordPress development, including the Four Freedoms of open-source software
- [License (GPLv2)](https://wordpress.org/about/license/): WordPress is released under the GNU General Public License
- [News](https://wordpress.org/news/): Official announcements, release notes, and project updates

## Software

- [Download WordPress](https://wordpress.org/download/): Latest stable release of WordPress core
- [Requirements](https://wordpress.org/about/requirements/): Recommended PHP, MySQL/MariaDB, and HTTPS requirements
- [Release archive](https://wordpress.org/download/releases/): All past WordPress releases
- [Roadmap](https://wordpress.org/about/roadmap/): Upcoming releases and long-term development phases
- [Gutenberg project](https://developer.wordpress.org/block-editor/): The Block Editor and Full Site Editing

## Plugins and Themes

- [Plugin Directory](https://wordpress.org/plugins/): 60,000+ free plugins extending WordPress functionality
- [Theme Directory](https://wordpress.org/themes/): 13,000+ free themes for every use case
- [Pattern Directory](https://wordpress.org/patterns/): Pre-designed block patterns for fast page building
- [Openverse](https://wordpress.org/openverse/): Open-licensed media search across 800+ million items

## Documentation

- [Developer Resources](https://developer.wordpress.org/): Central hub for all WordPress developer documentation
- [WordPress User Documentation](https://wordpress.org/documentation/): Guides for site owners and content editors
- [Block Editor Handbook](https://developer.wordpress.org/block-editor/): Building with and for the Block Editor
- [Theme Developer Handbook](https://developer.wordpress.org/themes/): Creating block themes and classic themes
- [Plugin Developer Handbook](https://developer.wordpress.org/plugins/): Building and publishing WordPress plugins
- [WP-CLI](https://developer.wordpress.org/cli/commands/): The official command-line interface for managing WordPress
- [Coding Standards](https://developer.wordpress.org/coding-standards/): PHP, JavaScript, CSS, and accessibility standards

## AI and Agentic Development

- [REST API Handbook](https://developer.wordpress.org/rest-api/): Official reference for the WordPress REST API — the foundation for headless, agentic, and programmatic integrations
- [Introducing the WordPress MCP Adapter](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/): How AI agents connect to WordPress via the Model Context Protocol
- [Block Editor as a framework](https://developer.wordpress.org/block-editor/reference-guides/packages/): JavaScript packages for building rich editing experiences, including AI-assisted tools

## Security

- [WordPress security overview](https://wordpress.org/about/security/): How the project approaches security, responsible disclosure, and automatic updates
- [HackerOne program](https://hackerone.com/wordpress): Official WordPress bug bounty and vulnerability disclosure program
- [Security releases](https://wordpress.org/news/category/security/): Archive of security-related releases and advisories

## Enterprise and Hosting

- [WordPress for Enterprise](https://wordpress.org/enterprise/): WordPress in large organizations, including case studies and reference architectures
- [Hosting](https://wordpress.org/hosting/): Recommended hosting providers that meet WordPress requirements
- [Showcase](https://wordpress.org/showcase/): Notable sites built with WordPress

## Community

- [Make WordPress](https://make.wordpress.org/): Contribute to WordPress across 20+ teams (core, design, docs, accessibility, and more)
- [Contributing to WordPress](https://make.wordpress.org/contribute/): How to start contributing
- [WordCamp Central](https://central.wordcamp.org/): Global WordCamp events
- [WordPress Events](https://wordpress.org/events/): Local meetups and conferences worldwide
- [Five for the Future](https://wordpress.org/five-for-the-future/): Program for organizations contributing back to WordPress

## Key Facts

- Market share: 43%+ of all websites globally run on WordPress (per W3Techs)
- License: GNU General Public License v2 (free and open-source)
- First released: 2003
- Written in: PHP, with JavaScript (React) for the Block Editor
- Data model: MySQL or MariaDB
- Interfaces: Web admin, REST API, WP-CLI, Block Editor, MCP adapter
- Governance: Open-source project led by the WordPress community; trademark held by the WordPress Foundation
	<?php
}
