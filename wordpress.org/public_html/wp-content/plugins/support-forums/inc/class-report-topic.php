<?php

namespace WordPressdotorg\Forums;

class Report_Topic {

	public function __construct() {
		add_action( 'wporg_support_after_topic_info', array( $this, 'add_sidebar_form' ) );

		add_action( 'set_object_terms', array( $this, 'detect_manual_modlook' ), 10, 6 );

		add_action( 'init', array( $this, 'capture_topic_report' ) );
	}

	public function detect_manual_modlook( $object_id, $terms, $tt_ids, $taxonomy, $append, $old_tt_ids ) {
		$modlook = null;

		/*
		 * Loop over the submitted terms to get the modlook taxonomy ID.
		 * it's slightly less ideal than a `in_array()` check, but is needed as users
		 * may have put spaces around the term somehow for example, and we need the ID
		 * later for processing.
		 */
		foreach ( $terms as $term_id => $term_slug ) {
			if ( 'modlook' === trim( $term_slug ) ) {
				$modlook = $tt_ids[ $term_id ];
				break;
			}
		}

		// If no modlook tag was found, or if the tag already existed from a previous reply, don't record it again.
		if ( null === $modlook || in_array( $modlook, $old_tt_ids ) ) {
			return;
		}

		// Translators: Default string to show when a topic is reported outside the report form feature.
		$this->add_modlook_history( $object_id, __( '[Manually added when replying]', 'wporg-forums' ), true );
	}

	public function add_modlook_history( $topic, $reason = null ) {
		$reporter = $this->get_previous_reports( $topic );
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
			'time'   => current_time( 'mysql' ),
			'user'   => $current_user,
			'reason' => $reason,
		);

		update_post_meta( $topic, '_wporg_topic_reported_by', $reporter );

	}

	public function capture_topic_report() {
		// Do not process anything if the user is not logged in.
		if ( ! is_user_logged_in() ) {
			return;
		}

		if ( isset( $_POST['wporg-support-report-topic'] ) ) {
			$action = sprintf(
				'report-topic-%d',
				$_POST['wporg-support-report-topic']
			);

			// Verify that the nonce is valid.
			if ( ! wp_verify_nonce( $_POST['_wpnonce'], $action ) ) {
				return;
			}

			remove_action( 'set_object_terms', array( $this, 'detect_manual_modlook' ), 10 );
			wp_add_object_terms( $_POST['wporg-support-report-topic'], 'modlook', 'topic-tag' );

			$reason = $_POST['topic-report-reason'];

			if ( 'other-input' === $reason ) {
				$reason = $_POST['topic-report-reason-other'];
			}

			$this->add_modlook_history( $_POST['wporg-support-report-topic'], $reason );

			wp_safe_redirect( get_the_permalink( $_POST['wporg-support-report-topic'] ) );

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

	public function add_sidebar_form() {
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

		$previous_reports = $this->get_previous_reports();
		$is_reported      = has_term( 'modlook', 'topic-tag', $topic_id );

		if ( $is_reported ) {
			$report_text = __( 'This topic has been reported', 'wporg-forums' );
		}
		else {
			$action = sprintf(
				'report-topic-%d',
				bbp_get_topic_id()
			);

			ob_start();
?>

			<form action="" method="post">
				<?php wp_nonce_field( $action ); ?>
				<input type="hidden" name="wporg-support-report-topic" value="<?php echo esc_attr( bbp_get_topic_id() ); ?>">

				<label for="topic-report-reason"><?php _e( 'Report this topic for:', 'wporg-forums' ); ?></label>
				<select name="topic-report-reason" id="topic-report-reason" required="required" onchange="wporg_report_topic_change()">
					<option value=""><?php _ex( '&mdash; Choose one &mdash;', 'Report a topic reason', 'wporg-forums' ); ?></option>
					<option><?php _ex( 'Guideline violation', 'Report a topic reason', 'wporg-forums' ); ?></option>
					<option><?php _ex( 'Security related', 'Report a topic reason', 'wporg-forums' ); ?></option>
					<option><?php _ex( 'Spam', 'Report a topic reason', 'wporg-forums' ); ?></option>
					<option><?php _ex( 'NSFW (Not Safe For Work) link', 'Report a topic reason', 'wporg-forums' ); ?></option>
					<option value="other-input"><?php _ex( 'Other', 'Report a topic reason', 'wporg-forums' ); ?></option>
				</select>
				<aside id="report-topic-other" style="display: none;">
					<label for="topic-report-reason-other"><?php _e( 'Your own reason:', 'wporg-forums' ); ?></label>
					<input type="text" name="topic-report-reason-other" id="topic-report-reason-other">
				</aside>
				<input type="submit" name="submit" value="<?php esc_attr_e( 'Report', 'wporg-forums' ); ?>">
			</form>

            <script type="text/javascript">
                function wporg_report_topic_change() {
                	if ( 'other-input' === document.getElementById('topic-report-reason').value ) {
                		document.getElementById( 'report-topic-other' ).style.display = 'block';
                    } else {
						document.getElementById( 'report-topic-other' ).style.display = 'none';
                    }
                }
            </script>
<?php
			$report_text = ob_get_clean();
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

			// List the latest report first.
			$previous_reports = array_reverse( $previous_reports );

			foreach( $previous_reports as $report ) {
				$lines[] = sprintf(
					'<li>%s</li>',
					sprintf(
						/* translators: 1: Reporters display name, 2: date, 3: time, 4: reason (when provided) */
						'%1$s on %2$s at %3$s %4$s',
						sprintf(
							'<a href="%s">%s</a>',
							esc_url( bbp_get_user_profile_url( $report['user']) ),
							get_the_author_meta( 'display_name', $report['user'] )
						),
						/* translators: localized date format, see https://www.php.net/date */
						mysql2date( __( 'F j, Y', 'wporg-forums' ), $report['time'] ),
						/* translators: localized time format, see https://www.php.net/date */
						mysql2date( __( 'g:i a', 'wporg-forums' ), $report['time'] ),
						( ! isset( $report['reason'] ) || empty( $report['reason'] ) ?  '' : sprintf(
							/* translators: %s: The reason this topic was reported. */
							'reason: %s',
							esc_html( $report['reason'] )
						) )
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
