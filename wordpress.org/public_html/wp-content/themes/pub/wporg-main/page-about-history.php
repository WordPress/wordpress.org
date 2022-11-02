<?php
/**
 * Template Name: About -> History
 *
 * Page template for displaying the History page.
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

get_header( 'child-page' );
the_post();

$date_format = get_option( 'date_format' );
?>

	<main id="main" class="site-main col-12" role="main">

		<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
			<header class="entry-header row">
				<?php the_title( '<h1 class="entry-title col-8">', '</h1>' ); ?>
			</header><!-- .entry-header -->

			<div class="entry-content row">
				<section class="col-8">
					<p><?php echo wp_kses_post( __( 'We&#8217;ve been working on a new book about the history of WordPress drawing on dozens of interviews with the original folks involved and extensive research. It&#8217;s not ready yet, but for the tenth anniversary of WordPress we&#8217;d like to make a chapter available, <em>On forking WordPress, forks in general, early WordPress, and the community</em>, which you can download below in the following formats:', 'wporg' ) ); ?></p>

					<ul>
						<li>
							<a href="chapter3.epub">
								<?php
								/* translators: file format */
								printf( esc_html__( 'Chapter 3 &#8211; %s', 'wporg' ), 'EPUB' );
								?>
							</a>
						</li>
						<li>
							<a href="chapter3.mobi">
								<?php
								/* translators: file format */
								printf( esc_html__( 'Chapter 3 &#8211; %s', 'wporg' ), 'MOBI' );
								?>
							</a>
						</li>
						<li>
							<a href="chapter3.pdf">
								<?php
								/* translators: file format */
								printf( esc_html__( 'Chapter 3 &#8211; %s', 'wporg' ), 'PDF' );
								?>
							</a>
						</li>
					</ul>

					<h2><?php esc_html_e( 'Jazzers and Release Dates', 'wporg' ); ?></h2>

					<p>
						<?php
						/* translators: 1: Link to playlist */
						printf( wp_kses_post( __( 'WordPress core developers share a love of jazz music, and all our major releases are named in honor of jazz musicians we personally admire. Here&#8217;s a list of releases and the musicians they were named for. <a href="%s">You can listen to a Last.fm station of all the musicians we named a release for</a>.', 'wporg' ) ), esc_url( 'https://www.last.fm/tag/wordpress-release-jazz' ) );
						?>
					</p>

					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Version', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Musician', 'wporg' ); ?></th>
								<th><?php esc_html_e( 'Date', 'wporg' ); ?></th>
							</tr>
						</thead>
						<tbody>
						<tr>
							<th><a href="https://wordpress.org/news/2022/11/misha/">6.1</a></th>
							<td>Mikhail Alperin</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'November 1, 2022' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2022/05/arturo/">6.0</a></th>
							<td>Arturo O’Farrill</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'May 24, 2022' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2022/01/josephine/">5.9</a></th>
							<td>Joséphine Baker</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'January 25, 2022' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2021/07/tatum/">5.8</a></th>
							<td>Art Tatum</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'July 20, 2021' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2021/03/esperanza/">5.7</a></th>
							<td>Esperanza Spalding</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'March 9, 2021' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2020/12/simone/">5.6</a></th>
							<td>Nina Simone</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 8, 2020' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2020/08/eckstine/">5.5</a></th>
							<td>Billy Eckstine</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'August 11, 2020' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2020/03/adderley/">5.4</a></th>
							<td>Nat Adderley</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'March 31, 2020' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2019/11/kirk/">5.3</a></th>
							<td>Rahsaan Roland Kirk</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'November 12, 2019' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2019/05/jaco/">5.2</a></th>
							<td>Jaco Pastorius</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'May 7, 2019' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2019/02/betty/">5.1</a></th>
							<td>Betty Carter</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'February 21, 2019' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2018/12/bebo/">5.0</a></th>
							<td>Bebo Valdés</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 6, 2018' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2017/11/tipton/">4.9</a></th>
							<td>Billy Tipton</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'November 15, 2017' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2017/06/evans/">4.8</a></th>
							<td>Bill Evans</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'June 8, 2017' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2016/12/vaughan/">4.7</a></th>
							<td>Sarah Vaughan</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 6, 2016' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2016/08/pepper/">4.6</a></th>
							<td>Pepper Adams</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'August 16, 2016' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2016/04/coleman/">4.5</a></th>
							<td>Coleman Hawkins</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'April 12, 2016' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2015/12/clifford/">4.4</a></th>
							<td>Clifford Brown</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 8, 2015' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2015/08/billie/">4.3</a></th>
							<td>Billie Holiday</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'August 18, 2015' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2015/04/powell/">4.2</a></th>
							<td>Bud Powell</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'April 23, 2015' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2014/12/dinah/">4.1</a></th>
							<td>Dinah Washington</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 18, 2014' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2014/09/benny/">4.0</a></th>
							<td>Benny Goodman</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'September 4, 2014' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2014/04/smith/">3.9</a></th>
							<td>Jimmy Smith</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'April 16, 2014' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2013/12/parker/">3.8</a></th>
							<td>Charlie Parker</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 12, 2013' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2013/10/basie/">3.7</a></th>
							<td>Count Basie</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'October 24, 2013' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2013/08/oscar/">3.6</a></th>
							<td>Oscar Peterson</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'August 1, 2013' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2012/12/elvin/">3.5</a></th>
							<td>Elvin Jones</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 11, 2012' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2012/06/green/">3.4</a></th>
							<td>Grant Green</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'June 13, 2012' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2011/12/sonny/">3.3</a></th>
							<td>Sonny Stitt</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 12, 2011' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2011/07/gershwin/">3.2</a></th>
							<td>George Gershwin</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'July 4, 2011' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2011/02/threeone/">3.1</a></th>
							<td>Django Reinhardt</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'February 23, 2011' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2010/06/thelonious/">3.0</a></th>
							<td>Thelonious Monk</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'June 17, 2010' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2009/12/wordpress-2-9/">2.9</a></th>
							<td>Carmen McRae</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 18, 2009' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2009/06/wordpress-28/">2.8</a></th>
							<td>Chet Baker</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'June 11, 2009' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2008/12/coltrane/">2.7</a></th>
							<td>John Coltrane</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 10, 2008' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2008/07/wordpress-26-tyner/">2.6</a></th>
							<td>McCoy Tyner</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'July 15, 2008' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2008/03/wordpress-25-brecker/">2.5</a></th>
							<td>Michael Brecker</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'March 29, 2008' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2007/09/wordpress-23/">2.3</a></th>
							<td>Dexter Gordon</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'September 24, 2007' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2007/05/wordpress-22/">2.2</a></th>
							<td>Stan Getz</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'May 16, 2007' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2007/01/ella-21/">2.1</a></th>
							<td>Ella Fitzgerald</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'January 22, 2007' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2005/12/wp2/">2.0</a></th>
							<td>Duke Ellington</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'December 31, 2005' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2005/02/strayhorn/">1.5</a></th>
							<td>Billy Strayhorn</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'February 17, 2005' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2004/05/heres-the-beef/">1.2</a></th>
							<td>Charles Mingus</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'May 22, 2004' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2004/01/wordpress-10/">1.0</a></th>
							<td>Miles Davis</td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'January 3, 2004' ) ) ); ?></td>
						</tr>
						<tr>
							<th><a href="https://wordpress.org/news/2003/05/wordpress-now-available/">.70</a></th>
							<td><?php esc_html_e( 'No musician chosen.', 'wporg' ); ?></td>
							<td><?php echo esc_html( date_i18n( $date_format, strtotime( 'May 27, 2003' ) ) ); ?></td>
						</tr>
						</tbody>
					</table>
				</section>
			</div><!-- .entry-content -->
		</article><!-- #post-## -->

	</main><!-- #main -->

<?php
get_footer();
