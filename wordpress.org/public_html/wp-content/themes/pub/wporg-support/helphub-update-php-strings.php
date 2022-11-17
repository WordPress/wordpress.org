<?php
/**
 * Injects the hard-coded page content for the Update PHP page, based on whether the page template is selected.
 *
 * @package HelpHub_Update_PHP
 */

use WordPressdotorg\API\Serve_Happy\RECOMMENDED_PHP;
use WordPressdotorg\API\Serve_Happy\MINIMUM_PHP;

/**
 * Injects the title for the Update PHP page if the Update PHP page template is selected.
 *
 * @param string $title Post title.
 * @param int    $id    Post ID.
 * @return string Filtered post title.
 */
function wporg_support_filter_update_php_title( $title, $id ) {
	if ( is_admin() ) {
		return $title;
	}

	if ( 'page-update-php.php' !== get_page_template_slug( $id ) ) {
		return $title;
	}

	return __( 'Get a faster, more secure website: update PHP today', 'wporg-forums' );
}
add_filter( 'the_title', 'wporg_support_filter_update_php_title', 5, 2 );

/**
 * Injects the content for the Update PHP page if the Update PHP page template is selected.
 *
 * @param string $content Post content.
 * @return string Filtered post content.
 */
function wporg_support_filter_update_php_content( $content ) {
	if ( is_admin() ) {
		return $content;
	}

	if ( ! in_the_loop() || get_the_ID() !== get_queried_object_id() ) {
		return $content;
	}

	if ( ! is_page_template( 'page-update-php.php' ) ) {
		return $content;
	}

	// Introduction.
	$content  = '<p><strong>' . __( 'Your WordPress site can be faster, and more secure, and you can make this happen!', 'wporg-forums' ) . '</strong></p>';
	$content .= '<p>' . __( 'This page will explain why this matters to you, and then how you can fix it.', 'wporg-forums' ) . '</p>';

	// Section "Why PHP Matters To You".
	$content .= '<h3>' . __( 'Why PHP Matters To You', 'wporg-forums' ) . '</h3>';
	$content .= '<p>';
	$content .= sprintf(
		/* translators: %s: link URL about keeping WordPress up to date */
		__( 'PHP is one of the programming languages that WordPress is built on. Your hosting company determines which versions of PHP are available for your site, and many hosters let you change this yourself. And while you may be familiar with the importance of <a href="%s">keeping WordPress, and your themes and plugins up-to-date</a>, keeping PHP up-to-date is just as important.', 'wporg-forums' ),
		esc_url( _x( 'https://wordpress.org/support/article/administration-screens/#updates', 'link URL about keeping WordPress up to date', 'wporg-forums' ) )
	);
	$content .= '</p>';
	$content .= '<p>' . __( 'There are two main benefits to keeping PHP up-to-date:', 'wporg-forums' ) . '</p>';
	$content .= '<ul>';
	$content .= '<li>';
	$content .= sprintf(
		/* translators: 1: link URL to WordPress requirements page, 2: recommended PHP version */
		__( '<strong>Your website may be faster</strong> because PHP becomes more efficient with each new version. Updating to <a href="%1$s">WordPress&#8217;s recommended PHP version</a> (currently %2$s or higher) can deliver a performance increase that will benefit all visitors to your website.', 'wporg-forums' ),
		esc_url( _x( 'https://wordpress.org/about/requirements/', 'link URL to WordPress requirements page', 'wporg-forums' ) ),
		RECOMMENDED_PHP
	);
	$content .= '</li>';
	$content .= '<li>';
	$content .= sprintf(
		/* translators: %s: recommended PHP version */
		__( '<strong>Your website will be more secure.</strong> PHP, like WordPress, is maintained by its community. Because PHP is so popular, it is a target for hackers – but the latest version will have the latest security features. Older versions of PHP (lower than %s) <em>do not have this</em>, so updating is essential to keep your WordPress site secure.', 'wporg-forums' ),
		RECOMMENDED_PHP
	);
	$content .= '</li>';
	$content .= '</ul>';
	$content .= '<p>' . __( 'And then there are a number of secondary benefits:', 'wporg-forums' ) . '</p>';
	$content .= '<ul>';
	$content .= '<li>' . __( '<strong>A faster WordPress website will be rewarded by search engines</strong>, so you&#8217;ll rank higher in search!', 'wporg-forums' ) . '</li>';
	$content .= '<li>' . __( '<strong>A faster website will retain visitors better</strong> (they&#8217;ll leave if it takes too long to load), making your website more effective.', 'wporg-forums' ) . '</li>';
	$content .= '<li>' . __( '<strong>A more secure website is better protected against hackers</strong>, and the cost and reputational damage associated with a hacked website.', 'wporg-forums' ) . '</li>';
	$content .= '</ul>';
	$content .= '<p>' . __( 'These benefits are good for you, and good for your website&#8217;s visitors. These are the reasons you should update PHP today. The next section will show you how to do this.', 'wporg-forums' ) . '</p>';

	// Section "How to check your PHP version".
	$content .= '<h3>' . __( 'How to check your PHP version', 'wporg-forums' ) . '</h3>';
	$content .= '<p>' . __( 'To check what version of PHP your WordPress site is using, from the WordPress Dashboard, select <em>Tools &gt; Site Health</em> from the sidebar menu, and then select the Info tab. Expand the Server section and scroll down until you see <strong>PHP version</strong>.', 'wporg-forums' ) . '</p>';
	$content .= '<p>';
	$content .= sprintf(
		/* translators: %s: recommended PHP version */
		__( 'If this number is at or higher than PHP %s, then you don&#8217;t need to update PHP at this time!' ),
		RECOMMENDED_PHP
	);
	$content .= '</p>';
	$content .= '<p>' . __( 'However, if your site is lower than the recommended version, running on an outdated and insecure version of PHP, then it&#8217;s time to update. You also may have noticed a warning such as "PHP Update Required" or "PHP Update Recommended" on the Dashboard or in Site Health – these serve as important reminders to update PHP and make your site more secure.', 'wporg-forums' ) . '</p>';

	// Section "Before you update your PHP version".
	$content .= '<h3>' . __( 'Before you update your PHP version', 'wporg-forums' ) . '</h3>';
	$content .= '<p>' . __( 'This section starts off with some warnings, but don&#8217;t be afraid! As with most things technical, we just need to cover some background before we can get to the part where you update your PHP version.', 'wporg-forums' ) . '</p>';
	$content .= '<p>';
	$content .= sprintf(
		/* translators: %s: minimum required PHP version */
		__( 'Updating your PHP version should not be a problem, but we can&#8217;t <em>guarantee</em> that it&#8217;s not. WordPress itself works with PHP as far back as version %s, but we don&#8217;t know if your themes or plugins will work on newer versions. They should, and popular or reputable ones almost certainly will, but they should be tested to make sure.', 'wporg-forums' ),
		MINIMUM_PHP
	);
	$content .= '</p>';
	$content .= '<p>' . __( 'There are a couple of steps you should take to avoid problems before proceeding:', 'wporg-forums' ) . '</p>';
	$content .= '<ul>';
	$content .= '<li>';
	$content .= sprintf(
		/* translators: %s: link URL for free backup plugins */
		__( '<strong>Make a backup of your website:</strong> a backup will let you revert your site to how it is right now in the event anything goes wrong. There are <a href="%s">plenty of free backup plugins available</a>, so if you don&#8217;t have a backup solution already – use one of these. In order to revert this backup, you&#8217;ll also need your web host to move your PHP version back to your current version (we&#8217;ll cover how to do this later).', 'wporg-forums' ),
		esc_url( _x( 'https://wordpress.org/plugins/search/backup/', 'link URL for free backup plugins', 'wporg-forums' ) )
	);
	$content .= '</li>';
	$content .= '<li>' . __( '<strong>Update WordPress, themes, and plugins:</strong> from your WordPress Dashboard, head to the Updates page under the <em>Dashboard &gt; Updates</em> menu, and then update everything. You should do this regularly anyway :). When done, check that your site is working as expected.', 'wporg-forums' ) . '</li>';
	$content .= '<li>';
	$content .= sprintf(
		/* translators: %s: link URL to the PHP Compatibility Checker plugin */
		__( '<strong>Check PHP compatibility:</strong> install the <a href="%s">PHP Compatibility Checker plugin</a> to check your themes and plugins for possible issues. This plugin isn&#8217;t perfect and may miss items or flag false positives, but it does work in most cases.', 'wporg-forums' ),
		esc_url( _x( 'https://wordpress.org/plugins/php-compatibility-checker/', 'link URL to the PHP Compatibility Checker plugin', 'wporg-forums' ) )
	);
	$content .= '</li>';
	$content .= '<li>';
	$content .= sprintf(
		/* translators: %s: link URL to wordpress.org */
		__( '<strong>Fix any PHP compatibility issues:</strong> if the PHP Compatibility Checker plugin picks up any issues, get in touch with the theme or plugin developer and ask them to investigate. If they can&#8217;t or won&#8217;t get back to you, have a look for themes or plugins on <a href="%s">WordPress.org</a> with similar functionality and use one of these instead.', 'wporg-forums' ),
		esc_url( _x( 'https://wordpress.org/', 'link URL to wordpress.org', 'wporg-forums' ) )
	);
	$content .= '</li>';
	$content .= '</ul>';
	$content .= '<p>' . __( 'Run through these steps, and you&#8217;ll be ready to update the PHP version on your WordPress site – and enjoy all of the benefits that come with this!', 'wporg-forums' ) . '</p>';
	$content .= '<p>' . __( 'If you run into any issues whilst doing this or need help, you should contact a professional web developer, your hosting company, or your theme and plugins authors. All of these will be happy to help here.', 'wporg-forums' ) . '</p>';
	$content .= '<p>' . __( 'We can now get on to the final part: actually updating your website&#8217;s PHP version.', 'wporg-forums' ) . '</p>';

	// Section "How to update your website's PHP version for a faster, more secure website".
	$content .= '<h3>' . __( 'How to update your website&#8217;s PHP version for a faster, more secure website', 'wporg-forums' ) . '</h3>';
	$content .= '<p>' . __( 'You&#8217;re now ready to update your website&#8217;s PHP version! You&#8217;ve done due diligence, got backups, and are in the best possible shape to do the update.', 'wporg-forums' ) . '</p>';
	$content .= '<p>' . __( 'PHP is installed on your website&#8217;s server, so updating it involves either interacting with your host&#8217;s settings or asking your provider to do it for you.', 'wporg-forums' ) . '</p>';
	$content .= '<p>';
	$content .= sprintf(
		/* translators: %s: link URL to hosting-specific update resources */
		__( 'Exactly <em>how</em> to do the update depends on your hosting company. We&#8217;ve asked hosting companies to submit instructions on how to update your PHP version on their hosting, and <a href="%s">you&#8217;ll find a list of hosts who have instructions available here</a>.', 'wporg-forums' ),
		esc_url( _x( 'https://github.com/WordPress/servehappy-resources/blob/master/tutorials/hosting-specific/tutorials-en.md', 'link URL to hosting-specific update resources', 'wporg-forums' ) )
	);
	$content .= '</p>';
	$content .= '<p>' . __( 'If you can&#8217;t find your host on this list, then email your hosting company and ask them to help! Here&#8217;s some template text you can use:', 'wporg-forums' ) . '</p>';
	$content .= '<pre>' . __( 'Dear Hosting Provider,<br><br>I want my WordPress website to be as performant and<br>secure as possible with the latest version of PHP.<br>If the server my site is hosted on is not already on<br>the latest version of PHP, please let me know what<br>steps I need to take to update it.<br><br>Thanks!', 'wporg-forums' ) . '</pre>';
	$content .= '<p>' . __( 'If you run into any issues at this stage, either change the PHP version back yourself, contact your hosting company or a professional web developer. In the unlikely event something goes wrong and you need to restore your backup, contact your host and ask them to restore the previous version of PHP you had running. You can then restore your backup.', 'wporg-forums' ) . '</p>';
	$content .= '<p>' . __( 'You should now have all the information you need to update! Nice work! With an up-to-date version of PHP you&#8217;ll enjoy a faster, more secure website and happier visitors.', 'wporg-forums' ) . '</p>';

	// Section "Faster, more secure WordPress websites for all".
	$content .= '<h3>' . __( 'Faster, more secure WordPress websites for all', 'wporg-forums' ) . '</h3>';
	$content .= '<p>' . __( 'Making sure you have the latest version of PHP ensures your website is as fast, and secure as possible.', 'wporg-forums' ) . '</p>';
	$content .= '<p>' . __( 'You now have all the information you need to update to the latest version of PHP, and you know how to update in the future as well. Look out for more PHP update messages on your WordPress Dashboard going forwards, or keep an eye on your hosting company&#8217;s news for more information.', 'wporg-forums' ) . '</p>';
	$content .= '<p>' . __( 'As a final reminder: contact your hosting company, a professional web developer, or your theme and/or plugin authors for any additional questions. They&#8217;ll all be able to help out with their areas of expertise.', 'wporg-forums' ) . '</p>';
	$content .= '<p><strong>' . __( 'Thanks for making the internet a better place!', 'wporg-forums' ) . '</strong></p>';

	return $content;
}
add_filter( 'the_content', 'wporg_support_filter_update_php_content', 5 );
