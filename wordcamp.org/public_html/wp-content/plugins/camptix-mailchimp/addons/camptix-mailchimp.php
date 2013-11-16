<?php
class CampTix_MailChimp_Addon extends CampTix_Addon {
	public function camptix_init() {
		global $camptix;

		add_filter( 'camptix_setup_sections', array( $this, 'setup_sections' ) );
		add_action( 'camptix_menu_setup_controls', array( $this, 'menu_setup_controls' ) );
		add_filter( 'camptix_validate_options', array( $this, 'validate_options' ), 10, 2 );

		$this->options = $camptix->get_options();

		if ( $this->options['mailchimp_override_email'] )
			add_filter( 'camptix_wp_mail_override', '__return_true' );
	}

	public function setup_sections( $sections ) {
		$sections['mailchimp'] = __( 'MailChimp', 'camptix' );
		return $sections;
	}

	public function menu_setup_controls( $section ) {
		global $camptix;

		if ( 'mailchimp' != $section )
			return;

		add_settings_section( 'general', __( 'MailChimp Setup', 'camptix' ), array( $this, 'menu_setup_section_mailchimp' ), 'camptix_options' );
		$camptix->add_settings_field_helper( 'mailchimp_api_key', __( 'MailChimp API Key', 'camptix' ), 'field_text' );

		if ( ! empty( $this->options['mailchimp_api_key'] ) ) {
			add_settings_field( 'mailchimp_list', __( 'List', 'camptix' ), array( $this, 'field_mailchimp_list' ), 'camptix_options', 'general' );
			add_settings_field( 'mailchimp_sync_attendees', __( 'Sync Attendees', 'camptix' ), array( $this, 'field_mailchimp_sync_attendees' ), 'camptix_options', 'general' );
			$camptix->add_settings_field_helper( 'mailchimp_override_email', __( 'Override E-mail', 'camptix' ), 'field_yesno', false,
				__( "Enabling this option will prevent CampTix from sending any outgoing e-mails, including ticket receipts, payment results, etc.", 'camptix' )
			);
		}
	}

	/**
	 * @todo Cache lists api call
	 */
	public function field_mailchimp_list() {
		$lists = $this->api( 'lists/list' );
		if ( ! $lists || empty( $lists->data ) )
			return;

		$lists = $lists->data;
		$value = $this->options['mailchimp_list'];
		?>
		<select name="camptix_options[mailchimp_list]">
		<option value=""><?php _e( 'None', 'camptix' ); ?></option>
		<?php foreach ( $lists as $list ) : ?>
			<option value="<?php echo esc_attr( $list->id ); ?>" <?php selected( $list->id, $value ); ?>><?php echo esc_html( $list->name ); ?></option>
		<?php endforeach; ?>
		</select>

		<p class="description"><?php _e( 'This list will be used to sync attendee data.', 'camptix' ); ?></p>
		<?php
	}

	public function field_mailchimp_sync_attendees() {
		?>
		<?php submit_button( __( 'Sync Now', 'camptix' ), 'secondary', 'camptix_options[mailchimp_sync_attendees]', false ); ?>
		<p class="description"><?php _e( 'This may take a while, depending on the number of tickets, questions and attendees. If you have changed any settings, please save changes before running a sync.', 'camptix' ); ?></p>
		<?php
	}

	public function api( $method, $args = array(), $format = 'json' ) {
		if ( empty( $this->options['mailchimp_api_key'] ) )
			return false;

		// Example API key: 1bae1653ae3f53224b7a678acb599865-us6 (last bit is the data-center)
		if ( ! preg_match( '#-([a-zA-Z0-9]+)$#', $this->options['mailchimp_api_key'], $matches ) )
			return false;

		$url = esc_url_raw( sprintf( 'https://%s.api.mailchimp.com/2.0/%s.%s', $matches[1], $method, $format ) );

		$args = array_merge( array(
			'apikey' => $this->options['mailchimp_api_key'],
		), $args );

		if ( empty( $url ) )
			return false;

		$request = wp_remote_post( $url, array( 'body' => json_encode( $args ) ) );
		if ( 200 != wp_remote_retrieve_response_code( $request ) )
			return $request;

		$body = json_decode( wp_remote_retrieve_body( $request ) );
		return $body;
	}

	public function validate_options( $output, $input ) {
		global $camptix;

		if ( isset( $input['mailchimp_api_key'] ) )
			$output['mailchimp_api_key'] = preg_replace( '#[^a-zA-Z0-9-]+#', '', $input['mailchimp_api_key'] );

		// c9b1c6508c
		if ( isset( $input['mailchimp_list'] ) )
			$output['mailchimp_list'] = preg_replace( '#[^a-zA-Z0-9]+#', '', $input['mailchimp_list'] );

		if ( isset( $input['mailchimp_override_email'] ) )
			$output['mailchimp_override_email'] = (bool) $input['mailchimp_override_email'];

		if ( isset( $input['mailchimp_sync_attendees'] ) ) {

			// This may take a while
			set_time_limit( 600 );

			// Existing groups keeps track of current post_id => mailchimp grouping id mapping.
			$existing_groups = array(
				'tickets' => false,
				'questions' => array(),
			);

			if ( ! empty( $output['mailchimp_groups_mapping'] ) && is_array( $output['mailchimp_groups_mapping'] ) )
				$existing_groups = $output['mailchimp_groups_mapping'];

			$current_groupings_clean = array();
			$current_groupings = $this->api( 'lists/interest-groupings', array(
				'id' => $this->options['mailchimp_list'],
			) );

			if ( is_array( $current_groupings ) )
				foreach ( $current_groupings as $key => $grouping )
					$current_groupings_clean[ $grouping->id ] = $grouping;

			$current_groupings = $current_groupings_clean;
			unset( $current_groupings_clean );

			$current_merge_vars_clean = array();
			$current_merge_vars = $this->api( 'lists/merge-vars', array(
				'id' => array( $this->options['mailchimp_list'] ),
			) );

			if ( ! empty( $current_merge_vars->data ) )
				foreach ( ( reset( $current_merge_vars->data )->merge_vars ) as $merge_var )
					$current_merge_vars_clean[ $merge_var->tag ] = $merge_var;

			$current_merge_vars = $current_merge_vars_clean;
			unset( $current_merge_vars_clean );

			// Check for an existing mapping for a tickets grouping.
			$grouping_id = 0;
			if ( ! empty( $existing_groups['tickets'] ) && array_key_exists( $existing_groups['tickets'], $current_groupings ) )
				$grouping_id = absint( $existing_groups['tickets'] );

			// Attempt to find the grouping from MailChimp by name.
			if ( ! $grouping_id ) {
				foreach ( $current_groupings as $grouping ) {
					if ( 'CampTix: Ticket' == $grouping->name ) {
						$grouping_id = $grouping->id;
						break;
					}
				}
			}

			// Create a tickets grouping if it does not exist.
			if ( ! $grouping_id ) {
				$result = $this->api( 'lists/interest-grouping-add', array(
					'id' => $this->options['mailchimp_list'],
					'name' => 'CampTix: Ticket',
					'type' => 'hidden',
					'groups' => array( 'None' ), // @todo: add groups here vs more api calls
				) );

				if ( ! empty( $result ) ) {
					$grouping_id = $result->id;
				}
			}

			// Add all our tickets to the tickets grouping.
			if ( $grouping_id ) {
				$existing_groups['tickets'] = $grouping_id;

				$tickets = get_posts( array(
					'post_type' => 'tix_ticket',
					'post_status' => 'publish',
					'posts_per_page' => -1, // assume we don't have a bazillion tickets.
				) );

				foreach ( $tickets as $ticket ) {
					$group_name = $ticket->post_title;

					// Look into the current MailChimp groupings and skip adding existing groups.
					if ( isset( $current_groupings[ $grouping_id ] ) && ! empty( $current_groupings[ $grouping_id ]->groups ) ) {
						foreach ( $current_groupings[ $grouping_id ]->groups as $group ) {
							if ( $group_name == $group->name ) {
								continue 2;
							}
						}
					}

					// Add the new group.
					$result = $this->api( 'lists/interest-group-add', array(
						'id' => $this->options['mailchimp_list'],
						'grouping_id' => $grouping_id,
						'group_name' => $group_name,
					) );
				}
			}

			$questions_clean = array();
			$questions = $camptix->get_all_questions();

			foreach ( $questions as $question )
				$questions_clean[ $question->ID ] = $question;

			$questions = $questions_clean;

			// Which question types should we sync to MailChimp.
			$sync_types = array( 'select', 'radio', 'checkbox' );

			// Sync all questions to mailchimp groups.
			foreach ( $questions as $question ) {
				$question->tix_type = get_post_meta( $question->ID, 'tix_type', true );
				$question->tix_values = get_post_meta( $question->ID, 'tix_values', true );

				if ( ! in_array( $question->tix_type, $sync_types ) )
					continue;

				// Don't sync empty groups.
				if ( empty( $question->tix_values ) )
					continue;

				$grouping_id = 0;
				if ( ! empty( $existing_groups['questions'][ $question->ID ] ) && array_key_exists( $existing_groups['questions'][ $question->ID ], $current_groupings ) )
					$grouping_id = absint( $existing_groups['questions'][ $question->ID ] );

				$grouping_name = $this->trim_group( sprintf( 'CampTix: %s', $question->post_title ) );

				// Attempt to find the grouping from MailChimp by name.
				if ( ! $grouping_id ) {
					foreach ( $current_groupings as $grouping ) {
						if ( $grouping_name == $this->trim_group( $grouping->name ) ) {
							$grouping_id = $grouping->id;
							break;
						}
					}
				}

				// Create an empty grouping if one has not been mapped.
				if ( ! $grouping_id ) {
					$result = $this->api( 'lists/interest-grouping-add', array(
						'id' => $this->options['mailchimp_list'],
						'name' => $grouping_name,
						'type' => 'hidden',
						'groups' => array( 'None' ), // @todo: add groups here vs more api calls
					) );

					if ( ! empty( $result ) ) {
						$grouping_id = $result->id;
					}
				}

				// Don't sync groups to unknown groupings.
				if ( ! $grouping_id )
					continue;

				$existing_groups['questions'][ $question->ID ] = $grouping_id;

				// Create a group for each question value.
				foreach ( $question->tix_values as $group_name ) {

					$group_name = $this->trim_group( $group_name );

					// Look into the current MailChimp groupings and skip adding existing groups.
					if ( isset( $current_groupings[ $grouping_id ] ) && ! empty( $current_groupings[ $grouping_id ]->groups ) ) {
						foreach ( $current_groupings[ $grouping_id ]->groups as $group ) {
							if ( $group_name == $this->trim_group( $group->name ) ) {
								continue 2;
							}
						}
					}

					$result = $this->api( 'lists/interest-group-add', array(
						'id' => $this->options['mailchimp_list'],
						'grouping_id' => $grouping_id,
						'group_name' => $group_name,
					) );
				}
			}

			$output['mailchimp_groups_mapping'] = $existing_groups;

			// Let's sync some merge vars now.
			$text_merge_vars = array(
				'TIX_URL' => 'CampTix: Attendee Edit Link',
				'TIX_COUPON' => 'CampTix: Coupon',
				// 10 chars max
			);

			foreach ( $text_merge_vars as $key => $name ) {
				if ( ! array_key_exists( $key, $current_merge_vars ) ) {
					$options = new stdClass;
					$options->field_type = 'text';
					$options->public = false;

					$result = $this->api( 'lists/merge-var-add', array (
						'id' => $this->options['mailchimp_list'],
						'tag' => $key,
						'name' => $name,
						'options' => $options,
					) );

					if ( $result && ! empty( $result->tag ) )
						$current_merge_vars[ $result->tag ] = $result;
				}
			}

			// The somewhat magic attendees loop.

			$paged = 1;
			while ( $attendees = get_posts( array(
				'post_type' => 'tix_attendee',
				'posts_per_page' => 50, // Don't change this, MailChimp supports up to 50 e-mails in lists/member-info
				'post_status' => array( 'publish', 'pending' ),
				'paged' => $paged++,
				'fields' => 'ids', // ! no post objects
				'orderby' => 'ID',
				'order' => 'ASC',
				'cache_results' => false, // no caching
			) ) ) {

				/**
				 * @see $camptix->prepare_metadata_for()
				 * @see $camptix->filter_post_meta
				 */
				$camptix->filter_post_meta = $camptix->prepare_metadata_for( $attendees );
				$batch = array();

				foreach ( $attendees as $attendee_id ) {
					$single = array();
					$single['email'] = new stdClass;
					$single['merge_vars'] = new stdClass;

					$ticket_id = get_post_meta( $attendee_id, 'tix_ticket_id', true );
					$ticket = get_post( $ticket_id );

					$email = get_post_meta( $attendee_id, 'tix_email', true );
					$answers = (array) get_post_meta( $attendee_id, 'tix_questions', true );

					$single['email']->email = $email;

					$groupings = array();

					// Add all answers (and questions) to MailChimp groupings.
					foreach ( $questions as $question ) {
						if ( ! in_array( $question->tix_type, $sync_types ) )
							continue;

						if ( empty( $existing_groups['questions'][ $question->ID ] ) )
							continue;

						$grouping_id = $existing_groups['questions'][ $question->ID ];
						$groups = array();

						if ( ! empty( $answers[ $question->ID ] ) ) {
							$answer = $answers[ $question->ID ];
							if ( is_array( $answer ) )
								$groups = array_values( $answer );
							else
								$groups = array( $answer );

							foreach ( $groups as &$group )
								$group = $this->trim_group( htmlspecialchars( $group ) );

							unset( $group );
						}

						$groupings[] = array(
							'id' => $grouping_id,
							'groups' => $groups,
						);
					}

					// Add the ticket name to the MailChimp groups.
					if ( ! empty( $existing_groups['tickets'] ) ) {
						$groupings[] = array(
							'id' => $existing_groups['tickets'],
							'groups' => array( $ticket->post_title ),
						);
					}

					$single['merge_vars']->groupings = $groupings;

					if ( array_key_exists( 'FNAME', $current_merge_vars ) )
						$single['merge_vars']->FNAME = get_post_meta( $attendee_id, 'tix_first_name', true );

					if ( array_key_exists( 'LNAME', $current_merge_vars ) )
						$single['merge_vars']->LNAME = get_post_meta( $attendee_id, 'tix_last_name', true );

					if ( array_key_exists( 'TIX_URL', $current_merge_vars ) && 'text' == $current_merge_vars['TIX_URL']->field_type ) {
						$edit_token = get_post_meta( $attendee_id, 'tix_edit_token', true );
						$single['merge_vars']->TIX_URL = $camptix->get_edit_attendee_link( $attendee_id, $edit_token );
					}

					if ( array_key_exists( 'TIX_COUPON', $current_merge_vars ) && 'text' == $current_merge_vars['TIX_COUPON']->field_type ) {
						$coupon = get_post_meta( $attendee_id, 'tix_coupon', true );
						$single['merge_vars']->TIX_COUPON = $coupon;
					}

					$batch[ $email ] = $single;
				}

				if ( ! empty( $batch ) ) {

					// Let's query MailChimp for all members we're about to batch update.
					$emails = array();
					foreach ( array_keys( $batch ) as $email )
						$emails[] = array( 'email' => $email );

					$result = $this->api( 'lists/member-info', array(
						'id' => $this->options['mailchimp_list'],
						'emails' => $emails,
					) );

					// Found someone! Let's make sure we don't erase their groupings.
					if ( $result && ! empty( $result->data ) ) {
						foreach ( $result->data as $member ) {
							$groupings = $batch[ $member->email ]['merge_vars']->groupings;
							$groupings_ids = wp_list_pluck( $groupings, 'id' );

							foreach ( $member->merges->GROUPINGS as $grouping ) {

								// Skip groupings that we're about to update.
								if ( in_array( $grouping->id, $groupings_ids ) )
									continue;

								// Populate selected groups.
								$groups = array();
								foreach ( $grouping->groups as $group )
									if ( $group->interested )
										$groups[] = $group->name;

								// Set these existing groups.
								$groupings[] = array(
									'id' => $grouping->id,
									'groups' => $groups,
								);
							}

							// Save the changed groupings back to our batch.
							$batch[ $member->email ]['merge_vars']->groupings = $groupings;
						}
					}

					$result = $this->api( 'lists/batch-subscribe', array(
						'id' => $this->options['mailchimp_list'],
						'batch' => array_values( $batch ),
						'double_optin' => false,
						'update_existing' => true,
						'replace_interests' => true,
					) );
				}

				// Clear prepared metadata.
				$camptix->filter_post_meta = false;
			}

			if ( ! get_settings_errors( 'camptix-mailchimp' ) )
				add_settings_error( 'camptix-mailchimp', 'success', __( "Everything's been synced. You're good to go.", 'camptix' ), 'updated' );
		}

		return $output;
	}

	public function trim_group( $group_name ) {
		return trim( substr( $group_name, 0, 50 ) );
	}

	public function menu_setup_section_mailchimp() {
		_e( 'Your main MailChimp export settings are here. Make sure your API key is valid and active. To obtain your API key, please refer to <a href="http://kb.mailchimp.com/article/where-can-i-find-my-api-key">this article</a>.' );
	}
}
