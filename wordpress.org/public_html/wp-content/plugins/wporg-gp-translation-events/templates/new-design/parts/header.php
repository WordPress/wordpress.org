<?php
namespace Wporg\TranslationEvents\Templates\NewDesign\Parts;

use Wporg\TranslationEvents\Templates;
use Wporg\TranslationEvents\Urls;

/** @var string $html_title */
/** @var string|callable $page_title */
/** @var string $url */
/** @var string $image_url */
/** @var string $html_description */
/** @var ?callable $sub_head */
/** @var ?string[] $breadcrumbs */

$html_title       = $html_title ?? esc_html__( 'Translation Events', 'gp-translation-events' );
$url              = $url ?? Urls::events_home();
$html_description = $html_description ?? esc_html__( 'WordPress Translation Events', 'gp-translation-events' );
$image_url        = $image_url ?? Urls::event_default_image();

gp_title( $html_title );
Templates::part( 'site-header', get_defined_vars() );
Templates::part( 'breadcrumbs', array( 'extra_items' => $breadcrumbs ?? array() ) );

?>

<div class="event-list-top-bar">
	<h2 class="event-page-title">
		<?php if ( is_callable( $page_title ) ) : ?>
			<?php $page_title(); ?>
		<?php else : ?>
			<?php echo esc_html( $page_title ); ?>
		<?php endif; ?>
	</h2>

	<ul class="event-list-nav">
		<?php if ( is_user_logged_in() ) : ?>
			<?php if ( current_user_can( 'manage_translation_events' ) ) : ?>
				<li><a href="<?php echo esc_url( Urls::events_trashed() ); ?>">Deleted Events</a></li>
			<?php endif; ?>
			<li><a href="<?php echo esc_url( Urls::my_events() ); ?>">My Events</a></li>
			<?php if ( current_user_can( 'create_translation_event' ) ) : ?>
				<li><a class="button is-primary" href="<?php echo esc_url( Urls::event_create() ); ?>">Create Event</a></li>
			<?php endif; ?>
		<?php endif; ?>
	</ul>

	<?php if ( isset( $sub_head ) && is_callable( $sub_head ) ) : ?>
		<p class="event-sub-head">
			<?php $sub_head(); ?>
		</p>
	<?php endif; ?>
</div>
