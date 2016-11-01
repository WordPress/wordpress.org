<?php

namespace CampTix\Badge_Generator\HTML;
defined( 'WPINC' ) or die();

/**
 * @var \WP_Post $attendee
 * @var array    $allowed_html
 */

?>

<header>
	<?php if ( has_custom_logo() ) : ?>
		<?php the_custom_logo(); ?>

	<?php else : ?>
		<h2 class="wordcamp-name">
			<?php echo esc_html( get_wordcamp_name() ); ?>
		</h2>

	<?php endif; ?>
</header>

<figure>
	<img
	    class="attendee-avatar"
		src="<?php echo esc_url( $attendee->avatar_url ); ?>"
		alt="<?php echo esc_attr( strip_tags( $attendee->formatted_name ) ); ?>"
	/>

	<figcaption>
		<h1 class="attendee-name">
			<?php echo wp_kses( $attendee->formatted_name, $allowed_html ); ?>
		</h1>
	</figcaption>
</figure>

<!-- These are arbitrary elements that you can use for any purpose -->
<div class="badge-design-element-1"></div>
<div class="badge-design-element-2"></div>
<div class="badge-design-element-3"></div>
<div class="badge-design-element-4"></div>
<div class="badge-design-element-5"></div>
