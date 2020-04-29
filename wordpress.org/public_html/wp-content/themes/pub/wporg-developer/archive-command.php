<?php namespace DevHub;

/**
 * The template for displaying Archive pages.
 *
 * Learn more: https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package wporg-developer
 */

get_header(); ?>

	<div id="content-area">

		<?php breadcrumb_trail(); ?>

		<main id="main" class="site-main" role="main">

			<p><?php _e( 'Below is a listing of all currently available WP-CLI commands with links to documentation on usage and subcommands.', 'wporg' ); ?></p>

			<p><?php printf(
				__( 'Looking to learn more about the internal API of WP-CLI or to contribute to its development? Check out the WP-CLI team&#8217;s <a href="%s">handbook</a>.', 'wporg' ),
				'https://make.wordpress.org/cli/handbook/'
			); ?></p>

			<?php if ( have_posts() ) : ?>

				<table>
					<thead>
						<tr>
							<th><?php _e( 'Command', 'wporg' ); ?></th>
							<th><?php _e( 'Description', 'wporg' ); ?></th>
						</tr>
					</thead>
					<tbody>

				<?php /* Start the Loop */ ?>
				<?php while ( have_posts() ) : the_post(); ?>
					<tr>
						<td><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></td>
						<td><?php the_excerpt(); ?></td>
					</tr>

				<?php endwhile; ?>
					</tbody>
				</table>

			<?php else : ?>

				<?php get_template_part( 'content', 'none' ); ?>

			<?php endif; ?>
			<?php loop_pagination(); ?>
		</main>
		<!-- /wrapper -->
	<?php get_sidebar(); ?>
	</div><!-- /pagebody -->

<?php get_footer(); ?>
