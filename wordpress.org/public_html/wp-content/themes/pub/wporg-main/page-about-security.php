<?php
/**
 * Template Name: Security
 *
 * Page template for displaying the Security page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

if ( false === stristr( home_url(), 'test' ) && ! isset( $_GET['test'] ) ) {
	return get_template_part( 'page' );
}

$GLOBALS['menu_items'] = [
	'about/requirements' => __( 'Requirements', 'wporg' ),
	'about/features'     => __( 'Features', 'wporg' ),
	'about/security'     => __( 'Security', 'wporg' ),
	'about/roadmap'      => __( 'Roadmap', 'wporg' ),
	'about/history'      => __( 'History', 'wporg' ),
];

// Prevent Jetpack from looking for a non-existent featured image.
add_filter( 'jetpack_images_pre_get_images', function() {
	return new \WP_Error();
} );

// See inc/page-meta-descriptions.php for the meta description for this page.

// Translation Note: Currently this is translated through https://github.com/WordPress/Security-White-Paper

get_header( 'child-page' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title col-8"><?php esc_html_e( 'Security', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p>Learn more about WordPress core software security in this free white paper. You can also download it in <a href="https://github.com/WordPress/Security-White-Paper/blob/master/WordPressSecurityWhitePaper.pdf?raw=true">PDF format</a>.</p>

					<img src="//s.w.org/about/images/logos/wordpress-logo-stacked-rgb.png" class="aligncenter" />

					<h2>Overview</h2>

					<p>This document is an analysis and explanation of the WordPress core software development and its related security processes, as well as an examination of the inherent security built directly into the software. Decision makers evaluating WordPress as a content management system or web application framework should use this document in their analysis and decision-making, and for developers to refer to it to familiarize themselves with the security components and best practices of the software.</p>

					<p>The information in this document is up-to-date for the latest stable release of the software, WordPress 4.7 at time of publication, but should be considered relevant also to the most recent versions of the software as backwards compatibility is a strong focus for the WordPress development team. Specific security measures and changes will be noted as they have been added to the core software in specific releases. It is strongly encouraged to always be running the latest stable version of WordPress to ensure the most secure experience possible.</p>
					<h2>Executive Summary</h2>
					<p>WordPress is a dynamic open-source content management system which is used to power millions of websites, web applications, and blogs. It currently powers more than 27% of the top 10 million websites on the Internet. WordPress' usability, extensibility, and mature development community make it a popular and secure choice for websites of all sizes.</p>

					<p>Since its inception in 2003, WordPress has undergone continual hardening so its core software can address and mitigate common security threats, including the Top 10 list identified by The Open Web Application Security Project (OWASP) as common security vulnerabilities, which are discussed in this document.</p>

					<p>The WordPress Security Team, in collaboration with the WordPress Core Leadership Team and backed by the WordPress global community, works to identify and resolve security issues in the core software available for distribution and installation at WordPress.org, as well as recommending and documenting security best practices for third-party plugin and theme authors.</p>

					<p>Site developers and administrators should pay particular attention to the correct use of core APIs and underlying server configuration which have been the source of common vulnerabilities, as well as ensuring all users employ strong passwords to access WordPress.</p>
					<h2>An Overview of WordPress</h2>
					<p>WordPress is a free and open source content management system (CMS). It is the most widely-used CMS software in the world and it powers more than 27% of the top 10 million websites<sup id="ref1"><a href="#footnote1">1</a></a></sup>, giving it an estimated 58% market share of all sites using a CMS.</p>

					<p>WordPress is licensed under the General Public License (GPLv2 or later) which provides four core freedoms, and can be considered as the WordPress "bill of rights":</p>
					<ol>
						<li>The freedom to run the program, for any purpose.</li>
						<li>The freedom to study how the program works, and change it to make it do what you wish.</li>
						<li>The freedom to redistribute.</li>
						<li>The freedom to distribute copies of your modified versions to others.</li>
					</ol>
					<h3>The WordPress Core Leadership Team</h3>
					<p>The WordPress project is a meritocracy, run by a core leadership team, and led by its co-creator and lead developer, Matt Mullenweg. The team governs all aspects of the project, including core development, WordPress.org, and community initiatives.</p>

					<p>The Core Leadership Team consists of Matt Mullenweg, five lead developers, and more than a dozen core developers with permanent commit access. These developers have final authority on technical decisions, and lead architecture discussions and implementation efforts.</p>

					<p>WordPress has a number of contributing developers. Some of these are former or current committers, and some are likely future committers. These contributing developers are trusted and veteran contributors to WordPress who have earned a great deal of respect among their peers. As needed, WordPress also has guest committers, individuals who are granted commit access, sometimes for a specific component, on a temporary or trial basis.</p>

					<p>The core and contributing developers primarily guide WordPress development. Every version, hundreds of developers contribute code to WordPress. These core contributors are volunteers who contribute to the core codebase in some way.</p>
					<h3>The WordPress Release Cycle</h3>
					<p>Each WordPress release cycle is led by one or more of the core WordPress developers. A release cycle usually lasts around 4 months from the initial scoping meeting to launch of the version.</p>

					<p>A release cycle follows the following pattern<sup id="ref2"><a href="#footnote2">2</a></sup>:</p>
					<ul>
						<li>Phase 1: Planning and securing team leads. This is done in the #core chat room on Slack. The release lead discusses features for the next release of WordPress. WordPress contributors get involved with that discussion. The release lead will identify team leads for each of the features.</li>
						<li>Phase 2: Development work begins. Team leads assemble teams and work on their assigned features. Regular chats are scheduled to ensure the development keeps moving forward.</li>
						<li>Phase 3: Beta. Betas are released, and beta-testers are asked to start reporting bugs. No more commits for new enhancements or feature requests are carried out from this phase on. Third-party plugin and theme authors are encouraged to test their code against the upcoming changes.</li>
						<li>Phase 4: Release Candidate. There is a string freeze for translatable strings from this point on. Work is targeted on regressions and blockers only.</li>
						<li>Phase 5: Launch. WordPress version is launched and made available in the WordPress Admin for updates.</li>
					</ul>
					<h3>Version Numbering and Security Releases</h3>
					<p>A major WordPress version is dictated by the first two sequences. For example, 3.5 is a major release, as is 3.6, 3.7, or 4.0. There isn't a "WordPress 3" or "WordPress 4" and each major release is referred to by its numbering, e.g., "WordPress 3.9."</p>

					<p>Major releases may add new user features and developer APIs. Though typically in the software world, a "major" version means you can break backwards compatibility, WordPress strives to never break backwards compatibility. Backwards compatibility is one of the project's most important philosophies, with the aim of making updates much easier on users and developers alike.</p>

					<p>A minor WordPress version is dictated by the third sequence. Version 3.5.1 is a minor release, as is 3.4.2<sup id="ref3"><a href="#footnote3">3</a></sup>. A minor release is reserved for fixing security vulnerabilities and addressing critical bugs only. Since new versions of WordPress are released so frequently &mdash; the aim is every 4-5 months for a major release, and minor releases happen as needed &mdash; there is only a need for major and minor releases.</p>

					<h3>Version Backwards Compatibility</h3>
					<p>The WordPress project has a strong commitment to backwards compatibility. This commitment means that themes, plugins, and custom code continues to function when WordPress core software is updated, encouraging site owners to keep their WordPress version updated to the latest secure release.</p>
					<h2>WordPress and Security</h2>
					<h3>The WordPress Security Team</h3>
					<p>The WordPress Security Team is made up of approximately 50 experts including lead developers and security researchers &mdash; about half are employees of Automattic (makers of WordPress.com, the earliest and largest WordPress hosting platform on the web), and a number work in the web security field. The team consults with well-known and trusted security researchers and hosting companies<sup><a href="#footnote3">3</a></sup>.</p>

					<p>The WordPress Security Team often collaborates with other security teams to address issues in common dependencies, such as resolving the vulnerability in the PHP XML parser, used by the XML-RPC API that ships with WordPress, in WordPress 3.9.2<sup id="ref4"><a href="#footnote4">4</a></sup>. This vulnerability resolution was a result of a joint effort by both WordPress and Drupal security teams.</p>
					<h3>WordPress Security Risks, Process, and History</h3>
					<p>The WordPress Security Team believes in Responsible Disclosure by alerting the security team immediately of any potential vulnerabilities. Potential security vulnerabilities can be signaled to the Security Team via the <a href="https://hackerone.com/wordpress">WordPress HackerOne</a><sup id="ref5"><a href="#footnote5">5</a></sup>. The Security Team communicates amongst itself via a private Slack channel, and works on a walled-off, private Trac for tracking, testing, and fixing bugs and security problems.</p>

					<p>Each security report is acknowledged upon receipt, and the team works to verify the vulnerability and determine its severity. If confirmed, the security team then plans for a patch to fix the problem which can be committed to an upcoming release of the WordPress software or it can be pushed as an immediate security release, depending on the severity of the issue.</p>

					<p>For an immediate security release, an advisory is published by the Security Team to the WordPress.org News site<sup id="ref6"><a href="#footnote6">6</a></sup> announcing the release and detailing the changes. Credit for the responsible disclosure of a vulnerability is given in the advisory to encourage and reinforce continued responsible reporting in the future.</p>

					<p>Administrators of the WordPress software see a notification on their site dashboard to upgrade when a new release is available, and following the manual upgrade users are redirected to the About WordPress screen which details the changes. If administrators have automatic background updates enabled, they will receive an email after an upgrade has been completed.</p>
					<h3>Automatic Background Updates for Security Releases</h3>
					<p>Starting with version 3.7, WordPress introduced automated background updates for all minor releases<sup id="ref7"><a href="#footnote7">7</a></sup>, such as 3.7.1 and 3.7.2. The WordPress Security Team can identify, fix, and push out automated security enhancements for WordPress without the site owner needing to do anything on their end, and the security update will install automatically.</p>

					<p>When a security update is pushed for the current stable release of WordPress, the core team will also push security updates for all the releases that are capable of background updates (since WordPress 3.7), so these older but still recent versions of WordPress will receive security enhancements.</p>

					<p>Individual site owners can opt to remove automatic background updates through a simple change in their configuration file, but keeping the functionality is strongly recommended by the core team, as well as running the latest stable release of WordPress.</p>
					<h3>2013 OWASP Top 10</h3>
					<p>The Open Web Application Security Project (OWASP) is an online community dedicated to web application security. The OWASP Top 10 list<sup id="ref8"><a href="#footnote8">8</a></sup> focuses on identifying the most serious application security risks for a broad array of organizations. The Top 10 items are selected and prioritized in combination with consensus estimates of exploitability, detectability, and impact estimates.</p>

					<p>The following sections discuss the APIs, resources, and policies that WordPress uses to strengthen the core software and 3rd party plugins and themes against these potential risks.</p>
					<h4>A1 - Injection</h4>
					<p>There is a set of functions and APIs available in WordPress to assist developers in making sure unauthorized code cannot be injected, and help them validate and sanitize data. Best practices and documentation are available<sup id="ref9"><a href="#footnote9">9</a></sup> on how to use these APIs to protect, validate, or sanitize input and output data in HTML, URLs, HTTP headers, and when interacting with the database and filesystem. Administrators can also further restrict the types of file which can be uploaded via filters.</p>
					<h4>A2 - Broken Authentication and Session Management</h4>
					<p>WordPress core software manages user accounts and authentication and details such as the user ID, name, and password are managed on the server-side, as well as the authentication cookies. Passwords are protected in the database using standard salting and stretching techniques. Existing sessions are destroyed upon logout for versions of WordPress after 4.0.</p>
					<h4>A3 - Cross Site Scripting (XSS)</h4>
					<p>WordPress provides a range of functions which can help ensure that user-supplied data is safe<sup id="ref10"><a href="#footnote10">10</a></sup>. Trusted users, that is administrators and editors on a single WordPress installation, and network administrators only in WordPress Multisite, can post unfiltered HTML or JavaScript as they need to, such as inside a post or page. Untrusted users and user-submitted content is filtered by default to remove dangerous entities, using the KSES library through the <code>wp_kses</code> function.</p>

					<p>As an example, the WordPress core team noticed before the release of WordPress 2.3 that the function <code>the_search_query()</code> was being misused by most theme authors, who were not escaping the function's output for use in HTML. In a very rare case of slightly breaking backward compatibility, the function's output was changed in WordPress 2.3 to be pre-escaped.</p>
					<h4>A4 - Insecure Direct Object Reference</h4>
					<p>WordPress often provides direct object reference, such as unique numeric identifiers of user accounts or content available in the URL or form fields. While these identifiers disclose direct system information, WordPress' rich permissions and access control system prevent unauthorized requests.</p>
					<h4>A5 - Security Misconfiguration</h4>
					<p>The majority of the WordPress security configuration operations are limited to a single authorized administrator. Default settings for WordPress are continually evaluated at the core team level, and the WordPress core team provides documentation and best practices to tighten security for server configuration for running a WordPress site<sup id="ref11"><a href="#footnote11">11</a></sup>.</p>
					<h4>A6 - Sensitive Data Exposure</h4>
					<p>WordPress user account passwords are salted and hashed based on the Portable PHP Password Hashing Framework<sup id="ref12"><a href="#footnote12">12</a></sup>. WordPress' permission system is used to control access to private information such an registered users' PII, commenters' email addresses, privately published content, etc. In WordPress 3.7, a password strength meter was included in the core software providing additional information to users setting their passwords and hints on increasing strength. WordPress also has an optional configuration setting for requiring HTTPS.</p>
					<h4>A7 - Missing Function Level Access Control</h4>
					<p>WordPress checks for proper authorization and permissions for any function level access requests prior to the action being executed. Access or visualization of administrative URLs, menus, and pages without proper authentication is tightly integrated with the authentication system to prevent access from unauthorized users.</p>
					<h4>A8 - Cross Site Request Forgery (CSRF)</h4>
					<p>WordPress uses cryptographic tokens, called nonces<sup id="ref13"><a href="#footnote13">13</a></sup>, to validate intent of action requests from authorized users to protect against potential CSRF threats. WordPress provides an API for the generation of these tokens to create and verify unique and temporary tokens, and the token is limited to a specific user, a specific action, a specific object, and a specific time period, which can be added to forms and URLs as needed. Additionally, all nonces are invalidated upon logout.</p>
					<h4>A9 - Using Components with Known Vulnerabilities</h4>
					<p>The WordPress core team closely monitors the few included libraries and frameworks WordPress integrates with for core functionality. In the past the core team has made contributions to several third-party components to make them more secure, such as the update to fix a cross-site vulnerability in TinyMCE in WordPress 3.5.2<sup id="ref14"><a href="#footnote14">14</a></sup>.</p>

					<p>If necessary, the core team may decide to fork or replace critical external components, such as when the SWFUpload library was officially replaced by the Plupload library in 3.5.2, and a secure fork of SWFUpload was made available by the security team<sup id="ref15"><a href="#footnote15">15</a></sup> for those plugins who continued to use SWFUpload in the short-term.</p>
					<h4>A10 - Unvalidated Redirects and Forwards</h4>
					<p>WordPress' internal access control and authentication system will protect against attempts to direct users to unwanted destinations or automatic redirects. This functionality is also made available to plugin developers via an API, <code>wp_safe_redirect()</code><sup id="ref16"><a href="#footnote16">16</a></sup>.</p>
					<h3>Further Security Risks and Concerns</h3>
					<h4>XXE (XML eXternal Entity) processing attacks</h4>
					<p>When processing XML, WordPress disables the loading of custom XML entities to prevent both External Entity and Entity Expansion attacks. Beyond PHP's core functionality, WordPress does not provide additional secure XML processing API for plugin authors.</p>
					<h4>SSRF (Server Side Request Forgery) Attacks</h4>
					<p>HTTP requests issued by WordPress are filtered to prevent access to loopback and private IP addresses. Additionally, access is only allowed to certain standard HTTP ports.</p>
					<h2>WordPress Plugin and Theme Security</h2>
					<h3>The Default Theme</h3>
					<p>WordPress requires a theme to be enabled to render content visible on the frontend. The default theme which ships with core WordPress (currently "Twenty Fifteen") has been vigorously reviewed and tested for security reasons by both the team of theme developers plus the core development team.</p>

					<p>The default theme can serve as a starting point for custom theme development, and site developers can create a child theme which includes some customization but falls back on the default theme for most functionality and security. The default theme can be easily removed by an administrator if not needed.</p>

					<h3>WordPress.org Theme and Plugin Repositories</h3>

					<p>There are approximately 50,000+ plugins and 4,500+ themes listed on the WordPress.org site. These themes and plugins are submitted for inclusion and are manually reviewed by volunteers before making them available on the repository.</p>

					<p>Inclusion of plugins and themes in the repository is not a guarantee that they are free from security vulnerabilities. Guidelines are provided for plugin authors to consult prior to submission for inclusion in the repository<sup id="ref17"><a href="#footnote17">17</a></sup>, and extensive documentation about how to do WordPress theme development<sup id="ref18"><a href="#footnote18">18</a></sup> is provided on the WordPress.org site.</p>

					<p>Each plugin and theme has the ability to be continually developed by the plugin or theme owner, and any subsequent fixes or feature development can be uploaded to the repository and made available to users with that plugin or theme installed with a description of that change. Site administrators are notified of plugins which need to be updated via their administration dashboard.</p>

					<p>When a plugin vulnerability is discovered by the WordPress Security Team, they contact the plugin author and work together to fix and release a secure version of the plugin. If there is a lack of response from the plugin author or if the vulnerability is severe, the plugin/theme is pulled from the public directory, and in some cases, fixed and updated directly by the Security Team.</p>
					<h3>The Theme Review Team</h3>
					<p>The Theme Review Team is a group of volunteers, led by key and established members of the WordPress community, who review and approve themes submitted to be included in the official WordPress Theme directory. The Theme Review Team maintains the official Theme Review Guidelines<sup id="ref19"><a href="#footnote19">19</a></sup>, the Theme Unit Test Data<sup id="ref20"><a href="#footnote20">20</a></sup>, and the Theme Check Plugin<sup id="ref21"><a href="#footnote21">21</a></sup>, and attempts to engage and educate the WordPress Theme developer community regarding development best practices. Inclusion in the group is moderated by core committers of the WordPress development team.</p>
					<h2>The Role of the Hosting Provider in WordPress Security</h2>
					<p>WordPress can be installed on a multitude of platforms. Though WordPress core software provides many provisions for operating a secure web application, which were covered in this document, the configuration of the operating system and the underlying web server hosting the software is equally important to keep the WordPress applications secure.</p>
					<h3>A Note about WordPress.com and WordPress security</h3>
					<p>WordPress.com is the largest WordPress installation in the world, and is owned and managed by Automattic, Inc., which was founded by Matt Mullenweg, the WordPress project co-creator. WordPress.com runs on the core WordPress software, and has its own security processes, risks, and solutions<sup id="ref22"><a href="#footnote22">22</a></sup>. This document refers to security regarding the self-hosted, downloadable open source WordPress software available from WordPress.org and installable on any server in the world.</p>
					<h2>Appendix</h2>
					<h3>Core WordPress APIs</h3>
					<p>The WordPress Core Application Programming Interface (API) is comprised of several individual APIs<sup id="ref23"><a href="#footnote23">23</a></sup>, each one covering the functions involved in, and use of, a given set of functionality. Together, these form the project interface which allows plugins and themes to interact with, alter, and extend WordPress core functionality safely and securely.</p>

					<p>While each WordPress API provides best practices and standardized ways to interact with and extend WordPress core software, the following WordPress APIs are the most pertinent to enforcing and hardening WordPress security:</p>

					<h3>Database API</h3>

					<p>The Database API<sup id="ref24"><a href="#footnote24">24</a></sup>, added in WordPress 0.71, provides the correct method for accessing data as named values which are stored in the database layer.</p>

					<h3>Filesystem API</h3>

					<p>The Filesystem API<sup id="ref25"><a href="#footnote25">25</a></sup>, added in WordPress 2.6<sup id="ref26"><a href="#footnote26">26</a></sup>, was originally created for WordPress' own automatic updates feature. The Filesystem API abstracts out the functionality needed for reading and writing local files to the filesystem to be done securely, on a variety of host types.</p>

					<p>It does this through the <code>WP_Filesystem_Base</code> class, and several subclasses which implement different ways of connecting to the local filesystem, depending on individual host support. Any theme or plugin that needs to write files locally should do so using the WP_Filesystem family of classes.</p>

					<h3>HTTP API</h3>

					<p>The HTTP API<sup id="ref27"><a href="#footnote27">27</a></sup>, added in WordPress 2.7<sup id="ref28"><a href="#footnote28">28</a></sup> and extended further in WordPress 2.8, standardizes the HTTP requests for WordPress. The API handles cookies, gzip encoding and decoding, chunk decoding (if HTTP 1.1), and various other HTTP protocol implementations. The API standardizes requests, tests each method prior to sending, and, based on your server configuration, uses the appropriate method to make the request.</p>

					<h3>Permissions and current user API</h3>

					<p>The permissions and current user API<sup id="ref29"><a href="#footnote29">29</a></sup> is a set of functions which will help verify the current user's permissions and authority to perform any task or operation being requested, and can protect further against unauthorized users accessing or performing functions beyond their permitted capabilities.</p>
					<h3>White paper content License</h3>
					<p>The text in this document (not including the WordPress logo or <a href="http://wordpressfoundation.org/trademark-policy/">trademark</a>) is licensed under <a href="https://creativecommons.org/publicdomain/zero/1.0/">CC0 1.0 Universal (CC0 1.0) Public Domain Dedication</a>. You can copy, modify, distribute and perform the work, even for commercial purposes, all without asking permission.</p>

					<p><em>A special thank you to Drupal's </em><a href="http://drupalsecurityreport.org/"><em>security white paper</em></a><em>, which provided some inspiration. </em></p>
					<h3>Additional Reading</h3>
					<ul>
						<li>WordPress News <a href="https://wordpress.org/news/">https://wordpress.org/news/</a></li>
						<li>WordPress Security releases <a href="https://wordpress.org/news/category/security/">https://wordpress.org/news/category/security/</a></li>
						<li>WordPress Developer Resources <a href="https://developer.wordpress.org/">https://developer.wordpress.org/</a></li>
					</ul>

					<hr />

					<p><em>Authored by </em>Sara Rosso </p>

					<p><em>Contributions from</em> Barry Abrahamson, Michael Adams, Jon Cave, Helen Hou-Sand&iacute;, Dion Hulse, Mo Jangda, Paul Maiorana</p>

					<p><em>Version 1.0 March 2015</em></p>

					<hr />

					<h3>Footnotes</h3>
					<ul>
						<li id='footnote1'><a href="#ref1">[1]</a> <a href="http://w3techs.com/">http://w3techs.com/</a>, as of March 2017</li>
						<li id='footnote2'><a href="#ref2">[2]</a> <a href="https://make.wordpress.org/core/handbook/about/release-cycle/">https://make.wordpress.org/core/handbook/about/release-cycle/</a></li>
						<li id='footnote3'><a href="#ref3">[3]</a> Andrew Nacin, WordPress lead developer, <a href="http://vip.wordpress.com/security">http://vip.wordpress.com/security</a></li>
						<li id='footnote4'><a href="#ref4">[4]</a> <a href="https://wordpress.org/news/2014/08/wordpress-3-9-2/">https://wordpress.org/news/2014/08/wordpress-3-9-2/</a></li>
						<li id='footnote5'><a href="#ref5">[5]</a> <a href="http://codex.wordpress.org/Security_FAQ">http://codex.wordpress.org/Security_FAQ</a></li>
						<li id='footnote6'><a href="#ref6">[6]</a> <a href="https://wordpress.org/news/">https://wordpress.org/news/</a></li>
						<li id='footnote7'><a href="#ref7">[7]</a> <a href="https://wordpress.org/news/2013/10/basie/">https://wordpress.org/news/2013/10/basie/</a></li>
						<li id='footnote8'><a href="#ref8">[8]</a> <a href="https://www.owasp.org/index.php/Top_10_2013-Top_10">https://www.owasp.org/index.php/Top_10_2013-Top_10</a></li>
						<li id='footnote9'><a href="#ref9">[9]</a> <a href="https://developer.wordpress.org/plugins/security/">https://developer.wordpress.org/plugins/security/</a></li>
						<li id='footnote10'><a href="#ref10">[10]</a> <a href="http://codex.wordpress.org/Data_Validation#HTML.2FXML">http://codex.wordpress.org/Data_Validation#HTML.2FXML</a></li>
						<li id='footnote11'><a href="#ref11">[11]</a> <a href="http://codex.wordpress.org/Hardening_WordPress">http://codex.wordpress.org/Hardening_WordPress</a></li>
						<li id='footnote12'><a href="#ref12">[12]</a> <a href="http://www.openwall.com/phpass/">http://www.openwall.com/phpass/</a></li>
						<li id='footnote13'><a href="#ref13">[13]</a> <a href="https://developer.wordpress.org/plugins/security/nonces/">https://developer.wordpress.org/plugins/security/nonces/</a></li>
						<li id='footnote14'><a href="#ref14">[14]</a> <a href="https://wordpress.org/news/2013/06/wordpress-3-5-2/">https://wordpress.org/news/2013/06/wordpress-3-5-2/</a></li>
						<li id='footnote15'><a href="#ref15">[15]</a> <a href="https://make.wordpress.org/core/2013/06/21/secure-swfupload/">https://make.wordpress.org/core/2013/06/21/secure-swfupload/</a></li>
						<li id='footnote16'><a href="#ref16">[16]</a> <a href="https://developer.wordpress.org/reference/functions/wp_safe_redirect/">https://developer.wordpress.org/reference/functions/wp_safe_redirect/</a></li>
						<li id='footnote17'><a href="#ref17">[17]</a> <a href="https://wordpress.org/plugins/about/guidelines/">https://wordpress.org/plugins/about/guidelines/</a></li>
						<li id='footnote18'><a href="#ref18">[18]</a> <a href="https://developer.wordpress.org/themes/getting-started/">https://developer.wordpress.org/themes/getting-started/</a></li>
						<li id='footnote19'><a href="#ref19">[19]</a> <a href="http://codex.wordpress.org/Theme_Review">http://codex.wordpress.org/Theme_Review</a></li>
						<li id='footnote20'><a href="#ref20">[20]</a> <a href="http://codex.wordpress.org/Theme_Unit_Test">http://codex.wordpress.org/Theme_Unit_Test</a></li>
						<li id='footnote21'><a href="#ref21">[21]</a> <a href="https://wordpress.org/plugins/theme-check/">https://wordpress.org/plugins/theme-check/</a></li>
						<li id='footnote22'><a href="#ref22">[22]</a> <a href="http://automattic.com/security/">http://automattic.com/security/</a></li>
						<li id='footnote23'><a href="#ref23">[23]</a> <a href="https://codex.wordpress.org/WordPress_APIs">https://codex.wordpress.org/WordPress_APIs</a></li>
						<li id='footnote24'><a href="#ref24">[24]</a> <a href="https://codex.wordpress.org/Database_API">https://codex.wordpress.org/Database_API</a></li>
						<li id='footnote25'><a href="#ref25">[25]</a> <a href="https://codex.wordpress.org/Filesystem_API">https://codex.wordpress.org/Filesystem_API</a></li>
						<li id='footnote26'><a href="#ref26">[26]</a> <a href="http://codex.wordpress.org/Version_2.6">http://codex.wordpress.org/Version_2.6</a></li>
						<li id='footnote27'><a href="#ref27">[27]</a> <a href="https://codex.wordpress.org/HTTP_API">https://codex.wordpress.org/HTTP_API</a></li>
						<li id='footnote28'><a href="#ref28">[28]</a> <a href="https://codex.wordpress.org/Version_2.7">https://codex.wordpress.org/Version_2.7</a></li>
						<li id='footnote29'><a href="#ref29">[29]</a> <a href="http://codex.wordpress.org/Function_Reference/current_user_can">http://codex.wordpress.org/Function_Reference/current_user_can</a></li>
					</ul>
				</section>
			</div><!-- .entry-content -->

		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
