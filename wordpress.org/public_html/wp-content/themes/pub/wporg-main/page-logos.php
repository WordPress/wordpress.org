<?php
/**
 * Template Name: Logos
 *
 * Page template for displaying the Logos and Graphics page.
 *
 * @package WordPressdotorg\MainTheme
 */

namespace WordPressdotorg\MainTheme;

get_header();
the_post();
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header">
				<h1 class="entry-title"><?php _esc_html_e( 'Logos and Graphics', 'wporg' ); ?></h1>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p class="intro">Need an official WordPress logo? Want to show your WordPress pride with a button on your blog? You&rsquo;ve come to the right place.</p>

					<h3 class="graphics">Fight the Fake Logo (Fauxgo)</h3>
					<img class="aligncenter" src="//s.w.org/about/images/logo-comparison.png" width="500" />
					<p>Friends don&#8217;t let friends use the wrong WordPress logo. If you see one of these in the wild, please suggest a change.</p>

					<h3 class="graphics">Real WordPress Logo</h3>

					<p>When you need the official WordPress logo for a web site or publication, please use one of the following. These are the real deal. Please only use logos in accordance with the <a href="http://wordpressfoundation.org/trademark-policy/">WordPress trademark&nbsp;policy</a>.</p>
				</section>

				<section class="all-logos col-12 row gutters">
					<table class="logo col-3" id="logo-stacked">
						<tr><th><img src="//s.w.org/about/images/wordpress-logo-stacked-bg.png" alt="WordPress logo" longdesc="WordPress Logo Stacked" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-stacked-cmyk.pdf">WordPress Logo Stacked PDF <small>Vector, for print</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-stacked-rgb.png">WordPress Logo Stacked PNG <small>Low-res, for web</small></a></td></tr>
					</table>

					<table class="logo col-3" id="logo-hoz">
						<tr><th><img src="//s.w.org/about/images/wordpress-logo-hoz-bg.png" alt="WordPress logo" longdesc="WordPress Logo Horizontal" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-hoz-cmyk.pdf">WordPress Logo PDF <small>Vector, for print</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-hoz-rgb.png">WordPress Logo PNG <small>Low-res, for web</small></a></td></tr>
					</table>

					<table class="logo col-3" id="logo-notext">
						<tr><th><img src="//s.w.org/about/images/wordpress-logo-notext-bg.png" alt="WordPress logo" longdesc="WordPress Logo No text" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-notext-cmyk.pdf">WordPress Logo Notext PDF <small>Vector, for print</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-notext-rgb.png">WordPress Logo Notext PNG <small>Low-res, for web</small></a></td></tr>
					</table>

					<table class="logo col-3" id="logo-textonly">
						<tr><th><img src="//s.w.org/about/images/wordpress-logo-textonly-bg.png" alt="WordPress logo" longdesc="WordPress Logo Text only" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-textonly-cmyk.pdf">WordPress Logo Textonly PDF <small>Vector, for print</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-textonly-rgb.png">WordPress Logo Textonly PNG <small>Low-res, for web</small></a></td></tr>
					</table>

					<table class="logo col-3" id="logo-notext-reverse">
						<tr><th><img src="//s.w.org/about/images/wordpress-logo-simplified-bg.png" alt="WordPress logo" longdesc="WordPress Logo Simplified" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-simplified-cmyk.pdf">WordPress Logo Simplified CMYK <small>Vector, for print</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-simplified-rgb.png">WordPress Logo Simplified PNG <small>Low-res, for web</small></a></td></tr>
					</table>

					<table class="logo col-3" id="logo-codeispoetry">
						<tr><th><img src="//s.w.org/about/images/codeispoetry-bg.png" alt="WordPress motto" longdesc="WordPress Code is Poetry motto" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/logos/codeispoetry-cmyk.pdf">Code is Poetry PDF <small>Vector, for print</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/logos/codeispoetry-rgb.png">Code is Poetry PNG <small>Low-res, for web</small></a></td></tr>
					</table>

					<table class="logo col-3" id="minilogo-32">
						<tr><th><img src="//s.w.org/about/images/logos/wordpress-logo-32-blue.png" alt="WordPress logo" longdesc="WordPress Logo mini blue" style="padding:54px 0" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-32-blue.png">Mini PNG (Blue) <small>32&times;32</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/logos/wordpress-logo-32.png">Mini PNG (Gray) <small>32&times;32</small></a></td></tr>
					</table>

					<table class="logo col-3" id="minilogo-blue">
						<tr><th><img src="//s.w.org/about/images/wpmini-blue.png" alt="WordPress logo" longdesc="WordPress Logo icon blue" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/wpmini-blue.png">Mini PNG (Blue) <small>16&times;16</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/wpmini-grey.png">Mini PNG (Gray) <small>16&times;16</small></a></td></tr>
					</table>
				</section>

				<section class="col-8">
					<h3 class="graphics">WordPress Desktops</h3>

					<p>Show your WordPress love with desktop images for the most popular screen sizes (including the&nbsp;iPhone!)</p>
				</section>

				<section class="all-logos col-12 row gutters">
					<table class="logo col-3" id="wp-light-hi">
						<tr><th><img src="//s.w.org/about/images/wp-light-hi-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Light High Contrast" /></th></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-hi-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-hi-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-hi-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-hi-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-hi-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-hi-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>

					<table class="logo col-3" id="wp-light-lo">
						<tr><th><img src="//s.w.org/about/images/wp-light-lo-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Light Low Contrast" /></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-lo-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-lo-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-lo-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-lo-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-lo-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-light-lo-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>

					<table class="logo col-3" id="wp-dark-hi">
						<tr><th><img src="//s.w.org/about/images/wp-dark-hi-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Dark High Contrast" /></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-hi-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-hi-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-hi-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-hi-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-hi-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-hi-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>

					<table class="logo col-3" id="wp-dark-lo">
						<tr><th><img src="//s.w.org/about/images/wp-dark-lo-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Dark Low Contrast" /></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-lo-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-lo-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-lo-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-lo-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-lo-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dark-lo-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>

					<table class="logo col-3" id="wp-blue">
						<tr><th><img src="//s.w.org/about/images/wp-blue-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Blue" /></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-blue-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-blue-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-blue-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-blue-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-blue-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-blue-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>

					<table class="logo col-3" id="wp-orange">
						<tr><th><img src="//s.w.org/about/images/wp-orange-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Orange" /></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-orange-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-orange-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-orange-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-orange-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-orange-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-orange-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>

					<table class="logo col-3" id="wp-dkblue-blue">
						<tr><th><img src="//s.w.org/about/images/wp-dkblue-blue-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Blue/Blue" /></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-blue-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-blue-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-blue-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-blue-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-blue-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-blue-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>

					<table class="logo col-3" id="wp-dkblue-orange">
						<tr><th><img src="//s.w.org/about/images/wp-dkblue-orange-bg.png" alt="WordPress Desktop Wallpaper" longdesc="WordPress Desktop Wallpaper Orange/Blue" /></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-orange-640x960.png">640&times;960 <small>Smartphones</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-orange-1024x768.png">1024&times;768</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-orange-1440x900.png">1440&times;900</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-orange-2048x1536.png">2048&times;1536 <small>iPad</small></a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-orange-2560x1440.png">2560&times;1400</a></td></tr>
						<tr><td><a href="//s.w.org/about/images/desktops/wp-dkblue-orange-2880x1800.png">2880&times;1800 <small>for Retina display</small></a></td></tr>
					</table>
				</section>

				<section class="col-8">
					<h3 class="graphics">WordPress Colors</h3>

					<p>When reproducing WordPress artwork in print or online, your project will look its best if you use this official WordPress color&nbsp;palette.</p>

					<div class="colors row gutters between">
						<div class="color-blue col-4">
							<img class="alignleft" src="//s.w.org/about/images/color-blue.png" alt="Blue color swatch" />
							<strong>Blue</strong><br />
							Pantone 7468<br />
							CMYK 97, 44, 26, 3<br />
							Hex #21759b<br />
							RGB 33, 117, 155</div>
						<div class="color-orange col-4">
							<img class="alignleft" src="//s.w.org/about/images/color-orange.png" alt="Orange color swatch" />
							<strong>Orange</strong><br />
							Pantone 1665<br />
							CMYK 6, 86, 100, 1<br />
							Hex #d54e21<br />
							RGB 213, 78, 33</div>
						<div class="color-grey col-4">
							<img class="alignleft" src="//s.w.org/about/images/color-grey.png" alt="Grey color swatch" />
							<strong>Grey</strong><br />
							Pantone Black 7<br />
							CMYK 65, 60, 60, 45<br />
							Hex #464646<br />
							RGB 70, 70, 70</div>
					</div>

					<p>The WordPress logotype is set in <a href="http://en.wikipedia.org/wiki/Mrs_Eaves">Mrs. Eaves</a>, licensed by Emigre.</p>

					<p class="community">Also check out <a href="/about/fanart/">WordPress fan art</a> created by WordPress users.</p>
				</section>
			</div><!-- .entry-content -->

			<?php
			edit_post_link(
				sprintf(
					/* translators: %s: Name of current post */
					esc_html__( 'Edit %s', 'wporg' ),
					the_title( '<span class="screen-reader-text">"', '"</span>', false )
				),
				'<footer class="entry-footer"><span class="edit-link">',
				'</span></footer><!-- .entry-footer -->'
			);
			?>
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
