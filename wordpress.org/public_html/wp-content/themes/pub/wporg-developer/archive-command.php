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
