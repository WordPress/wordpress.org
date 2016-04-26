<?php
namespace WordPressdotorg\Plugin_Directory\Theme;
use WordPressdotorg\Plugin_Directory\Template;

the_post();
get_header();
$plugin_banners = Template::get_plugin_banner( $post );

?>

<div class="wrapper">

	<div style="width: 772px; margin: 0 auto;" itemscope itemtype="http://schema.org/SoftwareApplication">

		<div id="plugin-head" class="<?php echo $plugin_banners ? 'plugin-head-with-banner' : 'plugin-head-without-banner'; ?>">

		<?php if ( $plugin_banners ): ?>
			<div id="plugin-title" class="with-banner">
				<div class="vignette"></div>
				<style type="text/css">
					#plugin-title { width:772px; height:250px; background-size:772px 250px; background-image: url('<?php echo esc_url( $plugin_banners['banner'] ); ?>'); }
					<?php if ( ! empty( $plugin_banners['banner_2x'] ) ): ?>
					@media only screen and (-webkit-min-device-pixel-ratio: 1.5), only screen and (-o-min-device-pixel-ratio: 15/10), only screen and (min-resolution: 144dpi), only screen and (min-resolution: 1.5dppx) {
						#plugin-title { background-image: url('<?php echo esc_url( $plugin_banners['banner_2x'] ); ?>'); }
					}
					<?php endif; ?>
				</style>

				<h2 itemprop="name"><?php the_title(); ?></h2>
			</div>
		<?php else: ?>
			<div id="plugin-title"><h2 itemprop="name"><?php the_title(); ?></h2></div>
		<?php endif; ?>

			<div id="plugin-description">
				<p itemprop="description" class="shortdesc"><?php the_excerpt(); ?></p>
				<div class="description-right">
					<p class="button">
						<a itemprop='downloadUrl' href='<?php echo esc_url( wporg_plugins_download_link() ); ?>'><?php _e( 'Download', 'wporg-plugins' ); ?></a>
					</p>
					<meta itemprop="softwareVersion" content="<?php echo esc_attr( wporg_plugins_the_version() ); ?>" />
					<meta itemprop="fileFormat" content="application/zip" />
				</div>
			</div>

			<div style="width: 552px; float: left">
				<div id="plugin-info" class="block description">
					<div class="head head-big">
						<ul id="sections">
							<?php
							foreach ( Template::get_plugin_sections() as $section ) {
								$current = ( $section['slug'] == get_query_var( 'content_page' ) || ( 'description' == $section['slug'] && ! get_query_var( 'content_page' ) ) );
								printf(
									'<li class="%s"><a itemprop="url" href="%s">%s</a></li>',
									'section-' . $section['slug'] . ( $current ? ' current' : '' ),
									$section['url'],
									$section['title']
								);
							}
							?>
						</ul>
					</div>

					<div class="block-content">
						<?php the_content(); ?>
					</div>
				</div>
			</div>

			<div class="" style="width: 212px; float: right;">
				<?php dynamic_sidebar('single-plugin-sidebar'); ?>
			</div>

		</div>

	</div>
</div>

<br class="clear" />
<?php
get_footer();
