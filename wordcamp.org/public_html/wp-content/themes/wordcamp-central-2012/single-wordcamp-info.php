<?php
/**
 * Single WordCamp /info/ template.
 */

get_header(); the_post();
$wordcamp_title = wcpt_get_wordcamp_title();
?>

		<div id="container">
			<div id="content" role="main">

				<h1 class="entry-title">Info: <?php wcpt_wordcamp_title(); ?></h1>

				<div class="wc-single-info-details">

					<p>Below is some aggregated data from attendees who have already registered for this WordCamp.</p>

					<h2>T-Shirt Sizes</h2>

					<?php $sizes = WordCamp_Central_Theme::get_tshirt_sizes( wcpt_get_wordcamp_ID() ); ?>
					<?php if ( ! empty( $sizes ) ) : ?>
						<table>
							<tr>
								<th>Size</th>
								<th>Attendees</th>
							</tr>
							<?php foreach ( $sizes as $label => $value ) : ?>
							<tr>
								<td><?php echo esc_html( $label ); ?></td>
								<td><?php echo esc_html( $value ); ?></td>
							</tr>
							<?php endforeach; ?>
						</table>
					<?php else : ?>
						<p>No data yet, please check back later.</p>
					<?php endif; ?>

				</div><!-- .wc-single-info -->

			</div><!-- #content -->
		</div><!-- #container -->

<?php get_footer(); ?>