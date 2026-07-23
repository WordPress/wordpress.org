<?php
namespace Wporg\TranslationEvents\Theme_2024;

use Wporg\TranslationEvents\Attendee\Attendee;

$event               = $attributes['event'];
$user_is_attending   = $attributes['user_is_attending'];
$user_is_contributor = $attributes['user_is_contributor'];

?>
<!-- wp:wporg-translate-events-2024/event-attend-button 
<?php
echo wp_json_encode(
	array(
		'id'                  => $event->id(),
		'user_is_attending'   => $user_is_attending,
		'user_is_contributor' => $user_is_contributor,
	)
);
?>

/-->
<?php
if ( $event->is_past() ) :
	?>
<!-- wp:wporg/notice {"type":"alert", "style":{"spacing":{"margin":{"top":"var:preset|spacing|20"}}}} -->
<div class="wp-block-wporg-notice is-alert-notice" style="margin-top:var(--wp--preset--spacing--20)">
	<div class="wp-block-wporg-notice__icon"></div>
	<div class="wp-block-wporg-notice__content">
		<p>
		<?php echo esc_html__( 'This event has ended.', 'wporg-translate-events-2024' ); ?>
		</p>
	</div>
</div>
<!-- /wp:wporg/notice -->
<?php endif; ?>

<!-- wp:paragraph -->
<p>
<!-- wp:wporg-translate-events-2024/event-host-list <?php echo wp_json_encode( array( 'id' => $event->id() ) ); ?> /-->

<span> Start Date: <strong><?php $event->start()->print_time_html(); ?></strong></span>
<span> End Date: <strong><?php $event->end()->print_time_html(); ?></strong></span>
</p>
<!-- /wp:paragraph -->

<!-- wp:wporg-translate-events-2024/event-description <?php echo wp_json_encode( array( 'id' => $event->id() ) ); ?> /-->
<!-- wp:wporg-translate-events-2024/contributor-list <?php echo wp_json_encode( array( 'id' => $event->id() ) ); ?> /-->
<!-- wp:wporg-translate-events-2024/attendee-list 
<?php
echo wp_json_encode(
	array(
		'id'        => $event->id(),
		'view_type' => 'list',
	)
);
?>

/-->
<!-- wp:wporg-translate-events-2024/event-stats <?php echo wp_json_encode( array( 'id' => $event->id() ) ); ?> /-->
<!-- wp:wporg-translate-events-2024/event-projects <?php echo wp_json_encode( array( 'id' => $event->id() ) ); ?> /-->
<!-- wp:wporg-translate-events-2024/event-contribution-summary <?php echo wp_json_encode( array( 'id' => $event->id() ) ); ?> /-->
