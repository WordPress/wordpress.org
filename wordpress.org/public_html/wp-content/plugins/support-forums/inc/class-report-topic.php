<?php

namespace WordPressdotorg\Forums;

class Report_Topic {

	public function __construct() {
		add_action( 'wporg_support_after_topic_info', array( $this, 'add_sidebar_link' ) );

		add_action( 'init', array( $this, 'capture_topic_report' ) );
	}

	public function capture_topic_report() {
		// Do not process anything if the user is not logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( isset( $_GET['wporg-support-report-topic'] ) ) {
			$action = sprintf(
				'report-topic-%d',
				$_GET['wporg-support-report-topic']
			);

			// Verify that the nonce is valid.
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], $action ) ) {
				return;
			}

			wp_add_object_terms( $_GET['wporg-support-report-topic'], 'modlook', 'topic-tag' );

			$reporter = $this->get_previous_reports( $_GET['wporg-support-report-topic'] );
			if ( empty( $reporter ) ) {
				$reporter = array();
			}

			$current_user = get_current_user_id();

			// In those odd cases where the same user reports a topic multiple times, let's increment them, so we can track each report time.
			$report_id = $current_user;
			if ( isset( $reporter[ $report_id ] ) ) {
				$increment = 1;

				$report_id = sprintf(
					'%d-%d',
					$current_user,
					$increment
				);

				while ( isset( $reporter[ $report_id ] ) ) {
					$increment++;

					/*
					 * If someone reports the same topic repeatedly, let's just stop logging it to avoid
					 * a never ending incremental loop, our moderators are smart enough to pick up on such behavior.
					 */
					if ( $increment > 10 ) {
						return;
					}

					$report_id = sprintf(
						'%d-%d',
						$current_user,
						$increment
					);
				}
			}

			$reporter[ $report_id ] = array(
				'time' => current_time( 'mysql' ),
				'user' => $current_user,
			);

			update_post_meta( $_GET['wporg-support-report-topic'], '_wporg_topic_reported_by', $reporter );

			wp_safe_redirect( get_the_permalink( $_GET['wporg-support-report-topic'] ) );

			exit();
		}

		if ( isset( $_GET['wporg-support-remove-modlook'] ) && current_user_can( 'moderate' ) ) {
			$action = sprintf(
				'remove-topic-modlook-%d',
				$_GET['wporg-support-remove-modlook']
			);

			// Verify that the nonce is valid.
			if ( ! wp_verify_nonce( $_GET['_wpnonce'], $action ) ) {
				return;
			}

			wp_remove_object_terms( $_GET['wporg-support-remove-modlook'], 'modlook', 'topic-tag' );

			wp_safe_redirect( get_the_permalink( $_GET['wporg-support-remove-modlook'] ) );

			exit();
		}
	}

	public function add_sidebar_link() {
		// We don't want to allow anonymous users to report topics, we want to track who reports them.
		if ( ! is_user_logged_in() ) {
			return;
		}

		$topic_id = bbp_get_topic_id();

		// Disallow closed support topics to be modlook reported after 6 months.
		$last_active_post_date = get_post_field( 'post_date', bbp_get_topic_last_active_id( $topic_id ) );

		if ( ( time() - strtotime( $last_active_post_date ) ) / MONTH_IN_SECONDS >= 6 ) {
			return;
		}

		$current_user     = get_current_user_id();
		$previous_reports = $this->get_previous_reports();
		$is_reported      = has_term( 'modlook', 'topic-tag', $topic_id );

		$report_text = '';

		if ( $is_reported ) {
			$report_text = __( 'This topic has been reported', 'wporg-forums' );
		}
		else {
			if ( isset( $previous_reports[ $current_user ] ) ) {
				$report_text = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $this->get_report_topic_url() ),
					__( 'Report this topic again', 'wporg-forums' )
				);
			}
			else {
				$report_text = sprintf(
					'<a href="%s">%s</a>',
					esc_url( $this->get_report_topic_url() ),
					__( 'Report this topic', 'wporg-forums' )
				);
			}
		}

		if ( $is_reported && current_user_can( 'moderate' ) ) {
			$report_text .= sprintf(
				'<br><a href="%s" class="button">%s</a>',
				esc_url( $this->remove_topic_modlook_url() ),
				// translators: `modlook` is the term used for posts tagged by users when they want a moderator to have a look.
				__( 'Remove modlook', 'wporg-support' )
			);
		}

		printf(
			'<li class="topic-report">%s</li>',
			$report_text
		);

		// Moderators should be able to see previous topic reports.
		if ( current_user_can( 'moderate' ) && ! empty( $previous_reports ) ) {
			$lines = array();

			foreach( $previous_reports as $report ) {
				$lines[] = sprintf(
					'<li><a href="%s">%s</a></li>',
					esc_url( bbp_get_user_profile_url( $report['user']) ),
					sprintf(
						/* translators: %1$s: Reporters display name, %2$s: date, %3$s: time */
						'%1$s on %2$s at %3$s',
						get_the_author_meta( 'display_name', $report['user'] ),
						/* translators: localized date format, see https://secure.php.net/date */
						mysql2date( __( 'F j, Y', 'wporg-forums' ), $report['time'] ),
						/* translators: localized time format, see https://secure.php.net/date */
						mysql2date( __( 'g:i a', 'wporg-forums' ), $report['time'] )
					)
				);
			}

			printf(
				'<li class="topic-previous-reports">%s<ul class="previous-reports">%s</ul></li>',
				__( 'Previously reported by:', 'wporg-support' ),
				implode( ' ', $lines )
			);
		}
	}

	public function get_previous_reports( $topic_id = null ) {
		if ( null === $topic_id ) {
			$topic_id = bbp_get_topic_id();
		}

		$reporters = get_post_meta( $topic_id, '_wporg_topic_reported_by', true );

		if ( empty( $reporters ) ) {
			$reporters = array();
		}

		return $reporters;
	}

	public function get_report_topic_url() {
		$url = add_query_arg( array(
			'wporg-support-report-topic' => bbp_get_topic_id(),
		), get_the_permalink() );

		$action = sprintf(
			'report-topic-%d',
			bbp_get_topic_id()
		);

		return wp_nonce_url( $url, $action );
	}

	public function remove_topic_modlook_url() {
		$url = add_query_arg( array(
			'wporg-support-remove-modlook' => bbp_get_topic_id(),
		), get_the_permalink() );

		$action = sprintf(
			'remove-topic-modlook-%d',
			bbp_get_topic_id()
		);

		return wp_nonce_url( $url, $action );
	}
}
