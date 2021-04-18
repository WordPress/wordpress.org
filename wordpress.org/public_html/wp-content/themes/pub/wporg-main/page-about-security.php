<?php
/**
 * Template Name: About -> Security
 *
 * Page template for displaying the Security page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

$GLOBALS['menu_items'] = [
	'about/requirements' => _x( 'Requirements', 'Page title', 'wporg' ),
	'about/features'     => _x( 'Features', 'Page title', 'wporg' ),
	'about/security'     => _x( 'Security', 'Page title', 'wporg' ),
	'about/roadmap'      => _x( 'Roadmap', 'Page title', 'wporg' ),
	'about/history'      => _x( 'History', 'Page title', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

/* See inc/page-meta-descriptions.php for the meta description for this page. */

/*
 * The contents of this page was last sync'd to the following commit:
 * https://github.com/WordPress/Security-White-Paper/commit/e805a609d3dc37aae03ed3da2262fe2d33849c53
 */

get_header( 'child-page' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header row">
				<h1 class="entry-title col-8"><?php the_title(); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>
					<?php
						printf(
							/* translators: %s: URL to English PDF */
							wp_kses_post( __( 'Learn more about WordPress core software security in this free white paper. You can also download it in <a href="%s">PDF format</a>.', 'wporg' ) ),
							'https://github.com/WordPress/Security-White-Paper/blob/master/WordPressSecurityWhitePaper.pdf?raw=true'
						);
					?>
					</p>

					<img src="//s.w.org/about/images/logos/wordpress-logo-stacked-rgb.png" class="aligncenter" />

					<h2><?php esc_html_e( 'Overview', 'wporg' ); ?></h2>

					<p><?php esc_html_e( 'This document is an analysis and explanation of the WordPress core software development and its related security processes, as well as an examination of the inherent security built directly into the software. Decision makers evaluating WordPress as a content management system or web application framework should use this document in their analysis and decision-making, and for developers to refer to it to familiarize themselves with the security components and best practices of the software.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'The information in this document is up-to-date for the latest stable release of the software, WordPress 4.7 at time of publication, but should be considered relevant also to the most recent versions of the software as backwards compatibility is a strong focus for the WordPress development team. Specific security measures and changes will be noted as they have been added to the core software in specific releases. It is strongly encouraged to always be running the latest stable version of WordPress to ensure the most secure experience possible.', 'wporg' ); ?></p>
					<h2><?php esc_html_e( 'Executive Summary', 'wporg' ); ?></h2>
					<p>
					<?php
						printf(
							/* translators: %s: WordPress Market share - 30. Note the following % sign is escaped as %%. */
							esc_html__( 'WordPress is a dynamic open-source content management system which is used to power millions of websites, web applications, and blogs. It currently powers more than %s%% of the top 10 million websites on the Internet. WordPress&#8217; usability, extensibility, and mature development community make it a popular and secure choice for websites of all sizes.', 'wporg' ),
							esc_html( WP_MARKET_SHARE )
						);
					?>
					</p>

					<p><?php esc_html_e( 'Since its inception in 2003, WordPress has undergone continual hardening so its core software can address and mitigate common security threats, including the Top 10 list identified by The Open Web Application Security Project (OWASP) as common security vulnerabilities, which are discussed in this document.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'The WordPress Security Team, in collaboration with the WordPress Core Leadership Team and backed by the WordPress global community, works to identify and resolve security issues in the core software available for distribution and installation at WordPress.org, as well as recommending and documenting security best practices for third-party plugin and theme authors.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'Site developers and administrators should pay particular attention to the correct use of core APIs and underlying server configuration which have been the source of common vulnerabilities, as well as ensuring all users employ strong passwords to access WordPress.', 'wporg' ); ?></p>
					<h2><?php esc_html_e( 'An Overview of WordPress', 'wporg' ); ?></h2>
					<p>
					<?php
						printf(
							/* translators: 1: WordPress Market share - 30. Note the following % sign is escaped as %%. 2: Footnote 3: Market Penetration - 60.  Note the following % sign is escaped as %%. */
							esc_html__( 'WordPress is a free and open source content management system (CMS). It is the most widely-used CMS software in the world and it powers more than %1$s%% of the top 10 million websites%2$s, giving it an estimated %3$s%% market share of all sites using a CMS.', 'wporg' ),
							esc_html( WP_MARKET_SHARE ),
							'<sup id="ref1"><a href="#footnote1">1</a></a></sup>',
							62
						);
					?>
					</p>

					<p><?php esc_html_e( 'WordPress is licensed under the General Public License (GPLv2 or later) which provides four core freedoms, and can be considered as the WordPress &#8220;bill of rights&#8221;:', 'wporg' ); ?></p>
					<ol>
						<li><?php esc_html_e( 'The freedom to run the program, for any purpose.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'The freedom to study how the program works, and change it to make it do what you wish.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'The freedom to redistribute.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'The freedom to distribute copies of your modified versions to others.', 'wporg' ); ?></li>
					</ol>
					<h3><?php esc_html_e( 'The WordPress Core Leadership Team', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'The WordPress project is a meritocracy, run by a core leadership team, and led by its co-creator and lead developer, Matt Mullenweg. The team governs all aspects of the project, including core development, WordPress.org, and community initiatives.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'The Core Leadership Team consists of Matt Mullenweg, five lead developers, and more than a dozen core developers with permanent commit access. These developers have final authority on technical decisions, and lead architecture discussions and implementation efforts.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'WordPress has a number of contributing developers. Some of these are former or current committers, and some are likely future committers. These contributing developers are trusted and veteran contributors to WordPress who have earned a great deal of respect among their peers. As needed, WordPress also has guest committers, individuals who are granted commit access, sometimes for a specific component, on a temporary or trial basis.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'The core and contributing developers primarily guide WordPress development. Every version, hundreds of developers contribute code to WordPress. These core contributors are volunteers who contribute to the core codebase in some way.', 'wporg' ); ?></p>
					<h3><?php esc_html_e( 'The WordPress Release Cycle', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'Each WordPress release cycle is led by one or more of the core WordPress developers. A release cycle usually lasts around 4 months from the initial scoping meeting to launch of the version.', 'wporg' ); ?></p>

					<p>
					<?php
						printf(
							/* translators: %s: Footnote*/
							esc_html__( 'A release cycle follows the following pattern%s:', 'wporg' ),
							'<sup id="ref2"><a href="#footnote2">2</a></sup>'
						);
					?>
					</p>
					<ul>
						<li><?php esc_html_e( 'Phase 1: Planning and securing team leads. This is done in the #core chat room on Slack. The release lead discusses features for the next release of WordPress. WordPress contributors get involved with that discussion. The release lead will identify team leads for each of the features.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Phase 2: Development work begins. Team leads assemble teams and work on their assigned features. Regular chats are scheduled to ensure the development keeps moving forward.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Phase 3: Beta. Betas are released, and beta-testers are asked to start reporting bugs. No more commits for new enhancements or feature requests are carried out from this phase on. Third-party plugin and theme authors are encouraged to test their code against the upcoming changes.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Phase 4: Release Candidate. There is a string freeze for translatable strings from this point on. Work is targeted on regressions and blockers only.', 'wporg' ); ?></li>
						<li><?php esc_html_e( 'Phase 5: Launch. WordPress version is launched and made available in the WordPress Admin for updates.', 'wporg' ); ?></li>
					</ul>
					<h3><?php esc_html_e( 'Version Numbering and Security Releases', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'A major WordPress version is dictated by the first two sequences. For example, 3.5 is a major release, as is 3.6, 3.7, or 4.0. There isn&#8217;t a &#8220;WordPress 3&#8221; or &#8220;WordPress 4&#8221; and each major release is referred to by its numbering, e.g., &#8220;WordPress 3.9.&#8221;', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'Major releases may add new user features and developer APIs. Though typically in the software world, a &#8220;major&#8221; version means you can break backwards compatibility, WordPress strives to never break backwards compatibility. Backwards compatibility is one of the project&#8217;s most important philosophies, with the aim of making updates much easier on users and developers alike.', 'wporg' ); ?></p>

					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'A minor WordPress version is dictated by the third sequence. Version 3.5.1 is a minor release, as is 3.4.2%s. A minor release is reserved for fixing security vulnerabilities and addressing critical bugs only. Since new versions of WordPress are released so frequently &mdash; the aim is every 4-5 months for a major release, and minor releases happen as needed &mdash; there is only a need for major and minor releases.', 'wporg' ),
							'<sup id="ref3"><a href="#footnote3">3</a></sup>'
						);
					?>
					</p>

					<h3><?php esc_html_e( 'Version Backwards Compatibility', 'wporg' ); ?></h3>
					<p><?php esc_html_e( 'The WordPress project has a strong commitment to backwards compatibility. This commitment means that themes, plugins, and custom code continues to function when WordPress core software is updated, encouraging site owners to keep their WordPress version updated to the latest secure release.', 'wporg' ); ?></p>
					<h2><?php esc_html_e( 'WordPress and Security', 'wporg' ); ?></h2>
					<h3><?php esc_html_e( 'The WordPress Security Team', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: 1: Number - 50; 2: Footnote*/
							esc_html__( 'The WordPress Security Team is made up of approximately %1$s experts including lead developers and security researchers &mdash; about half are employees of Automattic (makers of WordPress.com, the earliest and largest WordPress hosting platform on the web), and a number work in the web security field. The team consults with well-known and trusted security researchers and hosting companies%2$s.', 'wporg' ),
							50,
							'<sup><a href="#footnote3">3</a></sup>'
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'The WordPress Security Team often collaborates with other security teams to address issues in common dependencies, such as resolving the vulnerability in the PHP XML parser, used by the XML-RPC API that ships with WordPress, in WordPress 3.9.2%s. This vulnerability resolution was a result of a joint effort by both WordPress and Drupal security teams.', 'wporg' ),
							'<sup id="ref4"><a href="#footnote4">4</a></sup>'
						);
					?>
					</p>
					<h3><?php esc_html_e( 'WordPress Security Risks, Process, and History', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: 1: HackerOne URL 2: Footnote */
							wp_kses_post( __( 'The WordPress Security Team believes in Responsible Disclosure by alerting the security team immediately of any potential vulnerabilities. Potential security vulnerabilities can be signaled to the Security Team via the <a href="%1$s">WordPress HackerOne</a>%2$s. The Security Team communicates amongst itself via a private Slack channel, and works on a walled-off, private Trac for tracking, testing, and fixing bugs and security problems.', 'wporg' ) ),
							'https://hackerone.com/wordpress',
							'<sup id="ref5"><a href="#footnote5">5</a></sup>'
						);
					?>
					</p>

					<p><?php esc_html_e( 'Each security report is acknowledged upon receipt, and the team works to verify the vulnerability and determine its severity. If confirmed, the security team then plans for a patch to fix the problem which can be committed to an upcoming release of the WordPress software or it can be pushed as an immediate security release, depending on the severity of the issue.', 'wporg' ); ?></p>

					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'For an immediate security release, an advisory is published by the Security Team to the WordPress.org News site%s announcing the release and detailing the changes. Credit for the responsible disclosure of a vulnerability is given in the advisory to encourage and reinforce continued responsible reporting in the future.', 'wporg' ),
							'<sup id="ref6"><a href="#footnote6">6</a></sup>'
						);
					?>
					</p>

					<p><?php esc_html_e( 'Administrators of the WordPress software see a notification on their site dashboard to upgrade when a new release is available, and following the manual upgrade users are redirected to the About WordPress screen which details the changes. If administrators have automatic background updates enabled, they will receive an email after an upgrade has been completed.', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'Automatic Background Updates for Security Releases', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'Starting with version 3.7, WordPress introduced automated background updates for all minor releases%s, such as 3.7.1 and 3.7.2. The WordPress Security Team can identify, fix, and push out automated security enhancements for WordPress without the site owner needing to do anything on their end, and the security update will install automatically.', 'wporg' ),
							'<sup id="ref7"><a href="#footnote7">7</a></sup>'
						);
					?>
					</p>

					<p><?php esc_html_e( 'When a security update is pushed for the current stable release of WordPress, the core team will also push security updates for all the releases that are capable of background updates (since WordPress 3.7), so these older but still recent versions of WordPress will receive security enhancements.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'Individual site owners can opt to remove automatic background updates through a simple change in their configuration file, but keeping the functionality is strongly recommended by the core team, as well as running the latest stable release of WordPress.', 'wporg' ); ?></p>
					<h3><?php esc_html_e( '2013 OWASP Top 10', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'The Open Web Application Security Project (OWASP) is an online community dedicated to web application security. The OWASP Top 10 list%s focuses on identifying the most serious application security risks for a broad array of organizations. The Top 10 items are selected and prioritized in combination with consensus estimates of exploitability, detectability, and impact estimates.', 'wporg' ),
							'<sup id="ref8"><a href="#footnote8">8</a></sup>'
						);
					?>
					</p>

					<p><?php esc_html_e( 'The following sections discuss the APIs, resources, and policies that WordPress uses to strengthen the core software and 3rd party plugins and themes against these potential risks.', 'wporg' ); ?></p>
					<h4><?php esc_html_e( 'A1 - Injection', 'wporg' ); ?></h4>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'There is a set of functions and APIs available in WordPress to assist developers in making sure unauthorized code cannot be injected, and help them validate and sanitize data. Best practices and documentation are available%s on how to use these APIs to protect, validate, or sanitize input and output data in HTML, URLs, HTTP headers, and when interacting with the database and filesystem. Administrators can also further restrict the types of file which can be uploaded via filters.', 'wporg' ),
							'<sup id="ref9"><a href="#footnote9">9</a></sup>'
						);
					?>
					</p>
					<h4><?php esc_html_e( 'A2 - Broken Authentication and Session Management', 'wporg' ); ?></h4>
					<p><?php esc_html_e( 'WordPress core software manages user accounts and authentication and details such as the user ID, name, and password are managed on the server-side, as well as the authentication cookies. Passwords are protected in the database using standard salting and stretching techniques. Existing sessions are destroyed upon logout for versions of WordPress after 4.0.', 'wporg' ); ?></p>
					<h4><?php esc_html_e( 'A3 - Cross Site Scripting (XSS)', 'wporg' ); ?></h4>
					<p>
					<?php
						printf(
							/* translators: 1: Footnote, 2: wp_kses() */
							esc_html__( 'WordPress provides a range of functions which can help ensure that user-supplied data is safe%1$s. Trusted users, that is administrators and editors on a single WordPress installation, and network administrators only in WordPress Multisite, can post unfiltered HTML or JavaScript as they need to, such as inside a post or page. Untrusted users and user-submitted content is filtered by default to remove dangerous entities, using the KSES library through the %2$s function.', 'wporg' ),
							'<sup id="ref10"><a href="#footnote10">10</a></sup>',
							'<code>wp_kses</code>'
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: %s: the_search_query() */
							esc_html__( 'As an example, the WordPress core team noticed before the release of WordPress 2.3 that the function %s was being misused by most theme authors, who were not escaping the function&#8217;s output for use in HTML. In a very rare case of slightly breaking backward compatibility, the function&#8217;s output was changed in WordPress 2.3 to be pre-escaped.', 'wporg' ),
							'<code>the_search_query()</code>'
						);
					?>
					</p>
					<h4><?php esc_html_e( 'A4 - Insecure Direct Object Reference', 'wporg' ); ?></h4>
					<p><?php esc_html_e( 'WordPress often provides direct object reference, such as unique numeric identifiers of user accounts or content available in the URL or form fields. While these identifiers disclose direct system information, WordPress&#8217; rich permissions and access control system prevent unauthorized requests.', 'wporg' ); ?></p>
					<h4><?php esc_html_e( 'A5 - Security Misconfiguration', 'wporg' ); ?></h4>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'The majority of the WordPress security configuration operations are limited to a single authorized administrator. Default settings for WordPress are continually evaluated at the core team level, and the WordPress core team provides documentation and best practices to tighten security for server configuration for running a WordPress site%s.', 'wporg' ),
							'<sup id="ref11"><a href="#footnote11">11</a></sup>'
						);
					?>
					</p>
					<h4><?php esc_html_e( 'A6 - Sensitive Data Exposure', 'wporg' ); ?></h4>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'WordPress user account passwords are salted and hashed based on the Portable PHP Password Hashing Framework%s. WordPress&#8217; permission system is used to control access to private information such an registered users&#8217; PII, commenters&#8217; email addresses, privately published content, etc. In WordPress 3.7, a password strength meter was included in the core software providing additional information to users setting their passwords and hints on increasing strength. WordPress also has an optional configuration setting for requiring HTTPS.', 'wporg' ),
							'<sup id="ref12"><a href="#footnote12">12</a></sup>'
						);
					?>
					</p>

					<h4><?php esc_html_e( 'A7 - Missing Function Level Access Control', 'wporg' ); ?></h4>
					<p><?php esc_html_e( 'WordPress checks for proper authorization and permissions for any function level access requests prior to the action being executed. Access or visualization of administrative URLs, menus, and pages without proper authentication is tightly integrated with the authentication system to prevent access from unauthorized users.', 'wporg' ); ?></p>

					<h4><?php esc_html_e( 'A8 - Cross Site Request Forgery (CSRF)', 'wporg' ); ?></h4>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'WordPress uses cryptographic tokens, called nonces%s, to validate intent of action requests from authorized users to protect against potential CSRF threats. WordPress provides an API for the generation of these tokens to create and verify unique and temporary tokens, and the token is limited to a specific user, a specific action, a specific object, and a specific time period, which can be added to forms and URLs as needed. Additionally, all nonces are invalidated upon logout.', 'wporg' ),
							'<sup id="ref13"><a href="#footnote13">13</a></sup>'
						);
					?>
					</p>

					<h4><?php esc_html_e( 'A9 - Using Components with Known Vulnerabilities', 'wporg' ); ?></h4>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'The WordPress core team closely monitors the few included libraries and frameworks WordPress integrates with for core functionality. In the past the core team has made contributions to several third-party components to make them more secure, such as the update to fix a cross-site vulnerability in TinyMCE in WordPress 3.5.2%s.', 'wporg' ),
							'<sup id="ref14"><a href="#footnote14">14</a></sup>'
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'If necessary, the core team may decide to fork or replace critical external components, such as when the SWFUpload library was officially replaced by the Plupload library in 3.5.2, and a secure fork of SWFUpload was made available by the security team<%s for those plugins who continued to use SWFUpload in the short-term.', 'wporg' ),
							'<sup id="ref15"><a href="#footnote15">15</a></sup>'
						);
					?>
					</p>

					<h4><?php esc_html_e( 'A10 - Unvalidated Redirects and Forwards', 'wporg' ); ?></h4>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							wp_kses_post( __( 'WordPress&#8217; internal access control and authentication system will protect against attempts to direct users to unwanted destinations or automatic redirects. This functionality is also made available to plugin developers via an API, <code>wp_safe_redirect()</code>%s.', 'wporg' ) ),
							'<sup id="ref16"><a href="#footnote16">16</a></sup>'
						);
					?>
					</p>
					<h3><?php esc_html_e( 'Further Security Risks and Concerns', 'wporg' ); ?></h3>
					<h4><?php esc_html_e( 'XXE (XML eXternal Entity) processing attacks', 'wporg' ); ?></h4>
					<p><?php esc_html_e( 'When processing XML, WordPress disables the loading of custom XML entities to prevent both External Entity and Entity Expansion attacks. Beyond PHP&#8217;s core functionality, WordPress does not provide additional secure XML processing API for plugin authors.', 'wporg' ); ?></p>
					<h4><?php esc_html_e( 'SSRF (Server Side Request Forgery) Attacks', 'wporg' ); ?></h4>
					<p><?php esc_html_e( 'HTTP requests issued by WordPress are filtered to prevent access to loopback and private IP addresses. Additionally, access is only allowed to certain standard HTTP ports.', 'wporg' ); ?></p>
					<h2><?php esc_html_e( 'WordPress Plugin and Theme Security', 'wporg' ); ?></h2>
					<h3><?php esc_html_e( 'The Default Theme', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: %s: The latest Core Theme release - Currently Twenty Seventeen */
							esc_html__( 'WordPress requires a theme to be enabled to render content visible on the frontend. The default theme which ships with core WordPress (currently "%s") has been vigorously reviewed and tested for security reasons by both the team of theme developers plus the core development team.', 'wporg' ),
							esc_html( wp_get_theme( 'core/' . WP_CORE_DEFAULT_THEME )->display( 'Name' ) )
						);
					?>
					</p>

					<p><?php esc_html_e( 'The default theme can serve as a starting point for custom theme development, and site developers can create a child theme which includes some customization but falls back on the default theme for most functionality and security. The default theme can be easily removed by an administrator if not needed.', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'WordPress.org Theme and Plugin Repositories', 'wporg' ); ?></h3>

					<p>
					<?php
						printf(
							/* translators: 1: Number of plugins - 50,000; 2: Number of themes - 5,000 */
							esc_html__(
								'There are approximately %1$s+ plugins and %2$s+ themes listed on the WordPress.org site. These themes and plugins are submitted for inclusion and are manually reviewed by volunteers before making them available on the repository.', 'wporg'
							),
							esc_html( number_format_i18n( 50000 ) ),
							esc_html( number_format_i18n( 5000 ) )
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: 1: Footnote; 2: Footnote */
							esc_html__( 'Inclusion of plugins and themes in the repository is not a guarantee that they are free from security vulnerabilities. Guidelines are provided for plugin authors to consult prior to submission for inclusion in the repository%1$s, and extensive documentation about how to do WordPress theme development%2$s is provided on the WordPress.org site.', 'wporg' ),
							'<sup id="ref17"><a href="#footnote17">17</a></sup>',
							'<sup id="ref18"><a href="#footnote18">18</a></sup>'
						);
					?>
					</p>

					<p><?php esc_html_e( 'Each plugin and theme has the ability to be continually developed by the plugin or theme owner, and any subsequent fixes or feature development can be uploaded to the repository and made available to users with that plugin or theme installed with a description of that change. Site administrators are notified of plugins which need to be updated via their administration dashboard.', 'wporg' ); ?></p>

					<p><?php esc_html_e( 'When a plugin vulnerability is discovered by the WordPress Security Team, they contact the plugin author and work together to fix and release a secure version of the plugin. If there is a lack of response from the plugin author or if the vulnerability is severe, the plugin/theme is pulled from the public directory, and in some cases, fixed and updated directly by the Security Team.', 'wporg' ); ?></p>
					<h3><?php esc_html_e( 'The Theme Review Team', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: 1: Footnote; 2: Footnote; 3: Footnote */
							esc_html__( 'The Theme Review Team is a group of volunteers, led by key and established members of the WordPress community, who review and approve themes submitted to be included in the official WordPress Theme directory. The Theme Review Team maintains the official Theme Review Guidelines%1$s, the Theme Unit Test Datas%2$s, and the Theme Check Plugins%3$s, and attempts to engage and educate the WordPress Theme developer community regarding development best practices. Inclusion in the group is moderated by core committers of the WordPress development team.', 'wporg' ),
							'<sup id="ref19"><a href="#footnote19">19</a></sup>',
							'<sup id="ref20"><a href="#footnote20">20</a></sup>',
							'<sup id="ref21"><a href="#footnote21">21</a></sup>'
						);
					?>
					</p>
					<h2><?php esc_html_e( 'The Role of the Hosting Provider in WordPress Security', 'wporg' ); ?></h2>
					<p><?php esc_html_e( 'WordPress can be installed on a multitude of platforms. Though WordPress core software provides many provisions for operating a secure web application, which were covered in this document, the configuration of the operating system and the underlying web server hosting the software is equally important to keep the WordPress applications secure.', 'wporg' ); ?></p>
					<h3><?php esc_html_e( 'A Note about WordPress.com and WordPress security', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'WordPress.com is the largest WordPress installation in the world, and is owned and managed by Automattic, Inc., which was founded by Matt Mullenweg, the WordPress project co-creator. WordPress.com runs on the core WordPress software, and has its own security processes, risks, and solutions%s. This document refers to security regarding the self-hosted, downloadable open source WordPress software available from WordPress.org and installable on any server in the world.', 'wporg' ),
							'<sup id="ref22"><a href="#footnote22">22</a></sup>'
						);
					?>
					</p>
					<h2><?php esc_html_e( 'Appendix', 'wporg' ); ?></h2>
					<h3><?php esc_html_e( 'Core WordPress APIs', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'The WordPress Core Application Programming Interface (API) is comprised of several individual APIs%s, each one covering the functions involved in, and use of, a given set of functionality. Together, these form the project interface which allows plugins and themes to interact with, alter, and extend WordPress core functionality safely and securely.', 'wporg' ),
							'<sup id="ref23"><a href="#footnote23">23</a></sup>'
						);
					?>
					</p>

					<p><?php esc_html_e( 'While each WordPress API provides best practices and standardized ways to interact with and extend WordPress core software, the following WordPress APIs are the most pertinent to enforcing and hardening WordPress security:', 'wporg' ); ?></p>

					<h3><?php esc_html_e( 'Database API', 'wporg' ); ?></h3>

					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'The Database API%s, added in WordPress 0.71, provides the correct method for accessing data as named values which are stored in the database layer.', 'wporg' ),
							'<sup id="ref24"><a href="#footnote24">24</a></sup>'
						);
					?>
					</p>

					<h3><?php esc_html_e( 'Filesystem API', 'wporg' ); ?></h3>

					<p>
					<?php
						printf(
							/* translators: 1: Footnote; 2: Footnote */
							esc_html__( 'The Filesystem API%1$s, added in WordPress 2.6%2$s, was originally created for WordPress&#8217; own automatic updates feature. The Filesystem API abstracts out the functionality needed for reading and writing local files to the filesystem to be done securely, on a variety of host types.', 'wporg' ),
							'<sup id="ref25"><a href="#footnote25">25</a></sup>',
							'<sup id="ref26"><a href="#footnote26">26</a></sup>'
						);
					?>
					</p>

					<p><?php echo wp_kses_post( __( 'It does this through the <code>WP_Filesystem_Base</code> class, and several subclasses which implement different ways of connecting to the local filesystem, depending on individual host support. Any theme or plugin that needs to write files locally should do so using the WP_Filesystem family of classes.', 'wporg' ) ); ?></p>

					<h3><?php esc_html_e( 'HTTP API', 'wporg' ); ?></h3>

					<p>
					<?php
						printf(
							/* translators: 1: Footnote; 2: Footnote */
							esc_html__( 'The HTTP API%1$s, added in WordPress 2.7%2$s and extended further in WordPress 2.8, standardizes the HTTP requests for WordPress. The API handles cookies, gzip encoding and decoding, chunk decoding (if HTTP 1.1), and various other HTTP protocol implementations. The API standardizes requests, tests each method prior to sending, and, based on your server configuration, uses the appropriate method to make the request.', 'wporg' ),
							'<sup id="ref27"><a href="#footnote27">27</a></sup>',
							'<sup id="ref28"><a href="#footnote28">28</a></sup>'
						);
					?>
					</p>

					<h3><?php esc_html_e( 'Permissions and current user API', 'wporg' ); ?></h3>

					<p>
					<?php
						printf(
							/* translators: %s: Footnote */
							esc_html__( 'The permissions and current user API%s is a set of functions which will help verify the current user&#8217;s permissions and authority to perform any task or operation being requested, and can protect further against unauthorized users accessing or performing functions beyond their permitted capabilities.', 'wporg' ),
							'<sup id="ref29"><a href="#footnote29">29</a></sup>'
						);
					?>
					</p>
					<h3><?php esc_html_e( 'White paper content License', 'wporg' ); ?></h3>
					<p>
					<?php
						printf(
							/* translators: 1: Link to WordPress Foundation Trademark Polocy (English); 2: Link to Creative Commons CC0 license (English) */
							wp_kses_post( __( 'The text in this document (not including the WordPress logo or <a href="%1$s">trademark</a>) is licensed under <a href="%2$s">CC0 1.0 Universal (CC0 1.0) Public Domain Dedication</a>. You can copy, modify, distribute and perform the work, even for commercial purposes, all without asking permission.', 'wporg' ) ),
							'https://wordpressfoundation.org/trademark-policy/',
							'https://creativecommons.org/publicdomain/zero/1.0/'
						);
					?>
					</p>

					<p>
					<?php
						printf(
							/* translators: %s: Link to the Drupal Security Whitepaper (english). */
							wp_kses_post( __( '<em>A special thank you to Drupal&#8217;s </em><a href="%s"><em>security white paper</em></a><em>, which provided some inspiration. </em>', 'wporg' ) ),
							'https://www.drupal.org/files/drupal-security-whitepaper-1-3_0.pdf'
						);
					?>
					</p>
					<h3><?php esc_html_e( 'Additional Reading', 'wporg' ); ?></h3>
					<ul>
						<li>
						<?php
							printf(
								/* translators: %s: Link to News Blog including the <a> tags. */
								esc_html__( 'WordPress News %s', 'wporg' ),
								'<a href="https://wordpress.org/news/">https://wordpress.org/news/</a>'
							);
						?>
						</li>
						<li>
						<?php
							printf(
								/* translators: %s: Link to News Blog Security Release Archive including the <a> tags. */
								esc_html__( 'WordPress Security releases %s', 'wporg' ),
								'<a href="https://wordpress.org/news/category/security/">https://wordpress.org/news/category/security/</a>'
							);
						?>
						</li>
						<li>
						<?php
							printf(
								/* translators: %s: Link to Developer.WordPress.org including the <a> tags. */
								esc_html__( 'WordPress Developer Resources %s', 'wporg' ),
								'<a href="https://developer.wordpress.org/">https://developer.wordpress.org/</a>'
							);
						?>
						</li>
					</ul>

					<hr />

					<p><?php echo wp_kses_post( __( '<em>Authored by</em> Sara Rosso', 'wporg' ) ); ?></p>

					<p><?php echo wp_kses_post( __( '<em>Contributions from</em> Barry Abrahamson, Michael Adams, Jon Cave, Helen Hou-Sand&iacute;, Dion Hulse, Mo Jangda, Paul Maiorana', 'wporg' ) ); ?></p>

					<p><?php echo wp_kses_post( __( '<em>Version 1.0 March 2015</em>', 'wporg' ) ); ?></p>

					<hr />

					<h3><?php esc_html_e( 'Footnotes', 'wporg' ); ?></h3>
					<ul>
						<li id='footnote1'><a href="#ref1">[1]</a> <a href="https://w3techs.com/">https://w3techs.com/</a>, as of December 2019</li>
						<li id='footnote2'><a href="#ref2">[2]</a> <a href="https://make.wordpress.org/core/handbook/about/release-cycle/">https://make.wordpress.org/core/handbook/about/release-cycle/</a></li>
						<li id='footnote3'><a href="#ref3">[3]</a> <a href="https://make.wordpress.org/core/handbook/about/release-cycle/version-numbering/">https://make.wordpress.org/core/handbook/about/release-cycle/version-numbering/</a></li>
						<li id='footnote4'><a href="#ref4">[4]</a> <a href="https://wordpress.org/news/2014/08/wordpress-3-9-2/">https://wordpress.org/news/2014/08/wordpress-3-9-2/</a></li>
						<li id='footnote5'><a href="#ref5">[5]</a> <a href="https://hackerone.com/wordpress">https://hackerone.com/wordpress</a></li>
						<li id='footnote6'><a href="#ref6">[6]</a> <a href="https://wordpress.org/news/">https://wordpress.org/news/</a></li>
						<li id='footnote7'><a href="#ref7">[7]</a> <a href="https://wordpress.org/news/2013/10/basie/">https://wordpress.org/news/2013/10/basie/</a></li>
						<li id='footnote8'><a href="#ref8">[8]</a> <a href="https://www.owasp.org/index.php/Top_10_2013-Top_10">https://www.owasp.org/index.php/Top_10_2013-Top_10</a></li>
						<li id='footnote9'><a href="#ref9">[9]</a> <a href="https://developer.wordpress.org/plugins/security/">https://developer.wordpress.org/plugins/security/</a></li>
						<li id='footnote10'><a href="#ref10">[10]</a> <a href="https://codex.wordpress.org/Data_Validation#HTML.2FXML">https://codex.wordpress.org/Data_Validation#HTML.2FXML</a></li>
						<li id='footnote11'><a href="#ref11">[11]</a> <a href="https://wordpress.org/support/article/hardening-wordpress/">https://wordpress.org/support/article/hardening-wordpress/</a></li>
						<li id='footnote12'><a href="#ref12">[12]</a> <a href="https://www.openwall.com/phpass/">https://www.openwall.com/phpass/</a></li>
						<li id='footnote13'><a href="#ref13">[13]</a> <a href="https://developer.wordpress.org/plugins/security/nonces/">https://developer.wordpress.org/plugins/security/nonces/</a></li>
						<li id='footnote14'><a href="#ref14">[14]</a> <a href="https://wordpress.org/news/2013/06/wordpress-3-5-2/">https://wordpress.org/news/2013/06/wordpress-3-5-2/</a></li>
						<li id='footnote15'><a href="#ref15">[15]</a> <a href="https://make.wordpress.org/core/2013/06/21/secure-swfupload/">https://make.wordpress.org/core/2013/06/21/secure-swfupload/</a></li>
						<li id='footnote16'><a href="#ref16">[16]</a> <a href="https://developer.wordpress.org/reference/functions/wp_safe_redirect/">https://developer.wordpress.org/reference/functions/wp_safe_redirect/</a></li>
						<li id='footnote17'><a href="#ref17">[17]</a> <a href="https://wordpress.org/plugins/developers/">https://wordpress.org/plugins/developers/</a></li>
						<li id='footnote18'><a href="#ref18">[18]</a> <a href="https://developer.wordpress.org/themes/getting-started/">https://developer.wordpress.org/themes/getting-started/</a></li>
						<li id='footnote19'><a href="#ref19">[19]</a> <a href="https://make.wordpress.org/themes/handbook/review/">https://make.wordpress.org/themes/handbook/review/</a></li>
						<li id='footnote20'><a href="#ref20">[20]</a> <a href="https://codex.wordpress.org/Theme_Unit_Test">https://codex.wordpress.org/Theme_Unit_Test</a></li>
						<li id='footnote21'><a href="#ref21">[21]</a> <a href="https://wordpress.org/plugins/theme-check/">https://wordpress.org/plugins/theme-check/</a></li>
						<li id='footnote22'><a href="#ref22">[22]</a> <a href="https://automattic.com/security/">https://automattic.com/security/</a></li>
						<li id='footnote23'><a href="#ref23">[23]</a> <a href="https://codex.wordpress.org/WordPress_APIs">https://codex.wordpress.org/WordPress_APIs</a></li>
						<li id='footnote24'><a href="#ref24">[24]</a> <a href="https://developer.wordpress.org/apis/handbook/database/">https://developer.wordpress.org/apis/handbook/database/</a></li>
						<li id='footnote25'><a href="#ref25">[25]</a> <a href="https://codex.wordpress.org/Filesystem_API">https://codex.wordpress.org/Filesystem_API</a></li>
						<li id='footnote26'><a href="#ref26">[26]</a> <a href="https://wordpress.org/support/wordpress-version/version-2-6/">https://wordpress.org/support/wordpress-version/version-2-6/</a></li>
						<li id='footnote27'><a href="#ref27">[27]</a> <a href="https://developer.wordpress.org/plugins/http-api/">https://developer.wordpress.org/plugins/http-api/</a></li>
						<li id='footnote28'><a href="#ref28">[28]</a> <a href="https://wordpress.org/support/wordpress-version/version-2-7/">https://wordpress.org/support/wordpress-version/version-2-7/</a></li>
						<li id='footnote29'><a href="#ref29">[29]</a> <a href="https://developer.wordpress.org/reference/functions/current_user_can/">https://developer.wordpress.org/reference/functions/current_user_can/</a></li>
					</ul>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
