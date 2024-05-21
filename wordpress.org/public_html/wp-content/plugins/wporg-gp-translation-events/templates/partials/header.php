<?php
namespace Wporg\TranslationEvents\Templates\Partials;

use Wporg\TranslationEvents\Urls;
use function Wporg\TranslationEvents\Templates\gp_breadcrumb_translation_events;

/** @var string $html_title */
/** @var string|callable $page_title */
/** @var ?callable $sub_head */
/** @var ?string[] $breadcrumbs */

gp_title( $html_title );
gp_breadcrumb_translation_events( $breadcrumbs ?? array() );
gp_tmpl_header();
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
