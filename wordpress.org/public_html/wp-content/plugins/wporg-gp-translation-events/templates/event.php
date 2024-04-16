<?php
/**
 * Template for event page.
 */

namespace Wporg\TranslationEvents;

use WP_User;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Attendee\Attendee_Repository;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Event\Event_End_Date;
use Wporg\TranslationEvents\Event\Event_Start_Date;
use Wporg\TranslationEvents\Stats\Event_Stats;
use Wporg\TranslationEvents\Stats\Stats_Row;

/** @var Attendee_Repository $attendee_repo */
/** @var Attendee $attendee */
/** @var Event $event */
/** @var int $event_id */
/** @var string $event_title */
/** @var string $event_description */
/** @var Event_Start_Date $event_start */
/** @var Event_End_Date $event_end */
/** @var Event_Stats $event_stats */
/** @var array $projects */
/** @var WP_User $user */

/* translators: %s: Event title. */
gp_title( sprintf( __( 'Translation Events - %s' ), esc_html( $event_title ) ) );
gp_breadcrumb_translation_events( array( esc_html( $event_title ) ) );
gp_tmpl_header();
$event_page_title = $event_title;
gp_tmpl_load( 'events-header', get_defined_vars(), __DIR__ );
?>

<div class="event-page-wrapper">
	<div class="event-details-left">
		<div class="event-page-content">
			<?php
				echo wp_kses_post( wpautop( make_clickable( $event_description ) ) );
			?>
		</div>
		<?php if ( ! empty( $contributors ) ) : ?>
			<div class="event-contributors">
				<h2><?php esc_html_e( 'Contributors', 'gp-translation-events' ); ?></h2>
				<ul>
					<?php foreach ( $contributors as $contributor ) : ?>
						<li class="event-contributor" title="<?php echo esc_html( implode( ', ', $contributor->locales ) ); ?>">
							<a href="<?php echo esc_url( get_author_posts_url( $contributor->ID ) ); ?>" class="avatar"><?php echo get_avatar( $contributor->ID, 48 ); ?></a>
							<a href="<?php echo esc_url( get_author_posts_url( $contributor->ID ) ); ?>" class="name"><?php echo esc_html( get_the_author_meta( 'display_name', $contributor->ID ) ); ?></a>
							<?php if ( $stats_calculator->is_first_time_contributor( $event_start, $contributor->ID ) ) : ?>
								<span class="first-time-contributor-tada" title="<?php esc_html_e( 'New Translation Contributor', 'gp-translation-events' ); ?>"></span>
							<?php endif; ?>
							<?php
							if ( ! $event->end()->is_in_the_past() ) :
								if ( ( $attendee instanceof Attendee && $attendee->is_host() ) || current_user_can( 'manage_options' ) || $user->ID === $event->author_id() ) :
									$_attendee = $attendee_repo->get_attendee( $event_id, $contributor->ID );
									if ( $_attendee instanceof Attendee ) :
										echo '<form class="add-remove-user-as-host" method="post" action="' . esc_url( gp_url( "/events/host/$event_id/$contributor->ID" ) ) . '">';
										if ( $_attendee->is_host() ) :
											echo '<input type="submit" class="button is-primary remove-as-host" value="Remove as host"/>';
										else :
											echo '<input type="submit" class="button is-secondary convert-to-host" value="Make co-host"/>';
										endif;
										echo '</form>';
									else :
										echo '<span class="event-not-attending">' . esc_html__( 'Not attending', 'gp-translation-events' ) . '</span>';
									endif;
								endif;
							endif;
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $attendees ) && ( ! $event->end()->is_in_the_past() || ( ( $attendee instanceof Attendee && $attendee->is_host() ) || current_user_can( 'manage_options' ) || $user->ID === $event->author_id() ) ) ) : ?>
			<div class="event-attendees">
				<h2><?php esc_html_e( 'Attendees', 'gp-translation-events' ); ?></h2>
				<ul>
					<?php foreach ( $attendees as $_user ) : ?>
						<li class="event-attendee">
							<a href="<?php echo esc_url( get_author_posts_url( $_user->ID ) ); ?>" class="avatar"><?php echo get_avatar( $_user->ID, 48 ); ?></a>
							<a href="<?php echo esc_url( get_author_posts_url( $_user->ID ) ); ?>" class="name"><?php echo esc_html( get_the_author_meta( 'display_name', $_user->ID ) ); ?></a>
							<?php if ( $stats_calculator->is_first_time_contributor( $event_start, $_user->ID ) ) : ?>
								<span class="first-time-contributor-tada" title="<?php esc_html_e( 'New Translation Contributor', 'gp-translation-events' ); ?>"></span>
							<?php endif; ?>
							<?php
							if ( ! $event->end()->is_in_the_past() ) :
								if ( ( $attendee instanceof Attendee && $attendee->is_host() ) || current_user_can( 'manage_options' ) || $user->ID === $event->author_id() ) :
									$_attendee = $attendee_repo->get_attendee( $event_id, $_user->ID );
									if ( $_attendee instanceof Attendee ) :
										echo '<form class="add-remove-user-as-host" method="post" action="' . esc_url( gp_url( "/events/host/$event_id/$_user->ID" ) ) . '">';
										if ( $_attendee->is_host() ) :
											echo '<input type="submit" class="button is-primary remove-as-host" value="Remove as host"/>';
										else :
											echo '<input type="submit" class="button is-secondary convert-to-host" value="Make co-host"/>';
										endif;
										echo '</form>';
									endif;
								endif;
							endif;
							?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $event_stats->rows() ) ) : ?>
			<div class="event-details-stats">
				<h2><?php esc_html_e( 'Stats', 'gp-translation-events' ); ?></h2>
				<table>
					<thead>
					<tr>
						<th scope="col">Locale</th>
						<th scope="col">Translations created</th>
						<th scope="col">Translations reviewed</th>
						<th scope="col">Contributors</th>
					</tr>
					</thead>
					<tbody>
					<?php /** @var $row Stats_Row */ ?>
					<?php foreach ( $event_stats->rows() as $_locale => $row ) : ?>
					<tr>
						<td title="<?php echo esc_html( $_locale ); ?> "><a href="<?php echo esc_url( gp_url_join( gp_url( '/languages' ), $row->language->slug ) ); ?>"><?php echo esc_html( $row->language->english_name ); ?></a></td>
						<td><?php echo esc_html( $row->created ); ?></td>
						<td><?php echo esc_html( $row->reviewed ); ?></td>
						<td><?php echo esc_html( $row->users ); ?></td>
					</tr>
				<?php endforeach ?>
					<tr class="event-details-stats-totals">
						<td>Total</td>
						<td><?php echo esc_html( $event_stats->totals()->created ); ?></td>
						<td><?php echo esc_html( $event_stats->totals()->reviewed ); ?></td>
						<td><?php echo esc_html( $event_stats->totals()->users ); ?></td>
					</tr>
					</tbody>
				</table>
			</div>
			<div class="event-projects">
				<h2><?php esc_html_e( 'Projects', 'gp-translation-events' ); ?></h2>
				<ul>
					<?php foreach ( $projects as $project_name => $row ) : ?>
					<li class="event-project" title="<?php echo esc_html( str_replace( ',', ', ', $row->locales ) ); ?>">
						<a href="<?php echo esc_url( gp_url_project( $row->project ) ); ?>"><?php echo esc_html( $project_name ); ?></a> <small> to
						<?php
						foreach ( explode( ',', $row->locales ) as $_locale ) {
							$_locale = \GP_Locales::by_slug( $_locale );
							?>
							<a href="<?php echo esc_url( gp_url_project_locale( $row->project, $_locale, 'default' ) ); ?>"><?php echo esc_html( $_locale->english_name ); ?></a>
							<?php
						}
						// translators: %d: Number of contributors.
						echo esc_html( sprintf( _n( 'by %d contributor', 'by %d contributors', $row->users, 'gp-translation-events' ), $row->users ) );
						?>
						</small>
					</li>
				<?php endforeach; ?>
				</ul>
			</div>
			<details class="event-stats-summary">
				<summary>View stats summary in text </summary>
				<p class="event-stats-text">
					<?php
					echo wp_kses(
						sprintf(
							// translators: %1$s: Event title, %2$d: Number of contributors, %3$d: Number of languages, %4$s: List of languages, %5$d: Number of strings translated, %6$d: Number of strings reviewed.
							__( 'At the <strong>%1$s</strong> event, %2$d people contributed in %3$d languages (%4$s), translated %5$d strings and reviewed %6$d strings.', 'gp-translation-events' ),
							esc_html( $event_title ),
							esc_html( $event_stats->totals()->users ),
							count( $event_stats->rows() ),
							esc_html(
								implode(
									', ',
									array_map(
										function ( $row ) {
											return $row->language->english_name;
										},
										$event_stats->rows()
									)
								)
							),
							esc_html( $event_stats->totals()->created ),
							esc_html( $event_stats->totals()->reviewed )
						),
						array(
							'strong' => array(),
						)
					);
					?>
					<?php
					echo wp_kses(
						sprintf(
						// translators: %s the contributors.
							esc_html__( 'Contributors were %s.', 'gp-translation-events' ),
							implode(
								', ',
								array_map(
									function ( $contributor ) use ( $stats_calculator, $event_start ) {
										$append_tada = '';
										if ( $stats_calculator->is_first_time_contributor( $event_start, $contributor->ID ) ) {
											$append_tada = '<span class="first-time-contributor-tada" title="' . esc_html__( 'New Translation Contributor', 'gp-translation-events' ) . '"></span>';
										}
										return '@' . $contributor->user_login . $append_tada;
									},
									$contributors
								)
							)
						),
						array(
							'span' => array(
								'class' => array(),
								'title' => array(),
							),
						)
					);
					?>
			</p>
	</details>
		<?php endif; ?>
	</div>
	<div class="event-details-right">
		<div class="event-details-date">
			<p>
				<span class="event-details-date-label">
					<?php echo esc_html( $event_start->is_in_the_past() ? __( 'Started', 'gp-translation-events' ) : __( 'Starts', 'gp-translation-events' ) ); ?>:
					<?php $event_start->print_relative_time_html(); ?>
				</span>
				<?php $event_start->print_time_html(); ?>
				<span class="event-details-date-label">
					<?php echo esc_html( $event_end->is_in_the_past() ? __( 'Ended', 'gp-translation-events' ) : __( 'Ends', 'gp-translation-events' ) ); ?>:
					<?php $event_end->print_relative_time_html(); ?>

				</span>
				<?php $event_end->print_time_html(); ?>
			</p>
		</div>
		<?php if ( is_user_logged_in() ) : ?>
		<div class="event-details-join">
			<?php if ( $event_end->is_in_the_past() ) : ?>
				<?php if ( $attendee instanceof Attendee ) : ?>
					<button disabled="disabled" class="button is-primary attend-btn"><?php esc_html_e( 'You attended', 'gp-translation-events' ); ?></button>
				<?php endif; ?>
			<?php else : ?>
				<form class="event-details-attend" method="post" action="<?php echo esc_url( gp_url( "/events/attend/$event_id" ) ); ?>">
					<?php if ( $attendee instanceof Attendee ) : ?>
						<input type="submit" class="button is-secondary attending-btn" value="You're attending" />
					<?php else : ?>
						<input type="submit" class="button is-primary attend-btn" value="Attend Event"/>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>
		<?php else : ?>
		<div class="event-details-join">
			<p>
				<?php if ( ! $event_end->is_in_the_past() ) : ?>
					<a href="<?php echo esc_url( wp_login_url() ); ?>" class="button is-primary attend-btn"><?php esc_html_e( 'Login to attend', 'gp-translation-events' ); ?></a>
				<?php else : ?>
					<button disabled="disabled" class="button is-primary attend-btn"><?php esc_html_e( 'Event is over', 'gp-translation-events' ); ?></button>
				<?php endif; ?>
			</p>
		</div>
		<?php endif; ?>
	</div>
</div>
<div class="clear"></div>
<?php gp_tmpl_footer(); ?>
