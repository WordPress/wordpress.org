<?php

namespace WordPress_Community\Applications\Meetup;
defined( 'WPINC' ) or die();

/**
 * Renders the application form for meetup. Renders shortcode meetup-organizer-application
 *
 * @param array $countries
 */
function render_meetup_application_form( $countries ) {

	?>

	<form id="meetup-application" method="post">
		<div class="PDF_pageInner">
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					Please enter your full Name.
					<span class="required-indicator">(required)</span>
					<input type="text" name="q_name" required/>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					Please enter you email address.
					<span class="required-indicator">(required)</span>
					<input type="email" name="q_email" required/>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label> Please enter your mailing address (at least your city/state or city/country). </label>
				<label>
					Address Line 1
					<input type="text" name="q_address_line_1">
				</label>
				<label>
					Address Line 2
					<input type="text" name="q_address_line_2">
				</label>
				<label>
					City
					<span class="required-indicator">(required)</span>
					<input type="text" name="q_city" required/>
				</label>
				<label>
					State/Province
					<input type="text" name="q_state"/>
				</label>
				<label>
					Country
					<span class="required-indicator">(required)</span>

					<select name="q_country" required>
						<option value=""></option>

						<?php foreach ( $countries as $country ) : ?>
							<option value="<?php echo esc_attr( $country['alpha2'] ); ?>">
								<?php echo esc_html( $country['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>
					ZIP/Postal Code
					<input type="text" name="q_zip"/>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					Enter the city, state/province, and country where you would like to organize a Meetup
					<input type="text" name="q_mtp_loc" required/>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					Is there already a WordPress meetup group in this city?
					<span class="required-indicator">(required)</span>
					<span class="label-description">If you don't know, please <a
								href="https://www.meetup.com/topics/wordpress/"
								target="_blank" rel="noopener noreferrer">check Meetup.com</a> first.</span>

					<select name="q_already_a_meetup" required>
						<option value=""></option>
						<option>Nope, no current meetup group</option>
						<option>Yes, it's the meetup I run now</option>
						<option>Yes, but I want to do a different kind of meetup</option>
					</select>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					<!-- todo: Show/hide this via JavaScript based on selected answer above -->
					If there's an existing Meetup.com group, please provide the URL
					<input type="url" name="q_existing_meetup_url"/>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					How would you describe yourself?
					<span class="required-indicator">(required)</span>

					<select name="q_describe_yourself" required>
						<option value=""></option>
						<option>WordPress professional</option>
						<option>Current WordPress user or developer</option>
						<option>New to WordPress</option>
						<option>I don't use WordPress</option>
					</select>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					Your <a href="https://wordpress.org" target="_blank">WordPress.org</a> username
					<span class="required-indicator">(required)</span>
					<input type="text" name="q_wporg_username" required/>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					Your <a href="https://chat.wordpress.org" target="_blank">WordPress Slack</a> username
					<input type="text" name="q_wp_slack_username"/>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<label>
					Anything you'd like to tell us about yourself, or what you hope to do with a meetup group?
					<textarea name="q_additional_comments"></textarea>
				</label>
			</div>
			<div class="PDF_questionDivide"></div>
			<div class="PDF_question">
				<div class="submit-button">
					<?php submit_button( 'Submit Application', 'primary', 'submit-application', false ); ?>
				</div>
			</div>
		</div>
	</form>

	<?php
}