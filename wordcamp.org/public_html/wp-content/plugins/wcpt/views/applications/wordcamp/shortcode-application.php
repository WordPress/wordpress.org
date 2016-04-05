<?php

namespace WordPress_Community\Applications\WordCamp;
defined( 'WPINC' ) or die();

// Modified from https://wordcampcentral.polldaddy.com/s/wordcamp-organizer-application

?>

<form id="wordcamp-application" method="post">
	<div class="PDF_pageOuter">
		<div class="PDF_pageInner">
			<div class="PDF_question" id="pd-question-10000">
				<div class="qContent">
					<div class="PDF_QT1900">
						<h2>Part I: About You</h2>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-10000"></div>

			<div class="PDF_question" id="pd-question-1">
				<div class="qNumber">
					Q.1
				</div>

				<div class="qContent">
					<div class="qText">
						Please enter your name.
						<span class="PDF_mand">*</span>
					</div>

					<div class="PDF_QT800">
						<div>
							<label for="q_1079074_first_name">First Name</label>
							<br />
							<input type="text" class="firstName" maxlength="50" name="q_1079074_first_name" id="q_1079074_first_name" value="" required />
						</div>

						<div>
							<label for="q_1079074_last_name">Last Name</label>
							<br />
							<input type="text" class="lastName" maxlength="50" name="q_1079074_last_name" id="q_1079074_last_name" value="" required />
						</div>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-1"></div>

			<div class="PDF_question" id="pd-question-2">
				<div class="qNumber">
					Q.2
				</div>

				<div class="qContent">
					<div class="qText">
						Please enter your email address.
						<span class="PDF_mand">*</span>
					</div>

					<div class="PDF_QT1400">
						<label for="q_1079059_email">(e.g. john@example.com)</label>
						<br />
						<input type="email" maxlength="100" name="q_1079059_email" id="q_1079059_email" value="" class="survey-email required" autocomplete="off" required />
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-2"></div>

			<div class="PDF_question" id="pd-question-3">
				<div class="qNumber">
					Q.3
				</div>

				<div class="qContent">
					<div class="qText">
						Please enter your mailing address (at least your city/state or city/country).
					</div>

					<div class="PDF_QT900">
						<div>
							<label for="q_1079060_add1">Address Line 1</label>
							<br />
							<input type="text" maxlength="100" class="a" name="q_1079060_add1" id="q_1079060_add1" value="" />
						</div>

						<div>
							<label for="q_1079060_add2">Address Line 2</label>
							<br />
							<input type="text" maxlength="100" class="b" name="q_1079060_add2" id="q_1079060_add2" value="" />
						</div>

						<div>
							<label for="q_1079060_city">City</label>
							<br />
							<input type="text" maxlength="50" class="c" name="q_1079060_city" id="q_1079060_city" value="" />
						</div>

						<div>
							<label for="q_1079060_state">State</label>
							<br />
							<input type="text" maxlength="50" class="d" name="q_1079060_state" id="q_1079060_state" value="" />
						</div>

						<div>
							<label for="q_1079060_zip">Zip Code</label>
							<br />
							<input type="text" maxlength="20" class="e" name="q_1079060_zip" id="q_1079060_zip" value="" />
						</div>

						<div>
							<label for="q_1079060_country">Country</label>
							<br />

							<select name="q_1079060_country" id="q_1079060_country">
								<option value=""></option>

								<?php foreach ( $countries as $country ) : ?>
									<option value="<?php echo esc_attr( $country['alpha2'] ); ?>">
										<?php echo esc_html( $country['name'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-3"></div>

			<div class="PDF_question" id="pd-question-4">
				<div class="qNumber">
					Q.4
				</div>

				<div class="qContent">
					<div class="qText">
						How long have you been using WordPress?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="7+ years" id="q_1045947_1865361" />

								<label for="q_1045947_1865361" value="1865361">
									7+ years </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="6 years" id="q_1045947_1865362" />

								<label for="q_1045947_1865362" value="1865362">
									6 years </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="5 years" id="q_1045947_1865363" />

								<label for="q_1045947_1865363" value="1865363">
									5 years </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="4 years" id="q_1045947_1865369" />

								<label for="q_1045947_1865369" value="1865369">
									4 years </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="3 years" id="q_1045947_1865370" />

								<label for="q_1045947_1865370" value="1865370">
									3 years </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="2 years" id="q_1045947_1865371" />

								<label for="q_1045947_1865371" value="1865371">
									2 years </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="1 year" id="q_1045947_1865372" />

								<label for="q_1045947_1865372" value="1865372">
									1 year </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="less than a year" id="q_1045947_1865373" />

								<label for="q_1045947_1865373" value="1865373">
									Less than a year </label>
							</li>
							<li>
								<input type="radio" name="q_1045947_years_using_wp" value="I don't use WordPress yet" id="q_1045947_1865374" />

								<label for="q_1045947_1865374" value="1865374">
									I don't use WordPress yet </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-4"></div>

			<div class="PDF_question" id="pd-question-5">
				<div class="qNumber">
					Q.5
				</div>

				<div class="qContent">
					<div class="qText">
						How have you been involved in the WordPress community so far?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908777" value="I use WordPress for my website(s)" />

								<label for="q_1068246_1908777">I use WordPress for my website(s)</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908778" value="I help other people use WordPress for their websites" />

								<label for="q_1068246_1908778">I help other people use WordPress for their websites</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908779" value="I make plugins" />

								<label for="q_1068246_1908779">I make plugins</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908831" value="I make themes" />

								<label for="q_1068246_1908831">I make themes</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908832" value="I contribute to the WordPress core codebase" />

								<label for="q_1068246_1908832">I contribute to the WordPress core codebase</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908833" value="I volunteer in the support forums at wordpress.org" />

								<label for="q_1068246_1908833">I volunteer in the support forums at wordpress.org</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908834" value="I contribute to the WordPress core UI group" />

								<label for="q_1068246_1908834">I contribute to the WordPress core UI group</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908835" value="I'm involved with local WordPress meetups" />

								<label for="q_1068246_1908835">I'm involved with local WordPress meetups</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1908836" value="I've been involved with previous WordCamps" />

								<label for="q_1068246_1908836">I've been involved with previous WordCamps</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068246_ways_involved[]" id="q_1068246_1926765" value="WordPress helps me make a living" />

								<label for="q_1068246_1926765">WordPress helps me make a living</label>
							</li>

							<li>
								<label for="q_1068246_other">Other:</label>

								<input type="text" class="other" name="q_1068246_ways_involved_other" id="q_1068246_other" value="" style="width: 75%" />
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-5"></div>

			<div class="PDF_question" id="pd-question-6">
				<div class="qNumber">
					Q.6
				</div>

				<div class="qContent">
					<div class="qText">
						Have you ever attended a WordCamp before?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1046032_attended_camp_before" value="Yes, more than one" id="q_1046032_1865479" />

								<label for="q_1046032_1865479" value="1865479">
									Yes, more than one </label>
							</li>
							<li>
								<input type="radio" name="q_1046032_attended_camp_before" value="Yes, I've been to one" id="q_1046032_1865480" />

								<label for="q_1046032_1865480" value="1865480">
									Yes, I've been to one </label>
							</li>
							<li>
								<input type="radio" name="q_1046032_attended_camp_before" value="No, I haven't been to a WordCamp yet" id="q_1046032_1865481" />

								<label for="q_1046032_1865481" value="1865481">
									No, I haven't been to a WordCamp yet </label>
							</li>
						</ul>

					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-6"></div>

			<div class="PDF_question" id="pd-question-7">
				<div class="qNumber">
					Q.7
				</div>

				<div class="qContent">
					<div class="qText">
						What WordCamps have you been to? Please list one per line in this format: City, Year (ex. San Francisco, 2010)
					</div>

					<div class="PDF_QT200">
						<textarea name="q_1046033_camps_been_to" class="small" rows="10" cols="40" title="What WordCamps have you been to? Please list one pe&hellip;"></textarea>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-7"></div>

			<div class="PDF_question" id="pd-question-8">
				<div class="qNumber">
					Q.8
				</div>

				<div class="qContent">
					<div class="qText">
						What do you hope to accomplish by organizing a WordCamp?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908730" value="Grow my local WordPress community" />

								<label for="q_1068223_1908730">Grow my local WordPress community</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908731" value="Introduce new people to WordPress/teach new users" />

								<label for="q_1068223_1908731">Introduce new people to WordPress/teach new users</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908732" value="Find collaborators for WordPress projects" />

								<label for="q_1068223_1908732">Find collaborators for WordPress projects</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908771" value="Raise my visibility in the community" />

								<label for="q_1068223_1908771">Raise my visibility in the community</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908772" value="Make connections with visiting speakers (like Matt Mullenweg)" />

								<label for="q_1068223_1908772">Make connections with visiting speakers (like Matt Mullenweg)</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908773" value="Make money from surplus ticket sales/sponsorships" />

								<label for="q_1068223_1908773">Make money from surplus ticket sales/sponsorships</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908774" value="Have fun/throw a good party" />

								<label for="q_1068223_1908774">Have fun/throw a good party</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908775" value="Be part of the zeitgeist, as WordCamps are very popular right now" />

								<label for="q_1068223_1908775">Be part of the zeitgeist, as WordCamps are very popular right now</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068223_hope_to_accomplish[]" id="q_1068223_1908776" value="Inspire people to do more with WordPress" />

								<label for="q_1068223_1908776">Inspire people to do more with WordPress</label>
							</li>

							<li>
								<label for="q_1068223_other">Other:</label>

								<input type="text" class="other" name="q_1068223_hope_to_accomplish_other" id="q_1068223_other" value="" style="width: 75%" />
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-8"></div>

			<div class="PDF_question" id="pd-question-10001">
				<div class="qContent">
					<div class="PDF_QT1900">
						<h2>Part II: About Your Local Community</h2>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-10001"></div>

			<div class="PDF_question" id="pd-question-9">
				<div class="qNumber">
					Q.9
				</div>

				<div class="qContent">
					<div class="qText">
						Is there an active WordPress meetup in your area?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1045950_active_meetup" value="Yes" id="q_1045950_1865375" />

								<label for="q_1045950_1865375" value="1865375">
									Yes </label>
							</li>
							<li>
								<input type="radio" name="q_1045950_active_meetup" value="No" id="q_1045950_1865376" />

								<label for="q_1045950_1865376" value="1865376">
									No </label>
							</li>
							<li>
								<input type="radio" name="q_1045950_active_meetup" value="I don't know" id="q_1045950_1865377" />

								<label for="q_1045950_1865377" value="1865377">
									I don't know </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-9"></div>

			<div class="PDF_question" id="pd-question-10">
				<div class="qNumber">
					Q.10
				</div>

				<div class="qContent">
					<div class="qText">
						What is your role in the meetup group?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1045953_role_in_meetup" value="I am the organizer" id="q_1045953_1865378" />

								<label for="q_1045953_1865378" value="1865378">
									I am the organizer </label>
							</li>
							<li>
								<input type="radio" name="q_1045953_role_in_meetup" value="I am a regular attendee and presenter" id="q_1045953_1865379" />

								<label for="q_1045953_1865379" value="1865379">
									I am a regular attendee and presenter </label>
							</li>
							<li>
								<input type="radio" name="q_1045953_role_in_meetup" value="I attend regularly but don't present" id="q_1045953_1865380" />

								<label for="q_1045953_1865380" value="1865380">
									I attend regularly but don't present </label>
							</li>
							<li>
								<input type="radio" name="q_1045953_role_in_meetup" value="I attend sometimes" id="q_1045953_1865381" />

								<label for="q_1045953_1865381" value="1865381">
									I attend sometimes </label>
							</li>
							<li>
								<input type="radio" name="q_1045953_role_in_meetup" value="I attend rarely" id="q_1045953_1865382" />

								<label for="q_1045953_1865382" value="1865382">
									I attend rarely </label>
							</li>
							<li>
								<input type="radio" name="q_1045953_role_in_meetup" value="I do not attend the meetups" id="q_1045953_1865383" />

								<label for="q_1045953_1865383" value="1865383">
									I do not attend the meetups </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-10"></div>

			<div class="PDF_question" id="pd-question-11">
				<div class="qNumber">
					Q.11
				</div>

				<div class="qContent">
					<div class="qText">
						What is the URL for the meetup group&#039;s website?
					</div>

					<div class="PDF_QT100">
						<input value="" maxlength="500" name="q_1045972_meetup_url" class="large" type="url" title="What is the URL for the meetup group&#039;s website?" />
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-11"></div>

			<div class="PDF_question" id="pd-question-12">
				<div class="qNumber">
					Q.12
				</div>

				<div class="qContent">
					<div class="qText">
						About how many members are there?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1045967_meetup_members" value="500+" id="q_1045967_1865388" />

								<label for="q_1045967_1865388" value="1865388">
									500+ </label>
							</li>
							<li>
								<input type="radio" name="q_1045967_meetup_members" value="300-499" id="q_1045967_1865389" />

								<label for="q_1045967_1865389" value="1865389">
									300-499 </label>
							</li>
							<li>
								<input type="radio" name="q_1045967_meetup_members" value="100-299" id="q_1045967_1865390" />

								<label for="q_1045967_1865390" value="1865390">
									100-299 </label>
							</li>
							<li>
								<input type="radio" name="q_1045967_meetup_members" value="50-99" id="q_1045967_1865391" />

								<label for="q_1045967_1865391" value="1865391">
									50-99 </label>
							</li>
							<li>
								<input type="radio" name="q_1045967_meetup_members" value="26-49" id="q_1045967_1865392" />

								<label for="q_1045967_1865392" value="1865392">
									26-49 </label>
							</li>
							<li>
								<input type="radio" name="q_1045967_meetup_members" value="1-25" id="q_1045967_1865393" />

								<label for="q_1045967_1865393" value="1865393">
									1-25 </label>
							</li>
							<li>
								<input type="radio" name="q_1045967_meetup_members" value="I don't know" id="q_1045967_1926755" />

								<label for="q_1045967_1926755" value="1926755">
									I don't know </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-12"></div>

			<div class="PDF_question" id="pd-question-13">
				<div class="qNumber">
					Q.13
				</div>

				<div class="qContent">
					<div class="qText">
						How often does the group have meetups?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1045956_how_often_meetup" value="Once per month" id="q_1045956_1865384" />

								<label for="q_1045956_1865384" value="1865384">
									Once per month </label>
							</li>
							<li>
								<input type="radio" name="q_1045956_how_often_meetup" value="Several per month" id="q_1045956_1865385" />

								<label for="q_1045956_1865385" value="1865385">
									Several per month </label>
							</li>
							<li>
								<input type="radio" name="q_1045956_how_often_meetup" value="One every couple of months" id="q_1045956_1865386" />

								<label for="q_1045956_1865386" value="1865386">
									One every couple of months </label>
							</li>
							<li>
								<input type="radio" name="q_1045956_how_often_meetup" value="There's a group but it never meets" id="q_1045956_1865387" />

								<label for="q_1045956_1865387" value="1865387">
									There's a group but it never meets </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-13"></div>

			<div class="PDF_question" id="pd-question-14">
				<div class="qNumber">
					Q.14
				</div>

				<div class="qContent">
					<div class="qText">
						About how many people usually attend the meetups?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1045971_how_many_attend" value="500+" id="q_1045971_1865397" />

								<label for="q_1045971_1865397" value="1865397">
									500+ </label>
							</li>
							<li>
								<input type="radio" name="q_1045971_how_many_attend" value="300-499" id="q_1045971_1865398" />

								<label for="q_1045971_1865398" value="1865398">
									300-499 </label>
							</li>
							<li>
								<input type="radio" name="q_1045971_how_many_attend" value="100-299" id="q_1045971_1865399" />

								<label for="q_1045971_1865399" value="1865399">
									100-299 </label>
							</li>
							<li>
								<input type="radio" name="q_1045971_how_many_attend" value="50-99" id="q_1045971_1865400" />

								<label for="q_1045971_1865400" value="1865400">
									50-99 </label>
							</li>
							<li>
								<input type="radio" name="q_1045971_how_many_attend" value="26-49" id="q_1045971_1865401" />

								<label for="q_1045971_1865401" value="1865401">
									26-49 </label>
							</li>
							<li>
								<input type="radio" name="q_1045971_how_many_attend" value="1-25" id="q_1045971_1865402" />

								<label for="q_1045971_1865402" value="1865402">
									1-25 </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-14"></div>

			<div class="PDF_question" id="pd-question-15">
				<div class="qNumber">
					Q.15
				</div>

				<div class="qContent">
					<div class="qText">
						Have other community tech events (BarCamp, meetups, open source conferences) been successful in your city?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1079086_other_tech_events" value="Yes, and I've attended some of them" id="q_1079086_1926723" />

								<label for="q_1079086_1926723" value="1926723">
									Yes, and I've attended some of them </label>
							</li>
							<li>
								<input type="radio" name="q_1079086_other_tech_events" value="Yes, though I haven't attended any" id="q_1079086_1926724" />

								<label for="q_1079086_1926724" value="1926724">
									Yes, though I haven't attended any </label>
							</li>
							<li>
								<input type="radio" name="q_1079086_other_tech_events" value="No, we've had a few but they weren't very well-attended" id="q_1079086_1926725" />

								<label for="q_1079086_1926725" value="1926725">
									No, we've had a few but they weren't very well-attended </label>
							</li>
							<li>
								<input type="radio" name="q_1079086_other_tech_events" value="We haven't had any community tech events yet" id="q_1079086_1926744" />

								<label for="q_1079086_1926744" value="1926744">
									We haven't had any community tech events yet </label>
							</li>
							<li>
								<input type="radio" name="q_1079086_other_tech_events" value="I am not sure what events have been held here before, or if they've been successful" id="q_1079086_1926745" />

								<label for="q_1079086_1926745" value="1926745">
									I am not sure what events have been held here before, or if they've been successful </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-15"></div>

			<div class="PDF_question" id="pd-question-16">
				<div class="qNumber">
					Q.16
				</div>

				<div class="qContent">
					<div class="qText">
						List any community tech events that have/have not been successful in your city.
					</div>

					<div class="PDF_QT200">
						<textarea name="q_1079082_other_tech_events_success" class="small" rows="10" cols="40" title="List any community tech events that have/have not b&hellip;"></textarea>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-16"></div>

			<div class="PDF_question" id="pd-question-10002">
				<div class="qContent">
					<div class="PDF_QT1900">
						<h2>Part III: About Your Potential WordCamp</h2>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-10002"></div>

			<div class="PDF_question" id="pd-question-17">
				<div class="qNumber">
					Q.17
				</div>

				<div class="qContent">
					<div class="qText">
						Enter the city, state/province, and country where you would like to organize a WordCamp.
						<span class="PDF_mand">*</span>
					</div>

					<div class="PDF_QT100">
						<input value="" maxlength="500" name="q_1079103_wordcamp_location" class="large required" type="text" title="Enter the city, state/province, and country where y&hellip;" required />
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-17"></div>

			<div class="PDF_question" id="pd-question-18">
				<div class="qNumber">
					Q.18
				</div>

				<div class="qContent">
					<div class="qText">
						When do you want to have a WordCamp in your city? (month/year)
					</div>

					<div class="PDF_QT100">
						<input value="" maxlength="500" name="q_1046006_wordcamp_date" class="large" type="text" title="When do you want to have a WordCamp in your city? (&hellip;" />
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-18"></div>

			<div class="PDF_question" id="pd-question-19">
				<div class="qNumber">
					Q.19
				</div>

				<div class="qContent">
					<div class="qText">
						How many people do you think would attend?
					</div>

					<div class="PDF_QT100">
						<input value="" maxlength="500" name="q_1046007_how_many_attendees" class="large" type="text" title="How many people do you think would attend?" />
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-19"></div>

			<div class="PDF_question" id="pd-question-20">
				<div class="qNumber">
					Q.20
				</div>

				<div class="qContent">
					<div class="qText">
						Have you ever organized an event like this before?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1046038_organized_event_before" value="Yes, I've planned events of similar size/scope" id="q_1046038_1865485" />

								<label for="q_1046038_1865485" value="1865485">
									Yes, I've planned events of similar size/scope </label>
							</li>
							<li>
								<input type="radio" name="q_1046038_organized_event_before" value="I've organized similar types of events, but smaller" id="q_1046038_1865486" />

								<label for="q_1046038_1865486" value="1865486">
									I've organized similar types of events, but smaller </label>
							</li>
							<li>
								<input type="radio" name="q_1046038_organized_event_before" value="I've organized other events" id="q_1046038_1865487" />

								<label for="q_1046038_1865487" value="1865487">
									I've organized other events </label>
							</li>
							<li>
								<input type="radio" name="q_1046038_organized_event_before" value="I've never organized an event before" id="q_1046038_1865495" />

								<label for="q_1046038_1865495" value="1865495">
									I've never organized an event before </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-20"></div>

			<div class="PDF_question" id="pd-question-21">
				<div class="qNumber">
					Q.21
				</div>

				<div class="qContent">
					<div class="qText">
						Please give a brief description of the events you&#039;ve been involved in organizing and what your role was.
					</div>

					<div class="PDF_QT200">
						<textarea name="q_1046099_describe_events" class="small" rows="10" cols="40" title="Please give a brief description of the events you&#039;v&hellip;"></textarea>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-21"></div>

			<div class="PDF_question" id="pd-question-22">
				<div class="qNumber">
					Q.22
				</div>

				<div class="qContent">
					<div class="qText">
						Do you have at least 2 co-organizers?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1046101_have_co_organizers" value="Yes, I have co-organizers already" id="q_1046101_1908677" />

								<label for="q_1046101_1908677" value="1908677">
									Yes, I have co-organizers already </label>
							</li>
							<li>
								<input type="radio" name="q_1046101_have_co_organizers" value="Nope, but I know some people who might be interested" id="q_1046101_1865560" />

								<label for="q_1046101_1865560" value="1865560">
									Nope, but I know some people who might be interested </label>
							</li>
							<li>
								<input type="radio" name="q_1046101_have_co_organizers" value="Nope, I probably need help to find co-organizers" id="q_1046101_1865561" />

								<label for="q_1046101_1865561" value="1865561">
									Nope, I probably need help to find co-organizers </label>
							</li>
							<li>
								<input type="radio" name="q_1046101_have_co_organizers" value="Nope, I want to do it by myself" id="q_1046101_1865562" />

								<label for="q_1046101_1865562" value="1865562">
									Nope, I want to do it by myself </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-22"></div>

			<div class="PDF_question" id="pd-question-23">
				<div class="qNumber">
					Q.23
				</div>

				<div class="qContent">
					<div class="qText">
						What&#039;s your relationship to your co-organizers?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="checkbox" name="q_1068188_relationship_co_organizers[]" id="q_1068188_1908678" value="We work for the same company" />

								<label for="q_1068188_1908678">We work for the same company</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068188_relationship_co_organizers[]" id="q_1068188_1908679" value="We're friends" />

								<label for="q_1068188_1908679">We're friends</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068188_relationship_co_organizers[]" id="q_1068188_1908680" value="We've collaborated on projects before" />

								<label for="q_1068188_1908680">We've collaborated on projects before</label>
							</li>
							<li>
								<input type="checkbox" name="q_1068188_relationship_co_organizers[]" id="q_1068188_1908689" value="We met through the meetup group/other tech event" />

								<label for="q_1068188_1908689">We met through the meetup group/other tech event</label>
							</li>
							<li>
								<label for="q_1068188_other">Other:</label>

								<input type="text" class="other" name="q_1068188_relationship_co_organizers_other" id="q_1068188_other" value="" style="width: 75%" />
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-23"></div>

			<div class="PDF_question" id="pd-question-24">
				<div class="qNumber">
					Q.24
				</div>

				<div class="qContent">
					<div class="qText">
						Please enter the names and email addresses of your co-organizers here.
					</div>

					<div class="PDF_QT200">
						<textarea name="q_1068187_co_organizer_contact_info" class="small" rows="10" cols="40" title="Please enter the names and email addresses of your &hellip;"></textarea>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-24"></div>

			<div class="PDF_question" id="pd-question-25">
				<div class="qNumber">
					Q.25
				</div>

				<div class="qContent">
					<div class="qText">
						Are you confident you can raise money from local sponsors to cover the event costs?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1068214_raise_money" value="Yes, I'm cool with the fundraising" id="q_1068214_1908711" />

								<label for="q_1068214_1908711" value="1908711">
									Yes, I'm cool with the fundraising </label>
							</li>
							<li>
								<input type="radio" name="q_1068214_raise_money" value="I'm not sure -- I haven't done this before but I'll do my best" id="q_1068214_1908712" />

								<label for="q_1068214_1908712" value="1908712">
									I'm not sure -- I haven't done this before but I'll do my best </label>
							</li>
							<li>
								<input type="radio" name="q_1068214_raise_money" value="Not really, I hate asking people for money" id="q_1068214_1908713" />

								<label for="q_1068214_1908713" value="1908713">
									Not really, I hate asking people for money </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-25"></div>

			<div class="PDF_question" id="pd-question-26">
				<div class="qNumber">
					Q.26
				</div>

				<div class="qContent">
					<div class="qText">
						If there are businesses already interested in sponsoring, list them here.
					</div>

					<div class="PDF_QT100">
						<input value="" maxlength="500" name="q_1068220_interested_sponsors" class="large" type="text" title="If there are businesses already interested in spons&hellip;" />
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-26"></div>

			<div class="PDF_question" id="pd-question-27">
				<div class="qNumber">
					Q.27
				</div>

				<div class="qContent">
					<div class="qText">
						Do you know local people who would make good presenters/speakers?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1046009_good_presenters" value="Yes, I know lots of local WordPress users/developers" id="q_1046009_1865453" />

								<label for="q_1046009_1865453" value="1865453">
									Yes, I know lots of local WordPress users/developers </label>
							</li>
							<li>
								<input type="radio" name="q_1046009_good_presenters" value="Yes, I know a couple of people who would be qualified" id="q_1046009_1865454" />

								<label for="q_1046009_1865454" value="1865454">
									Yes, I know a couple of people who would be qualified </label>
							</li>
							<li>
								<input type="radio" name="q_1046009_good_presenters" value="No, I don't know anyone local who could speak" id="q_1046009_1865455" />

								<label for="q_1046009_1865455" value="1865455">
									No, I don't know anyone local who could speak </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-27"></div>

			<div class="PDF_question" id="pd-question-28">
				<div class="qNumber">
					Q.28
				</div>

				<div class="qContent">
					<div class="qText">
						Please enter the names of the people you have in mind, and describe them in a few words.
					</div>

					<div class="PDF_QT200">
						<textarea name="q_1046021_presenter_names" class="small" rows="10" cols="40" title="Please enter the names of the people you have in mi&hellip;"></textarea>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-28"></div>

			<div class="PDF_question" id="pd-question-29">
				<div class="qNumber">
					Q.29
				</div>

				<div class="qContent">
					<div class="qText">
						Do you have any connections to potential venue donations (colleges, offices, etc)?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1068197_venue_connections" value="Yes, I've been talking to people already" id="q_1068197_1908695" />

								<label for="q_1068197_1908695" value="1908695">
									Yes, I've been talking to people already </label>
							</li>
							<li>
								<input type="radio" name="q_1068197_venue_connections" value="No, but I have some leads" id="q_1068197_1908696" />

								<label for="q_1068197_1908696" value="1908696">
									No, but I have some leads </label>
							</li>
							<li>
								<input type="radio" name="q_1068197_venue_connections" value="No, I'll be looking for a venue from scratch" id="q_1068197_1908697" />

								<label for="q_1068197_1908697" value="1908697">
									No, I'll be looking for a venue from scratch </label>
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-29"></div>

			<div class="PDF_question" id="pd-question-30">
				<div class="qNumber">
					Q.30
				</div>

				<div class="qContent">
					<div class="qText">
						What possible venues are you considering?
					</div>

					<div class="PDF_QT200">
						<textarea name="q_1068212_venues_considering" class="small" rows="10" cols="40" title="What possible venues are you considering?"></textarea>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-30"></div>

			<div class="PDF_question" id="pd-question-31">
				<div class="qNumber">
					Q.31
				</div>

				<div class="qContent">
					<div class="qText">
						What is your wordpress.org username?
						<span class="PDF_mand">*</span>
					</div>

					<div class="qNote">
						<p>(This is the username you'd use to log in to http://wordpress.org/support/)</p>
					</div>

					<div class="PDF_QT100">
						<input value="" maxlength="500" name="q_4236565_wporg_username" class="large required" type="text" title="What is your wordpress.org username?" required />
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-31"></div>

			<div class="PDF_question" id="pd-question-32">
				<div class="qNumber">
					Q.32
				</div>

				<div class="qContent">
					<div class="qText">
						Anything else you want us to know while we&#039;re looking over your application?
					</div>

					<div class="PDF_QT200">
						<textarea name="q_1079098_anything_else" class="large" rows="10" cols="40" title="Anything else you want us to know while we&#039;re looki&hellip;"></textarea>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-32"></div>

			<div class="PDF_question" id="pd-question-10003">
				<div class="qContent">
					<div class="PDF_QT1900">
						<h2>Bonus Question!</h2>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-10003"></div>

			<div class="PDF_question" id="pd-question-33">
				<div class="qNumber">
					Q.33
				</div>

				<div class="qContent">
					<div class="qText">
						Which of these best describes you?
					</div>

					<div class="PDF_QT400">
						<ul>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Personal Blogger" id="q_1079112_1926766" />

								<label for="q_1079112_1926766" value="1926766">
									Personal Blogger </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Company Blogger" id="q_1079112_1926767" />

								<label for="q_1079112_1926767" value="1926767">
									Company Blogger </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Designer" id="q_1079112_1926768" />

								<label for="q_1079112_1926768" value="1926768">
									Designer </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Beginning Developer" id="q_1079112_1926769" />

								<label for="q_1079112_1926769" value="1926769">
									Beginning Developer </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Advanced Developer" id="q_1079112_1926770" />

								<label for="q_1079112_1926770" value="1926770">
									Advanced Developer </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Project Manager" id="q_1079112_1926771" />

								<label for="q_1079112_1926771" value="1926771">
									Project Manager </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="System Administrator/IT Professional" id="q_1079112_1926772" />

								<label for="q_1079112_1926772" value="1926772">
									System Administrator/IT Professional </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Sales/Marketing/PR" id="q_1079112_1926773" />

								<label for="q_1079112_1926773" value="1926773">
									Sales/Marketing/PR </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="Business Owner" id="q_1079112_1926774" />

								<label for="q_1079112_1926774" value="1926774">
									Business Owner </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" value="WordPress Fan" id="q_1079112_1926775" />

								<label for="q_1079112_1926775" value="1926775">
									WordPress Fan </label>
							</li>
							<li>
								<input type="radio" name="q_1079112_best_describes_you" id="q_1079112_other" value="other" />

								<label for="q_1079112_other">
									Other:
								</label>

								<input type="text" class="other" name="q_1079112_best_describes_you_other" value="" style="width: 75%" />
							</li>
						</ul>
					</div>
				</div>
			</div>

			<div class="PDF_questionDivide" id="pd-divider-33"></div>

			<div class="PDF_question">
				<div class="button">
					<input type="submit" name="submit-application" value="Submit Application" />
				</div>
			</div>

		</div> <!-- PDF.pageInner -->
	</div> <!-- PDF.pageOuter -->

</form>
