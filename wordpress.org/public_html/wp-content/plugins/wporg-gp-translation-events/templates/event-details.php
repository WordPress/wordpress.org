<?php
/**
 * Template for event page.
 */
namespace Wporg\TranslationEvents\Templates;

use GP_Locales;
use WP_User;
use Wporg\TranslationEvents\Attendee\Attendee;
use Wporg\TranslationEvents\Event\Event;
use Wporg\TranslationEvents\Stats\Event_Stats;
use Wporg\TranslationEvents\Stats\Stats_Row;
use Wporg\TranslationEvents\Templates;
use Wporg\TranslationEvents\Urls;

/** @var bool $user_is_attending */
/** @var bool $user_is_contributor */
/** @var Attendee[] $hosts */
/** @var Attendee[] $attendees_not_contributing */
/** @var Attendee[] $contributors */
/** @var array $new_contributor_ids */
/** @var Event $event */
/** @var Event_Stats $event_stats */
/** @var array $projects */
/** @var WP_User $user */

Templates::header(
	array(
		/* translators: %s: Event title. */
		'html_title'       => sprintf( __( 'Translation Events - %s' ), esc_html( $event->title() ) ),
		'page_title'       => function () use ( $event ) {
			echo esc_html( $event->title() );
			if ( 'draft' === $event->status() ) : ?>
				<span class="event-label-draft"><?php echo esc_html( $event->status() ); ?></span>
			<?php endif; ?>
			<?php
		},
		'html_description' => $event->description(),
		'url'              => Urls::event_details_absolute( $event->id() ),
		'image_url'        => Urls::event_image( $event->id() ),
		'breadcrumbs'      => array( esc_html( $event->title() ) ),
		'sub_head'         => function () use ( $event, $user, $hosts ) {
			?>
			<span class="event-host">
				<?php
				$has_hosts = count( $hosts ) > 0;

				if ( ! $has_hosts ) {
					$hosts = array( new Attendee( $event->id(), $event->author_id(), true ) );
				}
				$hosts_list = array_map(
					function ( $host ) {
						$url  = get_author_posts_url( $host->user_id() );
						$name = get_the_author_meta( 'display_name', $host->user_id() );
						return '<a href="' . esc_attr( $url ) . '">' . esc_html( $name ) . '</a>';
					},
					$hosts
				);

				if ( ! $has_hosts ) {
					/* translators: %s: Display name of the user who created the event. */
					$hosts_string = __( 'Created by: %s', 'gp-translation-events' );
				} else {
					/* translators: %s is a comma-separated list of event hosts (=usernames) */
					$hosts_string = _n( 'Host: %s', 'Hosts: %s', count( $hosts ), 'gp-translation-events' );
				}

				echo wp_kses(
					sprintf( $hosts_string, implode( ', ', $hosts_list ) ),
					array( 'a' => array( 'href' => array() ) )
				);
				?>
			</span>
			<?php if ( current_user_can( 'edit_translation_event', $event->id() ) ) : ?>
				<a class="event-page-edit-link" href="<?php echo esc_url( Urls::event_edit( $event->id() ) ); ?>"><span class="dashicons dashicons-edit"></span><?php esc_html_e( 'Edit event', 'gp-translation-events' ); ?></a>
			<?php endif ?>
			<?php
		},
	),
);
?>

<div class="event-page-wrapper">
	<div class="event-details-left">
		<div class="event-page-content">
			<?php
				echo wp_kses_post( wpautop( make_clickable( $event->description() ) ) );
			?>
		</div>
		<?php if ( ! empty( $contributors ) ) : ?>
			<div class="event-contributors">
				<h2>
				<?php
				// translators: %d is the number of contributors.
				echo esc_html( sprintf( __( 'Contributors (%d)', 'gp-translation-events' ), number_format_i18n( count( $contributors ) ) ) );
				?>
				</h2>
				<ul>
					<?php foreach ( $contributors as $contributor ) : ?>
						<li class="event-contributor" title="<?php echo esc_html( implode( ', ', $contributor->contributed_locales() ) ); ?>">
							<a href="<?php echo esc_url( get_author_posts_url( $contributor->user_id() ) ); ?>" class="avatar"><?php echo get_avatar( $contributor->user_id(), 48 ); ?></a>
							<a href="<?php echo esc_url( get_author_posts_url( $contributor->user_id() ) ); ?>" class="name"><?php echo esc_html( get_the_author_meta( 'display_name', $contributor->user_id() ) ); ?></a>
							<?php if ( $contributor->is_new_contributor() ) : ?>
								<span class="first-time-contributor-tada" title="<?php esc_html_e( 'New Translation Contributor', 'gp-translation-events' ); ?>"></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>
		<?php if ( ! empty( $attendees_not_contributing ) && current_user_can( 'edit_translation_event', $event->id() ) ) : ?>
			<div class="event-attendees">
				<h2>
				<?php
				// translators: %d is the number of attendees.
				echo esc_html( sprintf( __( 'Attendees (%d)', 'gp-translation-events' ), number_format_i18n( count( $attendees_not_contributing ) ) ) );
				?>
				</h2>
				<ul>
					<?php foreach ( $attendees_not_contributing as $_attendee ) : ?>
						<li class="event-attendee">
							<a href="<?php echo esc_url( get_author_posts_url( $_attendee->user_id() ) ); ?>" class="avatar"><?php echo get_avatar( $_attendee->user_id(), 48 ); ?></a>
							<a href="<?php echo esc_url( get_author_posts_url( $_attendee->user_id() ) ); ?>" class="name"><?php echo esc_html( get_the_author_meta( 'display_name', $_attendee->user_id() ) ); ?></a>
							<?php if ( $_attendee->is_new_contributor() ) : ?>
								<span class="first-time-contributor-tada" title="<?php esc_html_e( 'New Translation Contributor', 'gp-translation-events' ); ?>"></span>
							<?php endif; ?>
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
						<th scope="col"><?php esc_html_e( 'Translations', 'gp-translation-events' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Created', 'gp-translation-events' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Reviewed', 'gp-translation-events' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Contributors', 'gp-translation-events' ); ?></th>
					</tr>
					</thead>
					<tbody>
					<?php /** @var $row Stats_Row */ ?>
					<?php foreach ( $event_stats->rows() as $_locale => $row ) : ?>
					<tr>
						<td title="<?php echo esc_html( $_locale ); ?> "><a href="<?php echo esc_url( gp_url_join( gp_url( '/languages' ), $row->language->slug ) ); ?>"><?php echo esc_html( $row->language->english_name ); ?></a></td>
						<td><a href="<?php echo esc_url( Urls::event_translations( $event->id(), $row->language->slug ) ); ?>"><?php echo esc_html( $row->created ); ?></a></td>
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
						<?php
						$row_locales = array();
						foreach ( explode( ',', $row->locales ) as $_locale ) {
							$_locale       = GP_Locales::by_slug( $_locale );
							$row_locales[] = '<a href="' . esc_url( gp_url_project_locale( $row->project, $_locale->slug, 'default' ) ) . '">' . esc_html( $_locale->english_name ) . '</a>';
						}
						echo wp_kses_post(
							wp_sprintf(
								// translators: 1: Project translated. 2: List of languages. 3: Number of contributors.
								_n(
									'%1$s <small>to %2$l by %3$d contributor</small>',
									'%1$s <small>to %2$l by %3$d contributors</small>',
									$row->users,
									'gp-translation-events'
								),
								'<a href="' . esc_url( gp_url_project( $row->project ) ) . '">' . esc_html( $project_name ) . '</a>',
								$row_locales,
								$row->users
							)
						);
						?>
					</li>
				<?php endforeach; ?>
				</ul>
			</div>
			<details class="event-stats-summary" open>
				<summary><?php esc_html_e( 'View stats summary in text', 'gp-translation-events' ); ?></summary>
				<p class="event-stats-text">
					<?php
					$new_contributors_text = '';
					if ( ! empty( $new_contributor_ids ) ) {
						$new_contributors_text = sprintf(
							// translators: %d is the number of new contributors.
							_n( '(%d new contributor 🎉)', '(%d new contributors 🎉)', count( $new_contributor_ids ), 'gp-translation-events' ),
							count( $new_contributor_ids )
						);
					}

					echo wp_kses(
						wp_sprintf(
							// translators: %1$s: Event title, %2$d: Number of contributors, %3$s: is a parenthesis with potential text "x new contributors", %4$d: Number of languages, %5$l: List of languages, %6$d: Number of strings translated, %7$d: Number of strings reviewed.
							__( 'At the <strong>%1$s</strong> event, we had %2$d people %3$s who contributed in %4$d languages (%5$l), translated %6$d strings and reviewed %7$d strings.', 'gp-translation-events' ),
							esc_html( $event->title() ),
							esc_html( $event_stats->totals()->users ),
							$new_contributors_text,
							count( $event_stats->rows() ),
							array_map(
								function ( $row ) {
									return $row->language->english_name;
								},
								$event_stats->rows()
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
						wp_sprintf(
							// translators: %s List of contributors.
							_n(
								'Contributor was %l.',
								'Contributors were %l.',
								count( $contributors ),
								'gp-translation-events'
							),
							array_map(
								function ( $contributor ) {
									$append_tada = '';
									if ( $contributor->is_new_contributor() ) {
											$append_tada = ' <span class="new-contributor" title="' . esc_html__( 'New Translation Contributor', 'gp-translation-events' ) . '">🎉</span>';
									}
									return '@' . ( new WP_User( $contributor->user_id() ) )->user_login . $append_tada;
								},
								$contributors
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
					<?php echo esc_html( $event->start()->is_in_the_past() ? __( 'Started', 'gp-translation-events' ) : __( 'Starts', 'gp-translation-events' ) ); ?>:
					<?php $event->start()->print_relative_time_html(); ?>
				</span>
				<?php $event->start()->print_time_html(); ?>
				<span class="event-details-date-label">
					<?php echo esc_html( $event->end()->is_in_the_past() ? __( 'Ended', 'gp-translation-events' ) : __( 'Ends', 'gp-translation-events' ) ); ?>:
					<?php $event->end()->print_relative_time_html(); ?>

				</span>
				<?php $event->end()->print_time_html(); ?>
			</p>
		</div>
		<?php if ( is_user_logged_in() ) : ?>
		<div class="event-details-join">
			<?php if ( $event->end()->is_in_the_past() ) : ?>
				<?php if ( $user_is_attending ) : ?>
					<button disabled="disabled" class="button is-primary attend-btn"><?php esc_html_e( 'You attended', 'gp-translation-events' ); ?></button>
				<?php endif; ?>
			<?php elseif ( $user_is_contributor ) : ?>
				<?php // Contributors can't un-attend so don't show anything. ?>
			<?php else : ?>
				<form class="event-details-attend" method="post" action="<?php echo esc_url( Urls::event_toggle_attendee( $event->id() ) ); ?>">
					<?php if ( $user_is_attending ) : ?>
						<input type="submit" class="button is-secondary attending-btn" value="<?php esc_attr_e( "You're attending", 'gp-translation-events' ); ?>" />
					<?php else : ?>
						<input type="submit" class="button is-primary attend-btn" value="<?php esc_attr_e( 'Attend Event', 'gp-translation-events' ); ?>"/>
					<?php endif; ?>
				</form>
			<?php endif; ?>
		</div>
		<?php else : ?>
		<div class="event-details-join">
			<p>
				<?php if ( ! $event->end()->is_in_the_past() ) : ?>
					<a href="<?php echo esc_url( wp_login_url() ); ?>" class="button is-primary attend-btn"><?php esc_html_e( 'Login to attend', 'gp-translation-events' ); ?></a>
				<?php else : ?>
					<button disabled="disabled" class="button is-primary attend-btn"><?php esc_html_e( 'Event is over', 'gp-translation-events' ); ?></button>
				<?php endif; ?>
			</p>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php Templates::footer(); ?>
