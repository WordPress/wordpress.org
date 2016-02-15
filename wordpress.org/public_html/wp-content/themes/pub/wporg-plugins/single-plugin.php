<?php the_post(); ?>
<?php get_header(); ?>

<div class="wrapper">

	<div style="width: 772px; margin: 0 auto;" itemscope itemtype="http://schema.org/SoftwareApplication">

		<div id="plugin-head" class="plugin-head-with-banner">

			<div id="plugin-title" class="with-banner">
				<div class="vignette"></div>
				<style type="text/css">
				#plugin-title { width:772px; height:250px; background-size:772px 250px;	background-image: url(//ps.w.org/debug-bar/assets/banner-772x250.png?rev=478338); }
				</style>

				<h2 itemprop="name"><?php the_title(); ?></h2>
			</div>

			<div id="plugin-description">
				<p itemprop="description" class="shortdesc"><?php the_excerpt(); ?></p>
				<div class="description-right">
					<p class="button">
						<a itemprop='downloadUrl' href='<?php echo esc_url( wporg_plugins_download_link() ); ?>'><?php printf( __( 'Download Version %s', 'wporg-plugins' ), wporg_plugins_the_version() ); ?></a>
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
							foreach ( WPorg_Plugin_Directory_Template::get_plugin_sections() as $section ) {
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
				<p>
					<strong>Requires:</strong> <?php printf( __('%s or higher', 'wporg-plugins' ), wporg_plugins_template_requires() ); ?><br />
					<strong>Compatible up to:</strong> <?php echo wporg_plugins_template_compatible_up_to(); ?><br />
					<strong>Last Updated: </strong> <?php echo wporg_plugins_template_last_updated(); ?><br />
					<strong>Active Installs:</strong> <?php echo worg_plugins_template_active_installs( false ); ?><br />
					<meta itemprop="dateModified" content="<?php the_time('Y-m-d'); ?>" />
				</p>
			</div>

		</div>

	</div>
</div>

<br class="clear" />
<?php
get_footer();
