<?php
namespace Wporg\TranslationEvents\Templates\Partials;

use Wporg\TranslationEvents\Urls;
use function Wporg\TranslationEvents\Templates\gp_breadcrumb_translation_events;

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

add_action(
	'gp_head',
	function () use ( $html_title, $url, $html_description, $image_url ) {
		echo '<meta name="twitter:card" content="summary" />' . "\n";
		echo '<meta name="twitter:site" content="@WordPress" />' . "\n";
		echo '<meta name="twitter:title" content="' . esc_attr( $html_title ) . '" />' . "\n";
		echo '<meta name="twitter:description" content="' . esc_attr( $html_description ) . '" />' . "\n";
		echo '<meta name="twitter:creator" content="@WordPress" />' . "\n";
		echo '<meta name="twitter:image" content="' . esc_url( $image_url ) . '" />' . "\n";
		echo '<meta name="twitter:image:alt" content="' . esc_attr( $html_title ) . '" />' . "\n";

		echo '<meta property="og:url" content="' . esc_url( $url ) . '" />' . "\n";
		echo '<meta property="og:title" content="' . esc_attr( $html_title ) . '" />' . "\n";
		echo '<meta property="og:description" content="' . esc_attr( $html_description ) . '" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( get_bloginfo() ) . '" />' . "\n";
		echo '<meta property="og:image:url" content="' . esc_url( $image_url ) . '" />' . "\n";
		echo '<meta property="og:image:secure_url" content="' . esc_url( $image_url ) . '" />' . "\n";
		echo '<meta property="og:image:type" content="image/png" />' . "\n";
		echo '<meta property="og:image:width" content="1200" />' . "\n";
		echo '<meta property="og:image:height" content="675" />' . "\n";
		echo '<meta property="og:image:alt" content="' . esc_attr( $html_title ) . '" />' . "\n";
	}
);
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
