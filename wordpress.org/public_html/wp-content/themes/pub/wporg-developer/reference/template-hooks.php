<?php
/**
 * Reference Template: Hooks Functionality
 *
 * @package wporg-developer
 * @subpackage Reference
 */

namespace DevHub;

if ( show_usage_info() ) :

	$has_hooks   = ( post_type_has_hooks_info() && ( $hooks   = get_hooks()   ) && $hooks->have_posts()   );

	if ( $has_hooks ) :
	?>
	<hr />
	<section class="hooks">
		<h2><?php _e( 'Hooks', 'wporg' ); ?></h2>
		<article class="hooks">

			<?php while ( $hooks->have_posts() ) : $hooks->the_post(); ?>
			<dl>
				<dt class="signature-highlight">
					<a href="<?php the_permalink(); ?>" style="text-decoration: none">
						<?php echo get_signature(); ?>
					</a>
				</dt>
				<dd class="hook-desc">
					<p>
						<?php echo get_summary(); ?>
					</p>
				</dd>
			</dl>
			<?php endwhile; wp_reset_postdata(); ?>
		</article>
	</section>
	<?php endif; ?>
<?php endif; ?>
