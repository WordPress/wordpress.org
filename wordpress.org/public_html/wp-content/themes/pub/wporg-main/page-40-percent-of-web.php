<?php
// phpcs:disable WordPress.Security.EscapeOutput
/**
 * Template Name: 40% Milestones Page
 *
 * Page template for displaying the "40% of the web" page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

/* See inc/page-meta-descriptions.php for the meta description for this page. */

get_header( 'wporg' );
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class( 'entry-40-percent-of-web' ); ?>>

			<div class="entry-content">
				<div class="wp-block-group alignfull _40p-timeline-header"><div class="wp-block-group__inner-container">

					<h1 class="has-text-align-left _40p-timeline-title has-primary-color has-text-color"><?php _e( 'Our journey<br>to powering<br><strong>40% of the web</strong>', 'wporg' ); ?></h1>

					<svg class="_40p-timeline-pills" id="pills" role="img" aria-hidden="true" focusable="false" width="1243" height="786" viewBox="0 0 1243 786" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect class="p1" x="141.207" y="311.977" width="470.465" height="144.379" rx="72.1895" transform="rotate(87.2018 141.207 311.977)" fill="#000"></rect>
						<rect class="p2" x="337.377" y="311.045" width="470.465" height="144.379" rx="72.1895" transform="rotate(91.4752 337.377 311.045)" fill="#000"></rect>
						<rect class="p3" x="525.91" y="253.748" width="470.465" height="144.379" rx="72.1895" transform="rotate(94.1993 525.91 253.748)" fill="#000"></rect>
						<rect class="p4" x="682.691" y="180.881" width="470.465" height="144.379" rx="72.1895" transform="rotate(86.5113 682.691 180.881)" fill="#000"></rect>
						<rect class="p5" width="470.465" height="144.379" rx="72.1895" transform="matrix(-0.0608521 0.998147 0.998147 0.0608521 746.812 73.0295)" fill="#000"></rect>
						<rect class="p6" x="1046.15" y="1.2782" width="470.465" height="144.379" rx="72.1895" transform="rotate(90.3055 1046.15 1.2782)" fill="#D13638"></rect>
						<rect class="p7" x="1250.01" y="10.0571" width="470.465" height="144.379" rx="72.1895" transform="rotate(96.3853 1250.01 10.0571)" fill="#000"></rect>
					</svg>
					<svg class="_40p-timeline-pills" id="pills-mobile" style="display: none;" role="img" aria-hidden="true" focusable="false" width="342" height="319" viewBox="0 0 342 319" fill="none" xmlns="http://www.w3.org/2000/svg">
						<rect x="68.6484" y="87.974" width="229.023" height="70.2838" rx="35.1419" transform="rotate(86.5113 68.6484 87.974)" fill="#000"></rect>
						<rect width="229.023" height="70.2838" rx="35.1419" transform="matrix(-0.0608521 0.998147 0.998147 0.0608521 99.8633 35.4716)" fill="#000"></rect>
						<rect x="344.816" y="4.81659" width="229.023" height="70.2838" rx="35.1419" transform="rotate(96.3853 344.816 4.81659)" fill="#000"></rect>
						<rect x="245.582" y="0.54303" width="229.023" height="70.2838" rx="35.1419" transform="rotate(90.3055 245.582 0.54303)" fill="#D13638"></rect>
					</svg>
					<p class="_40p-timeline-intro"><?php _e( 'Reaching this current high point involved an incredible amount of hard work from the amazing WordPress community. Take a look at 40 of the key milestones that helped shape the course.', 'wporg' ); ?></p>
				</div></div>

				<div style="height:50px" aria-hidden="true" class="wp-block-spacer"></div>

				<div class="wp-block-cooltimeline-timeline-block _40p-timeline-block">
					<div class="ctl-instant-timeline block-1619117550100  both-sided" style="--timeLineColor:#d13638;--textColor:#ffffff;--titleSize:22px;--descriptionSize:14px;--timeSize:36px">
						<div class="timeline-content" id="2003-ms-wordpress-born">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2003</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2003-wordpress-born"><?php _e( 'The blogging software b2 is forked, WordPress is born', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2003-ms-2003-first-release">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2003-first-release"><?php _e( 'First WordPress Release', 'wporg' ); ?><em>+</em></a> </h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2003</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2003-ms-great-renaming">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2003</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2003-great-renaming"><?php _e( 'The Great Renaming', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2004-ms-six-apart-doubled-downloads">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2004-six-apart-doubled-downloads"><?php _e( 'Six Apart, Doubled Downloads', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2004</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2004-ms-plugin-system">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2004</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2004-plugin-system"><?php _e( 'Plugin System Introduced', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2004-ms-mingus">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2004-mingus"><?php _e( 'First Update: v1.2 “Mingus” Released', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2004</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2004-ms-hello-dolly">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2004</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2004-hello-dolly"><?php _e( 'First Plugin: Hello, Dolly<em>!</em>', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2004-ms-i18n">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2004-i18n"><?php _e( 'Internationalization (i18n)', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2004</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2005-ms-plugin-repo">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2005</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2005-plugin-repo"><?php _e( 'Plugin Repository Launched', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2005-ms-wp-hackers">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2005-wp-hackers"><?php _e( 'wp-hackers Mailing List', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2005</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2005-ms-theme-system">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2005</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2005-theme-system"><?php _e( 'Theme System Introduced', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2005-ms-100k-downloads">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2005-100k-downloads"><?php _e( '100,000 Downloads', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2005</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2005-ms-logo">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2005</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2005-logo"><?php _e( 'WordPress Logo Created', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2005-ms-wordpress.com">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2005-wordpress.com"><?php _e( 'WordPress.com Launched', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2005</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2006-ms-wordcamp">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2006</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2006-wordcamp"><?php _e( 'First WordCamp (Held in San Francisco)', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2007-ms-import-export">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2007-import-export"><?php _e( 'Import &amp; Export Functionality', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2007</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2008-ms-coltrane">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2008</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2008-coltrane"><?php _e( 'New Admin User Interface Design', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2009-ms-easier-video-embeds">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2009-easier-video-embeds"><?php _e( 'Easier Video Embeds', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2009</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2010-ms-foundation">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2010</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2010-foundation"><?php _e( 'WordPress Foundation Created', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2010-ms-first-kids-camp">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2010-first-kids-camp"><?php _e( 'First Kids Camp', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2010</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2012-ms-community-summit">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2012</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2012-community-summit"><?php _e( 'First WordPress Community Summit', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2013-ms-wordcamp-europe">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2013-wordcamp-europe"><?php _e( 'First WordCamp Europe', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2013</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2013-ms-mp6-design">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2014</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2013-mp6-design"><?php _e( 'MP6 Design &amp; Flat Icons', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2014-ms-improved-visual-editing">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2014-improved-visual-editing"><?php _e( 'Improved Visual Editing', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2014</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2014-ms-more-visual-editing-enhancements">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2014</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2014-more-visual-editing-enhancements"><?php _e( 'More Visual Editing Enhancements', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2015-ms-1b-plugin-downloads">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2015-1b-plugin-downloads"><?php _e( 'One Billion Plugin Downloads', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2015</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2015-ms-25-percent-of-web">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2015</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2015-25-percent-of-web"><?php _e( 'Powering More Than 25% Of The Web', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2015-ms-first-wordcamp-us">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2015-first-wordcamp-us"><?php _e( 'Inaugural WordCamp US', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2015</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2015-ms-kim-parsell">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2015</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2015-kim-parsell"><?php _e( 'Kim Parsell Memorial Scholarship', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2016-ms-rest-api-content-endpoints">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2016-rest-api-content-endpoints"><?php _e( 'REST API Content Endpoints', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2016</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2017-ms-gutenberg-begins">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2017</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2017-gutenberg-begins"><?php _e( 'Gutenberg Project', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2018-ms-gutenberg-new-default-editor">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2018-gutenberg-new-default-editor"><?php _e( 'Gutenberg Becomes Default Editor', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2018</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2019-ms-leadership-expansion">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2019</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2019-leadership-expansion"><?php _e( 'Leadership Expansion', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2019-ms-honoring-alex-mills">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2019-honoring-alex-mills"><?php _e( 'Moment of Silence to Honor Alex Mills', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2019</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2019-ms-state-of-word-gutenberg">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2019</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2019-state-of-word-gutenberg"><?php _e( 'State of The Word, in Gutenberg', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2020-ms-block-directory">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2020-block-directory"><?php _e( 'Block Directory Support', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2020</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2020-ms-all-women-and-non-binary-release-squad">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2020</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2020-all-women-and-non-binary-release-squad"><?php _e( 'First All Women &amp; Non-Binary Release Squad', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2021-ms-tt1-blocks-theme">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2021-tt1-blocks-theme"><?php _e( 'TT1 Blocks Theme Released', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2021</div>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2021-ms-100th-gutenberg-release">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2021</div>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2021-100th-gutenberg-release"><?php _e( '100th Release of Gutenberg', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
							</div>
						</div>
						<div class="timeline-content" id="2021-ms-40-percent-of-web">
							<div class="ctl-row">
								<div class="ctl-col-6">
									<div class="story-details">
										<h3><a href="#2021-40-percent-of-web"><?php _e( 'WordPress Powers 40% of The Web<em>!</em>', 'wporg' ); ?><em>+</em></a></h3>
									</div>
								</div>
								<div class="ctl-col-6">
									<div class="story-time">
										<div>2021</div>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>

				<h2 class="has-text-align-center _40p-timeline-percent has-text-color" style="color:#d13638;font-size:128px;line-height:1"><?php _e( '40%', 'wporg' ); ?></h2>
				<p class="has-text-align-center has-primary-color has-text-color"><?php _e( 'Sixty percent of the web to go!', 'wporg' ); ?></p>

				<div class="_40p-milestones-details">
					<h2 class="_40p-milestones-header"><?php _e( 'Key Milestones', 'wporg' ); ?></h2>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2003-wordpress-born" href="#top">⌃<span class="screen-reader-text"><?php _e( 'Back to top.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/13/hello-world/"><?php _e( 'WordPress Is Born', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'After discussions with <a href="%1$s">Mike Little</a>, <a href="%2$s">Matt Mullenweg</a> created <a href="%3$s">a new branch of b2</a> on SourceForge, and, with the name coined by his friend <a href="%4$s">Christine Tremoulet</a>, called it WordPress. WordPress.org launched May 27. Initially, it was home to the development blog, some schematic documentation, and support forums. The <a href="%5$s">original WordPress homepage</a> told the world that “WordPress is a semantic personal publishing platform with a focus on aesthetics, web standards, and usability.” The site gave the WordPress community a presence and the forums provided a home.', 'wporg' ),
							'https://mikelittle.org/',
							'https://ma.tt/about/',
							'https://milestonesbook.wordpress.com/2015/11/13/hello-world/',
							'https://christinetremoulet.com/site/about/',
							'http://web.archive.org/web/20030618021947/http://wordpress.org/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2003-first-release" href="#2003-ms-wordpress-born">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2003/05/wordpress-now-available/"><?php _e( 'First WordPress Release', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'On May 27th, 2003, <a href="%s">the first version of WordPress, WordPress 0.7, was released</a>. Users who switched from b2 to WordPress got some new features, most notably the new, simplified administration panel and the WordPress Links Manager, which allowed users to create a blogroll. Once WordPress 0.7 shipped, there was an effort to get other developers involved in the project, starting with Donncha Ó Caoimh and François Planque, both of whom had created their own b2 forks.', 'wporg' ),
							'http://wordpress.org/news/2003/05/wordpress-now-available/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2003-great-renaming" href="#2003-ms-2003-first-release">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2003/12/the-great-renaming/"><?php _e( 'The Great Renaming', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In late 2003, major changes to the file structure involved replacing “b2” files with “wp-”, dubbed <a href="%s">The Great Renaming</a>. The WordPress file structure morphed from b2 to the familiar file structure used today, with many files consolidated into the wp-includes and wp-admin folders.', 'wporg' ),
							'http://wordpress.org/news/2003/12/the-great-renaming/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2004-six-apart-doubled-downloads" href="#2003-ms-great-renaming">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/20/freedom-zero/"><?php _e( 'Six Apart, Doubled Downloads', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'On May 13th, 2004, Six Apart, the company behind Movable Type, <a href="%1$s">announced changes to Movable Type’s license</a>. Movable Type 3.0, the newest version, came with licensing restrictions, which meant that users not only had to pay for software that was previously free but pay for each additional software installation. Six Apart’s move galvanized the WordPress community. It helped grow the WordPress platform. <a href="%2$s">WordPress downloads on SourceForge more than doubled</a>, increasing from 8,670 in April, 2004, to 19,400 in May.', 'wporg' ),
							'http://web.archive.org/web/20040605225637/http://www.sixapart.com/corner/archives/2004/05/movable_type_de.shtml',
							'http://sourceforge.net/projects/cafelog/files/WordPress/stats/timeline?dates=2003-04-01+to+2005-04-01'
						);
						?>
					</p>
					<p><?php _e( 'The decision to fork b2, not to rewrite the platform was prescient: if the community had buried itself in a rewrite, it wouldn’t have been ready to welcome and support all of the new WordPress users. Instead, they were ready. For weeks, everyone was focused on helping “switchers”. Developers wrote scripts to help people easily migrate from Movable Type to WordPress.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2004-plugin-system" href="#2004-ms-six-apart-doubled-downloads">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://core.trac.wordpress.org/changeset/1008"><?php _e( 'Plugin System Introduced', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In March 2004, the <a href="%1$s">plugin system</a> transformed WordPress for core developers and the wider community. It meant that the core product didn’t need to include every developer’s pet feature, just the features that made sense for a majority of users. Ryan Boren <a href="%2$s">stated</a> that the plugin system enabled core developers to implement the 80/20 rule: “Is this useful to 80%% of our users? If not, try it in a plugin.”', 'wporg' ),
							'http://core.trac.wordpress.org/changeset/1008',
							'http://archive.wordpress.org/interviews/2013_05_15_Boren1.html#L65'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2004-mingus" href="#2004-ms-plugin-system">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2004/05/heres-the-beef/"><?php _e( 'First Update: v1.2 “Mingus” Released', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'May 2004 was the month <a href="%1$s">WordPress 1.2 “Mingus”</a> launched, making WordPress much more accessible and available to a wider group of people. Major WordPress releases are named in honor of jazz musicians. <a href="%2$s">Charles Mingus</a> was a highly influential American jazz double bassist, composer and bandleader.', 'wporg' ),
							'https://wordpress.org/news/2004/05/heres-the-beef/',
							'https://en.wikipedia.org/wiki/Charles_Mingus'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2004-hello-dolly" href="#2004-ms-mingus">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/plugins/hello-dolly/"><?php _e( 'First Plugin: Hello, Dolly<em>!</em>', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'Also in May 2004, the first plugin, which is still bundled with WordPress — the <a href="%s">Hello Dolly plugin</a>, randomly displays a lyric from the Louis Armstrong song <em>Hello, Dolly!</em> in the top right of the admin dashboard was launched. It was intended as a guide for developers interested in making plugins, and for users learning how to activate or deactivate plugins.', 'wporg' ),
							'https://core.trac.wordpress.org/changeset/1340'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2004-i18n" href="#2004-ms-hello-dolly">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2003/05/wordpress-now-available/"><?php _e( 'Internationalization (i18n)', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'To internationalize WordPress in 2004, Ryan Boren <a href="%s">wrapped</a> translatable strings with the <code>__()</code> translation function. He went through the code, one line at a time, found everything that could be translated, and marked it up. This meant that when WordPress v1.2 was released, it not only contained the plugin API but was fully internationalized.', 'wporg' ),
							'https://core.trac.wordpress.org/changeset/1106'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2005-plugin-repo" href="#2004-ms-i18n">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2005/01/the-wordpress-plugin-repository/"><?php _e( 'Plugin Repository Launched', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'The <a href="%1$s">WordPress Plugin Repository</a> launched in January 2005. Hosted at <a href="%2$s">dev.wp-plugins.org</a>, and powered by subversion and trac, it’s quite different from the user-friendly plugin directory that we’re used to today. Literally, the plugin repository was just a code repository.', 'wporg' ),
							'http://wordpress.org/news/2005/01/the-wordpress-plugin-repository/',
							'http://dev.wp-plugins.org'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2005-wp-hackers" href="#2005-ms-plugin-repo">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="http://lists.wordpress.org/pipermail/wp-hackers/"><?php _e( 'wp-hackers Mailing List', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'The first mailing list in the project, however, wasn’t wp-hackers, but wp-docs, which was <a href="%1$s">set up in November 2003</a> to discuss the WordPress documentation and wiki. It was active for six months before the <a href="%2$s">hackers mailing list</a> was set up in June 2004. This later moved to <a href="%3$s">wp-hackers</a> in 2005. Development discussion shifted from the forums to the mailing list, leaving the forums as a place to provide support.', 'wporg' ),
							'https://web.archive.org/web/20090107221645/http://comox.textdrive.com/pipermail/docs/2003-November/000000.html',
							'http://lists.wordpress.org/pipermail/hackers/',
							'http://lists.wordpress.org/pipermail/wp-hackers/'
						);
						?>
					</p>
					<p>
						<?php
						printf(
							__( 'The wp-hackers mailing list exploded with activity, busy with heated discussions about issues such as <a href="%1$s">whether comment links should be nofollow</a> to discourage spammers, <a href="%2$s">the best way to format the date</a>, and <a href="%3$s">how to start translating WordPress</a>. Developers finally had a place to congregate. They embraced the new communication platform — their new home in the project.', 'wporg' ),
							'http://plugins.lists.wordpress.org/pipermail/hackers/2005-January/003617.html',
							'http://lists.wordpress.org/pipermail/hackers/2004-August/001335.html',
							'http://lists.wordpress.org/pipermail/hackers/2004-December/003462.html'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2005-theme-system" href="#2005-ms-wp-hackers">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/20/themes/"><?php _e( 'Theme System Introduced', 'wporg' ); ?></a></h3>
					<p><?php _e( 'In February 2005, the theme system was built using PHP, which is a templating language itself, after all. The theme system breaks a theme down into its component parts — header, footer, and sidebar, for example. Each part is an individual file that a designer can customize. A native WordPress theme system, as opposed to a templating system such as Smarty, meant that designers could design and build themes without learning an entirely new syntax.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2005-100k-downloads" href="#2005-ms-theme-system">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/20/wordpress-incorporated/"><?php _e( '100,000 Downloads', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'At the start of March 2005, <a href="%1$s">WordPress v1.5 “Strayhorn”</a> had seen <a href="%2$s">50,000 downloads</a>. Just three weeks later, the number doubled to 100,000. To celebrate the landmark, there was a 100k party in San Francisco. On March 22nd, a group of WordPressers got together at the Odeon Bar in San Francisco.', 'wporg' ),
							'https://wordpress.org/news/2005/02/strayhorn/',
							'http://wordpress.org/news/2005/03/fifty-thousand/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2005-logo" href="#2005-ms-100k-downloads">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/20/a-new-logo/"><?php _e( 'WordPress Logo Created', 'wporg' ); ?></a></h3>
					<p><?php _e( 'The logo was finally decided on May 15th, 2005 when Matt sent an email to the mailing list with the subject <em>“I think this is it”</em>. Matt’s message contained just this one beautiful image:', 'wporg' ); ?></p>

					<div class="wp-block-image _40p-timeline-wp-logo-created-img is-style-default">
						<figure class="aligncenter size-full is-resized">
							<img loading="lazy" src="<?php echo esc_url( get_stylesheet_directory_uri() . '/images/40p-wp-logo-proposal.png' ); ?>" alt="2005 WordPress.org Logo Proposal" class="wp-image-637" width="314" height="115">
							<figcaption><?php _e( 'By Jason Santa Maria, 2005', 'wporg' ); ?></figcaption>
						</figure>
					</div>
					<div style="height:23px" aria-hidden="true" class="wp-block-spacer"></div>
					<p>
						<?php
						printf(
							__( 'The creation of a mark, created by <a href="%s">Jason Santa Maria</a>, gave WordPress a stand-alone element of the logo which, over time, would be recognizable even without the word beside it. This could and would be used in icons, branding, and t-shirts. It’s become instantly recognizable, helped by its appearance on WordCamp t-shirts the world over.', 'wporg' ),
							'https://v4.jasonsantamaria.com/portfolio/'
						);
						?>
					</p>
					<p>
						<?php
						printf(
							__( '<em>* For the latest official WordPress logos, <a href="%s">click here</a>.</em>', 'wporg' ),
							'https://wordpress.org/about/logos/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2005-wordpress.com" href="#2005-ms-logo">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/21/wordpress-com/"><?php _e( 'WordPress.com', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'WordPress.com opened to signups in August 2005, <a href="%1$s">by invitation only</a>, to control user growth on untested servers. Many who were involved with the WordPress project got WordPress.com blogs, including <a href="%2$s">Lorelle VanFossen</a> and Mark Riley. Every new WordPress.com member also got one invite to share.', 'wporg' ),
							'http://matt.wordpress.com/2005/08/15/invites/',
							'http://lorelle.wordpress.com/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2006-wordcamp" href="#2005-ms-wordpress.com">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://2006.sf.wordcamp.org/"><?php _e( 'First WordCamp (Held in San Francisco)', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In July 2006, Matt Mullenweg announced that he would host a BarCamp-style event called <a href="%1$s">“WordCamp”</a> later that summer in San Francisco. “BarCamp-style” was a code phrase for ‘last minute,’ <a href="%1$s">he joked</a>.', 'wporg' ),
							'http://ma.tt/2006/07/wordcamp/'
						);
						?>
					</p>
					<p><?php _e( 'The event — which he announced without a venue or schedule — would be on August 5th. More than 500 people from all over the world registered: Donncha Ó Caoimh flew in from Ireland, and Mark Riley from the UK. When WordCamp did get a venue, it was the Swedish American Hall, a Market Street house that served as headquarters for the Swedish Society of San Francisco.', 'wporg' ); ?></p>
					<p>
						<?php
						printf(
							__( '<a href="%1$s">WordCamp 2006’s schedule</a> reflects the project’s concerns and its contributors’ passions. Mark Riley gave the first-ever workshop on getting involved with the WordPress community, now a staple talk at WordCamps. Andy Skelton presented on the widgets feature that he was working on for WordPress.com. Donncha spoke about WPMU, and Mark Jaquith explored <a href="%2$s">WordPress as a CMS</a>, <a href="%3$s">one of the most-requested sessions</a>. There were presentations about blogging and podcasting, and about journalism and monetizing.', 'wporg' ),
							'http://2006.wordcamp.org/schedule/',
							'http://markjaquith.com/wordcamp/wordpress-versatility/',
							'http://markjaquith.wordpress.com/2006/08/30/wordcamp-thoughts-late-to-the-game/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2007-import-export" href="#2006-ms-wordcamp">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2007/01/ella-21/"><?php _e( 'Import &amp; Export Functionality', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'With the release of <a href="%s">WordPress v2.1 “Ella”</a> in 2007, lossless XML import and export functionality made it easy to move content seamlessly between WordPress blogs. Also, it came with features like a new tabbed editor to switch between WYSIWYG and code editing mode while writing a post. Better internationalization and support for right-to-left languages. A new upload manager made it easier to manage pictures, video, and audio. It brought much cleaner code, and more.', 'wporg' ),
							'https://wordpress.org/news/2007/01/ella-21/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2008-coltrane" href="#2007-ms-import-export">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2008/12/coltrane/"><?php _e( 'New Admin User Interface Design', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'With <a href="%s">WordPress v2.7 “Coltrane”</a> in 2008, the admin user interface changed drastically. When screenshots of the changes appeared on community blogs, the inevitable question was “why are they changing it again?” WordPress v2.5’s design hadn’t quite settled in before another huge change came about with the implementation in v2.7.', 'wporg' ),
							'https://wordpress.org/news/2008/12/coltrane/'
						);
						?>
					</p>
					<p>
						<?php
						printf(
							__( 'The change meant that users of varying skill levels needed to relearn WordPress. The growing WordPress tutorial community would need to retake every screenshot and reshoot every video. However, when WordPress users upgraded, the <a href="%s">feedback was positive</a>. Users loved the new interface. They found it intuitive and easy to use — finally demonstrating that it wasn’t change they had been unhappy with just nine months earlier — but the interface itself.', 'wporg' ),
							'http://lorelle.wordpress.com/2008/12/10/wordpress-27-available-now/#comments'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2009-easier-video-embeds" href="#2008-ms-coltrane">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2009/12/wordpress-2-9/"><?php _e( 'Easier Video Embeds', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'With <a href="%s">WordPress v2.9 “Carmen”</a> in 2009, you could just paste a URL on its own line and have it magically turn it into the proper embed code, with Oembed support for YouTube, Daily Motion, Blip.tv, Flickr, Hulu, Viddler, Qik, Revision3, Scribd, Google Video, Photobucket, PollDaddy, WordPress.tv, and more would follow in future releases.', 'wporg' ),
							'https://wordpress.org/news/2009/12/wordpress-2-9/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2010-foundation" href="#2009-ms-easier-video-embeds">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/25/the-wordpress-foundation/"><?php _e( 'WordPress Foundation Created', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'The WordPress Foundation was launched in January 2010. Automattic transferred <a href="%s">the trademarks</a> later that year in September. As part of the transfer, Automattic was granted use of WordPress for WordPress.com, but not for any future domains. Matt was granted a license for WordPress.org and WordPress.net. As well as transferring the trademarks for WordPress itself, the company also transferred the WordCamp name. As with WordPress itself, this protects WordCamps as non-profit, educational events in perpetuity.', 'wporg' ),
							'http://ma.tt/2010/09/wordpress-trademark/'
						);
						?>
					</p>
					<p>
						<?php
						printf(
							__( 'The <a href="%s">community was pleased</a> with decoupling WordPress the project from Automattic the company. It gave people more confidence that Automattic was not out to dominate the WordPress commercial ecosystem.', 'wporg' ),
							'http://ma.tt/2010/09/wordpress-trademark/#comments'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2010-first-kids-camp" href="#2010-ms-foundation">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><?php _e( 'First “Kids Camp” at WordCamp', 'wporg' ); ?></h3>
					<p>
						<?php
						printf(
							__( 'WordCamp Ireland, organized by <a href="%1$s">Sabrina Kent</a> and <a href="%2$s">Katherine Nolan</a>, was the first WordPress event <a href="%3$s">to offer activities for kids ages 3-12</a>. Krishna De might be the first person to coin the term “<a href="%4$s">Kids Camp</a>.” More events for kids followed, as outlined by <a href="%5$s">this list</a>, which is current through 2019.', 'wporg' ),
							'http://www.sabrinadent.com/',
							'https://twitter.com/dochara',
							'https://twitter.com/WordCampIRL/status/10021454195',
							'https://www.krishna.me/wordcamp-ireland-in-kilkenny-has-its-own-kids-camp/',
							'https://heropress.com/essays/history-and-future-of-kids-heroes-in-wordpress/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2012-community-summit" href="#2010-ms-first-kids-camp">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/25/the-community-summit/"><?php _e( 'First WordPress Community Summit', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'The first en-masse, invitation-only WordPress community get-together — <a href="%s">The Community Summit</a> — took place in 2012. The Community Summit focused on issues facing WordPress software development and the wider WordPress community. Community members nominated themselves and others to receive an invitation; a team of 18 people reviewed and voted on who would be invited. The attendees — active contributors, bloggers, plugin and theme developers, and business owners from across the WordPress community — came to Tybee Island, Georgia, to talk about WordPress.', 'wporg' ),
							'https://wordpress.org/news/2012/05/calling-all-contributors-community-summit-2012/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2013-wordcamp-europe" href="#2012-ms-community-summit">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://europe.wordcamp.org/2013/about/"><?php _e( 'First WordCamp Europe', 'wporg' ); ?></a></h3>
					<p><?php _e( 'WordCamp Europe, in 2013, was the first large-scale WordCamp to be held in Europe. By large-scale, we mean big. And by big, we mean awesome. This was a chance for the European WordPress community to gather together in the idyllic town of Leiden to geek-out, share experiences, do business, and most of all, talk WordPress.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2013-mp6-design" href="#2013-ms-wordcamp-europe">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://milestonesbook.wordpress.com/2015/11/25/mp6/"><?php _e( 'MP6 Design &amp; Flat Icons', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In January 2013, <a href="%s">Ben Dunkle proposed new, flat icons</a>. The WordPress admin was outdated, particularly on retina displays where the icons were pixelated. Flat icons would scale properly and also allow designers to color icons using CSS. So the MP6 design project began to address icons and other improvements. Work took place in a plugin hosted by the WordPress plugin directory. Anyone could install the plugin and see the changes in their admin. Every week, the group shared a release and a report that was open to public feedback.', 'wporg' ),
							'https://core.trac.wordpress.org/ticket/23333'
						);
						?>
					</p>
					<p>
						<?php
						printf(
							__( 'The MP6 plugin merged with <a href="%s">WordPress v3.8 “Parker”</a>, released in December 2013, demonstrating that, while it may take a while to get there, harmonious design in a free software project is possible.', 'wporg' ),
							'https://wordpress.org/news/2013/12/parker/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2014-improved-visual-editing" href="#2013-ms-mp6-design">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2014/04/smith/"><?php _e( 'Improved Visual Editing', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'With <a href="%s">WordPress v3.9 “Smith”</a> in 2014, updates to the visual editor improved speed, accessibility, and mobile support. You could now paste into the visual editor from your word processor without wasting time to clean up messy styling. With quicker access to crop and rotation tools, it was now much easier to edit images <em>while</em> editing posts. Also, it became possible to scale images directly in the editor, and galleries began to display a beautiful grid of images right in the editor, just like in a published post.', 'wporg' ),
							'https://wordpress.org/news/2014/04/smith/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2014-more-visual-editing-enhancements" href="#2014-ms-improved-visual-editing">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2014/04/smith/"><?php _e( 'More Visual Editing Enhancements', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'With <a href="%s">WordPress v4.0 “Benny”</a> in 2014, it became possible to explore uploads in a beautiful, endless grid. A new details preview made viewing and editing any amount of media in sequence a snap. Embedding became a visual experience, showing a true preview of embedded content (such as YouTube videos) saving time and adding confidence. Writing and editing became even smoother and more immersive. The editor would now expand to fit content as you write, and the formatting tools were now available at all times.', 'wporg' ),
							'https://wordpress.org/news/2014/09/benny/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2015-1b-plugin-downloads" href="#2014-ms-more-visual-editing-enhancements">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wptavern.com/wordpress-plugin-directory-surpasses-one-billion-total-downloads"><?php _e( 'One Billion Plugin Downloads', 'wporg' ); ?></a></h3>
					<p><?php _e( 'As WordPress’ market share continued to grow, so did the amount of downloads from the plugin directory. WordPress was capable of building almost any type of website you could think of, and there were many smart people who jumped on board to build plugins, both free and premium. It was largely this rise of the WordPress entrepreneur that sent the download count over 1 billion in August, 2015.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2015-25-percent-of-web" href="#2015-ms-1b-plugin-downloads">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://martechtoday.com/wordpress-used-on-25-percent-of-all-websites-report-151115"><?php _e( 'More Than 25% Of The Web', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'W3Techs.com broke the Internet down and divided it by each content management system in 2015. WordPress <a href="%1$s">far exceeded</a> number two on the list, which was Joomla at just 2.8 percent. Matt Mullenweg <a href="%2$s">wrote</a>, <em>“The big opportunity is still the 57%% of websites that don’t use any identifiable CMS yet, and that’s where I think there is still a ton of growth for us (and I’m also rooting for all the other open source CMSes).”.</em> He also <a href="%3$s">tweeted</a> just these few words, <em>“Seventy-Five to Go”.</em>', 'wporg' ),
							'https://w3techs.com/blog/entry/wordpress-powers-25-percent-of-all-websites',
							'https://ma.tt/2015/11/seventy-five-to-go/',
							'https://twitter.com/photomatt/status/663344236234846208'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2015-first-wordcamp-us" href="#2015-ms-25-percent-of-web">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://us.wordcamp.org/2015/"><?php _e( 'Inaugural WordCamp US', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'It was, <a href="%1$s">at the time</a> (<a href="%2$s">2015</a>), the largest WordCamp ever in the world, and the very first national WordCamp US event. The Pennsylvania Convention Center opened its doors to more than 1,800 WordPress bloggers, designers, developers, and many others. Philadelphia Councilman David Oh <a href="%2$s">declared</a> December 5th ‘WordPress Day’ during the State of the Word address by Matt Mullenweg.', 'wporg' ),
							'https://ma.tt/2015/07/wcus-philadelphia/',
							'https://us.wordcamp.org/2015/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2015-kim-parsell" href="#2015-ms-first-wordcamp-us">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpressfoundation.org/projects/kim-parsell-memorial-scholarship/"><?php _e( 'Kim Parsell Memorial Scholarship', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In memory of <a href="%s">Kim Parsell</a>, in 2015 the WordPress Foundation created a scholarship to provide annual funding for a woman of any assigned sex, who contributes to WordPress, to attend WordCamp US.', 'wporg' ),
							'https://wordpress.org/news/2019/11/people-of-wordpress-kim-parsell/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2016-rest-api-content-endpoints" href="#2015-ms-kim-parsell">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2016/12/vaughan/"><?php _e( 'REST API Content Endpoints', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'With the release of <a href="%s">WordPress v4.7 “Vaughan”</a> in 2016, REST API endpoints were added for posts, comments, terms, users, meta, and settings. Content endpoints provided machine-readable external access to a WordPress site with a clear, standards-driven interface, paving the way for new and innovative methods of interacting with sites through plugins, themes, apps, and more.', 'wporg' ),
							'https://wordpress.org/news/2016/12/vaughan/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2017-gutenberg-begins" href="#2016-ms-rest-api-content-endpoints">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://make.wordpress.org/core/2017/06/23/whats-new-in-gutenberg-june-23rd/"><?php _e( 'Gutenberg Project Begins', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'Matt Mullenweg announced his plans to overhaul the editor during his <a href="%1$s">State of the Word address in 2016</a>. More specifically, he talked about creating a “block-based editor” and reiterated that it was important to continue learning JavaScript deeply. Not long after, the project was given a name, Gutenberg. The <a href="%2$s">first commit</a> occurred Feb 3, 2017 and the <a href="%3$s">first release</a> as a WordPress plugin in June 2017. Initially, Gutenberg was met with a bit of controversy, but the entire WordPress community rallied around the project to help ensure its success.', 'wporg' ),
							'https://ma.tt/2016/12/state-of-the-word-2016/',
							'https://github.com/WordPress/gutenberg/commit/0fcff2947b97635dc43bfb7e2a29c91d08ba4f04',
							'https://make.wordpress.org/core/2017/06/23/whats-new-in-gutenberg-june-23rd/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2018-gutenberg-new-default-editor" href="#2017-ms-gutenberg-begins">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2018/12/bebo/"><?php _e( 'Gutenberg Becomes Default Editor', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'Big upgrades to the editor. With the release of <a href="%s">WordPress v5.0 “Bebo”</a> in 2018, Gutenberg became the default editing experience. The new block-based editor was the first step toward an exciting new future. Whether you were building your first site, revamping your blog, or writing code for a living; Gutenberg offered more content flexibility.', 'wporg' ),
							'https://wordpress.org/news/2018/12/bebo/'
						);
						?>
					</p>
					<p><?php _e( 'For developers building client sites, you could now create reusable blocks, letting your clients add new content anytime, while still maintaining a consistent look and feel. A wide collection of APIs and interface components made it easy to create blocks with intuitive controls. Utilizing these components not only sped up development work but also provided a more consistent, usable, and accessible interface.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2019-leadership-expansion" href="#2018-ms-gutenberg-new-default-editor">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://make.wordpress.org/updates/2019/01/16/expanding-wordpress-leadership/"><?php _e( 'Leadership Expansion', 'wporg' ); ?></a></h3>
					<p><?php _e( 'WordPress leadership was expanded in 2019 to help lead the project more efficiently. Josepha Haden Chomphosy was named Executive Director and took on day-to-day operations of the project as well as support of contributor teams.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2019-honoring-alex-mills" href="#2019-ms-leadership-expansion">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://poststatus.com/matt-mullenweg-state-of-the-word-2019/"><?php _e( 'Honoring Alex Mills', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In 2019, Matt Mullenweg held a moment of silence for long-time community member <a href="%s">Alex Mills (viper007bond)</a> who passed away after a long-fought battle with leukemia. Alex was a very kind, creative, and prolific WordPress contributor.', 'wporg' ),
							'https://alex.blog/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2019-state-of-word-gutenberg" href="#2019-ms-honoring-alex-mills">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://ma.tt/2019/11/state-of-the-word-2019/"><?php _e( 'State of The Word, in Gutenberg', 'wporg' ); ?></a></h3>
					<p><?php _e( 'In 2019, Matt Mullenweg’s annual State of The Word presentation was beautifully written and designed completely in Gutenberg for the first time ever.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2020-block-directory" href="#2019-ms-state-of-word-gutenberg">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2020/08/eckstine/"><?php _e( 'Block Directory Support', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'With <a href="%s">WordPress v5.5 “Eckstine”</a> in 2020,  it became easier than ever to find the block you need. The new block directory was now built right into the block editor, making it possible to install new block types without ever leaving the editor.', 'wporg' ),
							'https://wordpress.org/news/2020/08/eckstine/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2020-all-women-and-non-binary-release-squad" href="#2020-ms-block-directory">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2020/12/simone/"><?php _e( 'All Women &amp; Non-Binary Release Squad', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In 2020, the <a href="%s">WordPress 5.6 “Simone”</a> release came from the first all-women and non-binary identifying release squad. WordPress 5.6 brought countless ways to set ideas free and bring them to life.', 'wporg' ),
							'https://wordpress.org/news/2020/12/simone/'
						);
						?>
					</p>
					<p><?php _e( 'With a brand-new default theme as a canvas, and support for an ever-growing collection of blocks as brushes, it became possible to paint with words, pictures, sound, or rich embedded media.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2021-tt1-blocks-theme" href="#2020-ms-all-women-and-non-binary-release-squad">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/themes/tt1-blocks/"><?php _e( 'TT1 Blocks Theme Released', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'In 2021, the <a href="%s">TT1 Blocks theme</a> was released as an experimental block-based version of the Twenty Twenty-One theme. It’s been created to leverage full-site editing functionality that is being built in the Gutenberg plugin. Not meant for use on a production site yet.', 'wporg' ),
							'https://wordpress.org/themes/tt1-blocks/'
						);
						?>
					</p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2021-100th-gutenberg-release" href="#2021-ms-tt1-blocks-theme">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2021/02/reflecting-on-gutenbergs-100th-release/"><?php _e( '100th Release of Gutenberg', 'wporg' ); ?></a></h3>
					<p><?php _e( 'February 17th, 2021 marked the 100th release of Gutenberg, and while that looks remarkable on the outside, the release itself holds what all the other releases did. It holds improvements to existing features, it fixes bugs that users reported, it adds new features, and it highlights experiments with new ideas. What is remarkable about the release is the people. The ones who were with us from the start, the ones who were with us but left, the ones who joined in our journey, everyone who helped along the way, everyone who provided feedback, everyone who got their hands dirty, and everyone who has used this editor, tried to extend it, and provided ideas.', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a id="2021-40-percent-of-web" href="#2021-ms-100th-gutenberg-release">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
					<h3><a href="https://wordpress.org/news/2021/03/the-month-in-wordpress-february-2021/"><?php _e( 'WordPress Powers 40% of The Web', 'wporg' ); ?></a></h3>
					<p>
						<?php
						printf(
							__( 'Also in February 2021, <a href="%s">W3Techs.com reported</a> the WordPress software now powers 40%% of the top 10 million websites in the world! Every two minutes, a new website using WordPress says, “Hello world”!', 'wporg' ),
							'https://w3techs.com/blog/entry/40_percent_of_the_web_uses_wordpress'
						);
						?>
					</p>
					<p><?php _e( 'For the top 1000 sites, the market share is even higher at 51.8%. Over the past 10 years, the growth rate has increased, which is reflected by the fact that 66.2% of all new websites use WordPress!', 'wporg' ); ?></p>

					<p class="has-text-align-center _40p-milestones-back-link"><a href="#2021-ms-40-percent-of-web">⌃<span class="screen-reader-text"><?php _e( 'Back to milestone marker.', 'wporg' ); ?></span></a></p>
				</div>

				<p class="has-text-align-center has-link-color" style="--wp--style--color--link:#d13638">
					<?php _e( 'Forty percent and counting…', 'wporg'); ?><br>
					<a href="https://wordpress.org/"><?php _e( 'Get started with WordPress', 'wporg'); ?></a>
				</p>

				<p class="has-text-align-center _40p-social" style="line-height:5">
					<a href="https://twitter.com/wordpress" style="padding-right: 40px;">
						<svg width="39" height="33" viewBox="0 0 39 33" fill="#000" xmlns="http://www.w3.org/2000/svg"><title>Twitter</title><path d="M12.0965 32.1647C26.6277 32.1647 34.579 20.1145 34.579 9.68221C34.579 9.3432 34.579 9.00419 34.5636 8.66518C36.1046 7.55569 37.4452 6.15342 38.5085 4.56624C37.0908 5.19803 35.5652 5.61409 33.9626 5.81441C35.5961 4.84361 36.8442 3.28725 37.4452 1.4381C35.9197 2.34726 34.2246 2.99447 32.4217 3.34889C30.9732 1.80793 28.9237 0.852539 26.6585 0.852539C22.2976 0.852539 18.7534 4.39673 18.7534 8.75764C18.7534 9.37402 18.8305 9.97499 18.9537 10.5606C12.3893 10.237 6.56447 7.078 2.66585 2.30104C1.98783 3.47216 1.60259 4.8282 1.60259 6.2767C1.60259 9.0196 3.00486 11.4389 5.11597 12.8566C3.82157 12.8103 2.60421 12.4559 1.54095 11.8704C1.54095 11.9012 1.54095 11.932 1.54095 11.9782C1.54095 15.7998 4.26844 19.005 7.87428 19.7292C7.21167 19.9141 6.51824 20.0066 5.79399 20.0066C5.28547 20.0066 4.79237 19.9604 4.31467 19.8679C5.31629 23.0115 8.24411 25.2921 11.6958 25.3537C8.98377 27.4802 5.57826 28.7438 1.87997 28.7438C1.24817 28.7438 0.616382 28.713 0 28.636C3.48256 30.8549 7.64314 32.1647 12.0965 32.1647Z"></path></svg>
						@wordpress
					</a>
					<a href="https://www.facebook.com/WordPress/">
						<svg width="37" height="37" viewBox="0 0 37 37" fill="#000" xmlns="http://www.w3.org/2000/svg"><title>Facebook</title><path d="M36.6005 18.4117C36.6005 8.24212 28.4083 0 18.3002 0C8.19222 0 0 8.24212 0 18.4117C0 27.6032 6.69103 35.2196 15.4408 36.6005V23.7339H10.7943V18.4117H15.4408V14.3554C15.4408 9.74167 18.1716 7.19208 22.3535 7.19208C24.3551 7.19208 26.4496 7.55168 26.4496 7.55168V12.0827H24.1406C21.8674 12.0827 21.1597 13.5031 21.1597 14.9595V18.4117H26.2351L25.4238 23.7339H21.1597V36.6005C29.9095 35.2196 36.6005 27.6032 36.6005 18.4117Z"></path></svg>
						WordPress
					</a>
				</p>

				<div style="height:90px" aria-hidden="true" class="wp-block-spacer"></div>

				<svg role="img" aria-hidden="true" focusable="false" class="bp p1" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p2" width="650" height="200" viewBox="0 0 650 200" fill="#D13638" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p3" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p4" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p5" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p6" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p7" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p8" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p9" width="650" height="200" viewBox="0 0 650 200" fill="#D13638" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p10" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p11" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p12" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p13" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p14" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p15" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p16" width="650" height="200" viewBox="0 0 650 200" fill="#D13638" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p17" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p18" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p19" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
				<svg role="img" aria-hidden="true" focusable="false" class="bp p20" width="650" height="200" viewBox="0 0 650 200" fill="#000" xmlns="http://www.w3.org/2000/svg"><rect width="650" height="200" rx="100"></rect></svg>
			</div>

		</article><!-- #post-## -->

	</main><!-- #main -->

	<script type="text/javascript">
		const headings = document.querySelectorAll( '.story-details h3 a' );
		if ( headings ) {
			headings.forEach( function(heading){
				heading.addEventListener( 'mouseover', function( event ){
					event.target.closest('.ctl-row').classList.add('hover');
				} );
				heading.addEventListener( 'mouseleave', function( event ){
					event.target.closest('.ctl-row').classList.remove('hover');
				} );
			} );
		}

		const pattern = [ 0, 1, 4, 5, 8, 9, 12, 13, 16, 17, 20, 21, 24, 25, 28, 29, 32, 33, 36, 37 ];
		const list = document.querySelectorAll( '.entry-content p > a[href^="#"]' );
		const svgs = document.querySelectorAll( 'svg.bp' );
		for ( i = 0; i < pattern.length; i++ ){
			if ( ! list[pattern[i]] || ! svgs[i] ) {
				continue;
			}
			const rect = list[pattern[i]].getBoundingClientRect();
			const height = rect.top + window.scrollY;

			if ( 0 == i % 2 ){
				var rand = Math.floor(Math.random() * (0 - 181 )) + 180;
			} else {
				var rand = Math.floor(Math.random() * (180 - 271 )) + 180;
			}

			svgs[i].style.top = Math.floor(height - 160) + 'px';
			svgs[i].style.transform = 'rotate(' + rand + 'deg)';
		}
	</script>
<?php
get_footer();
