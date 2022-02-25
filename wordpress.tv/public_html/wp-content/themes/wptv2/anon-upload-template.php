<?php
/**
 * Template Name: Anon video upload
 *
 * A custom page template containing the "submit video" form
 */

function anon_upload_css() {
	?>
	<style type="text/css">
		html,
		body {
			width: 100%;
			height: 100%;
			margin: 0;
		}

		.page-template-anon-upload-template-php #page {
			height: auto;
			min-height: 100%;
			position: relative;
			width: 100%;
		}

		.page-template-anon-upload-template-php #header {
			margin: 0;
			padding-top: 10px;
		}

		.video-upload {
			width: 100%;
		}

		.noscript-show p {
			margin: 0 !important;
		}

		.container {
			overflow: hidden;
		}

		.video-upload-left {
			max-width: 550px;
			margin: 15px;
		}

		.video-upload-right {
			float: right;
			width: 390px;
			margin: -25px 0 25px;
		}

		@media screen and ( max-width: 920px ) {
			.video-upload-right {
				float: none;
				width: auto;
				margin: 0 10px;
			}
		}

		.video-upload p {
			margin: 16px 0;
			overflow: hidden;
		}

		.video-upload h3 {
			font-size: 22px;
		}

		.video-upload-left p > label,
		.video-upload-left div > label {
			padding: 4px 0 0;
			display: block;
			width: 130px;
			float: left;
			font-weight: bold;
		}

		@media screen and ( max-width: 500px ) {
			.video-upload-left p > label,
			.video-upload-left div > label {
				width: 100%;
			}
		}

		.video-upload-left p > label.wptv-video-wordcamp-cb {
			display: inline;
			float: none;
		}

		.video-upload-left p label .required {
			line-height: 15px;
			vertical-align: bottom;
			margin: 0 3px;
		}

		.video-upload-left input[type="text"],
		.video-upload-left textarea,
		.video-upload-left ul.cats-checkboxes {
			border-radius: 3px;
			border: 1px solid #dfdfdf;
			color: #333;
			background-color: #fff;
			padding: 4px;
			width: 96%;
			max-width: 329px;
		}

		.video-upload-left ul.cats-checkboxes {
			height: 150px;
			overflow: auto;
			margin-top: 4px;
		}

		.video-upload-left ul.cats-checkboxes ul.children {
			margin-left: 15px;
		}

		.video-upload-left input[type="text"]:focus,
		.video-upload-left textarea:focus {
			border-color: #bbb;
		}

		.video-upload-left #wptv_honey_container {
			display: none;
		}

		#video-upload-form p.last {
			margin: 5px 80px 25px;
			text-align: right;
		}

		#video-upload-form p .invalid {
			border: 1px solid red;
		}

		#video-upload-form input[type="submit"] {
			font-size: 15px;
			padding: 4px 12px;
		}

		.page-template-anon-upload-template-php .wptv-hero {
			padding: 20px;
		}

		.page-template-anon-upload-template-php .wptv-hero h2 {
			font-size: 24px;
		}

		.video-upload-right .accepted-formats {
			margin-left: 16px;
		}

		.video-upload-right .accepted-formats li {
			list-style: square;
		}

		.video-upload-right h3 {
			padding-bottom: 4px;
		}

		.video-upload .pass-form label {
			float: none;
			display: inline;
			width: auto;
		}

		#upload-progress .status,
		#upload-progress .percent {
			font-family: monospace;
		}
		#upload-progress a.abort:hover {
			color: red;
		}
	</style>
	<?php
}
add_action( 'wp_head', 'anon_upload_css' );

wp_enqueue_script( 'jquery' );

get_header();

$message = '';

if ( !empty($_REQUEST['error']) ) {
	$message = (int) $_REQUEST['error'];

	switch ( $message ) {
		case 1:
			$message = 'Error: please select a video file.';
			break;
		case 2:
			$message = 'Error: invalid file type.';
			break;
		case 3:
			$message = 'Error: unknown file type.';
			break;
		case 4:
			$message = 'Upload error: the video cannot be saved.';
			break;
		case 5:
			$message = 'Unknown error. Please try again later.';
			break;
		case 6:
			$message = 'Error: invalid submission.';
			break;
		// these shouldn't show, JS form validation should catch them
		case 10:
			$message = 'Error: please enter your name.';
			break;
		case 11:
			$message = 'Error: please enter your email address.';
			break;
		case 12:
			$message = 'Error: please enter a valid email address.';
			break;
		case 13:
			$message = "Error: please leave the first field empty. (It helps us know you're not a spammer.)";
			break;
		case 14:
			$message = "Error: please enter a valid WordPress.org username for the producer, or leave the field empty.";
			break;
		case 15:
			$message = 'Error: form nonce was missing or invalid.';
			break;
	}
	$message = '<div class="error"><p>' . $message . '</p></div>';
} elseif ( !empty($_REQUEST['success']) ) {
	$message = '<div class="success"> <p>Thank you for submitting a video; it was uploaded successfully.</p> <p>Submit another?</p> </div>';
}

$selected_cats = [];
if ( isset( $_GET['post_category'] ) ) {
	// [ selected Id => 0.. ]
	$selected_cats = array_flip( array_map( 'intval', $_GET['post_category'] ) );
}

?>

	<div class="wptv-hero">
		<div class="single container">
			<h2><?php esc_html_e( 'Submit a video' ); ?></h2>
		</div>
	</div>

	<div class="container">
		<div class="video-upload">
			<?php

			// temp pwd?
			if ( post_password_required() ) {
				echo '<div class="pass-form">';
				echo get_the_password_form();
				echo '</div></div></div>';
				get_footer();
				return;
			} else {
				echo $message;
			}

			?>
			<noscript><div class="error"><p>This form requires JavaScript. Please enable it in your browser and reload the page.</p></div></noscript>
			<div class="video-upload-right">
				<h3>Guidelines</h3>
				<p>WordCamp videos: the audio is clear and easy to understand, the camera was on a tripod, the video shows the speaker and slides, divide the video by presentation if possible.</p>
				<p>Screencasts: keep it concise, keep it clear, keep on track (no chock-full of personal promotion please), keep it current.</p>
				<p>Vodcasts and other video-based content: if you have put together a video podcast or other WordPress focused, relevant video - let us <a href="https://wordpress.tv/contact/">know about it</a>.
				<p>If this is the first time you're submitting a video, please check all <a href="https://blog.wordpress.tv/submission-guidelines/">Submission Guidelines</a>.</p>
				<h3>Accepted formats</h3>
				<p>Maximum upload file size: 1GB. You can upload the following video formats:</p>
				<ul class="accepted-formats">
					<li>avi</li>
					<li>mov/qt</li>
					<li>mpeg/mpg</li>
					<li>mp4</li>
					<li>ogv</li>
					<li>wmv</li>
					<li>3gp/3g2</li>
				</ul>
			</div>

			<div class="video-upload-left">
				<?php if ( !$message ) { ?>
					<p>Please review the guidelines listed on the right, then submit your video below:</p>
				<?php } ?>

				<form method="post" action="<?php echo admin_url('admin-post.php'); ?>" data-xhr-action="<?php echo admin_url('admin-post.php?xhr=1'); ?>" id="video-upload-form" enctype="multipart/form-data">
					<?php wp_nonce_field('wptv-upload-video', 'wptvvideon'); ?>
					<input type="hidden" name="action" value="wptv_video_upload" />

					<?php // This field only exists to trap spam bots that will automatically fill it in. It will be hidden from normal users. ?>
					<p id="wptv_honey_container">
						<label for="wptv_honey"><?php esc_html_e( 'Leave this empty' ); ?></label>
						<input type="text" id="wptv_honey" name="wptv_honey" value="" />
					</p>
					<p>
						<input type="checkbox" id="wptv_video_wordcamp" name="wptv_video_wordcamp" <?php if ( !empty( $_GET['wptv_video_wordcamp'] ) ) { echo 'checked="checked"'; } ?> />
						<label for="wptv_video_wordcamp" class="wptv-video-wordcamp-cb"><?php esc_html_e( 'This is a WordCamp video' ); ?></label>
					</p>

					<?php if ( ! is_user_logged_in() ) : ?>
						<p>
							<label for="wptv_uploaded_by"><?php esc_html_e( 'Uploaded by' ); ?><span class="required"> * </span></label>
							<input type="text" id="wptv_uploaded_by" name="wptv_uploaded_by" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_uploaded_by'] ?? '' ) ); ?>" />
						</p>
						<p>
							<label for="wptv_email"><?php esc_html_e( 'Email address' ); ?><span class="required"> * </span></label>
							<input type="text" id="wptv_email" name="wptv_email" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_email'] ?? '' ) ); ?>" />
						</p>
					<?php endif; ?>

					<p>
						<label for="wptv_video_title"><?php esc_html_e( 'Video title' ); ?></label>
						<input type="text" id="wptv_video_title" name="wptv_video_title" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_video_title'] ?? '' ) ); ?>" />
					</p>
					<p>
						<label for="wptv_language"><?php esc_html_e( 'Language' ); ?></label>
						<input type="text" id="wptv_language" name="wptv_language" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_language'] ?? '' ) ); ?>" />
					</p>
					<p>
						<label for="wptv_date"><?php esc_html_e( 'Date Recorded' ); ?></label>
						<input type="date" id="wptv_date" name="wptv_date" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_date'] ?? '' ) ); ?>" />
					</p>

					<div class="location">
						<label for="wptv_location"><?php esc_html_e( 'Location' ); ?></label>
						<ul class="cats-checkboxes">
							<?php

							foreach ( get_categories( [
								'parent'     => get_term_by( 'slug', 'location', 'category' )->term_id,
								'hide_empty' => false,
							] ) as $term ) {
								printf(
									'<li id="category-%1$d"><label class="selectit"><input value="%1$d" type="checkbox" name="post_category[]" id="in-category-%1$d" %2$s> %3$s</label></li>',
									$term->term_id,
									isset( $selected_cats[ $term->term_id ] ) ? 'checked="checked" ' : '',
									$term->name,
								);
							}
							?>
						</ul>
					</div>

					<div class="cats">
						<label for="post_category"><?php esc_html_e( 'Category' ); ?></label>
						<ul class="cats-checkboxes">
							<?php
							foreach ( get_categories( [
								'exclude_tree' => [
									get_term_by( 'slug', 'location', 'category' )->term_id,
									get_term_by( 'slug', 'year', 'category' )->term_id,
								],
								'parent'       => 0,
								'hide_empty'   => false,
							] ) as $term ) {
								printf(
									'<li id="category-%1$d"><label class="selectit"><input value="%1$d" type="checkbox" name="post_category[]" id="in-category-%1$d" %2$s> %3$s</label></li>',
									$term->term_id,
									isset( $selected_cats[ $term->term_id ] ) ? 'checked="checked" ' : '',
									$term->name,
								);
							}
							?>
						</ul>
					</div>

					<p>
						<label for="wptv_producer_username"><?php esc_html_e( 'Producer WordPress.org Username' ); ?></label>
						<input type="text" id="wptv_producer_username" name="wptv_producer_username" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_producer_username'] ?? '' ) ); ?>" />
					</p>
					<p>
						<label for="wptv_speakers"><?php esc_html_e( 'Speakers' ); ?></label>
						<input type="text" id="wptv_speakers" name="wptv_speakers" placeholder="John Smith, Jane Doe" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_speakers'] ?? '' ) ); ?>" />
					</p>
					<p>
						<label for="wptv_event"><?php esc_html_e( 'Event' ); ?></label>
						<input type="text" id="wptv_event" name="wptv_event" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_event'] ?? '' ) ); ?>" />
					</p>
					<p>
						<label for="wptv_video_description"><?php esc_html_e( 'Description' ); ?></label>
						<textarea name="wptv_video_description" id="wptv_video_description" rows="8" cols="40"><?php echo esc_textarea( wp_unslash( $_GET['wptv_video_description'] ?? '' ) ); ?></textarea>
					</p>
					<p>
						<label for="wptv_slides_url"><?php esc_html_e( 'Slides URL' ); ?></label>
						<input type="text" name="wptv_slides_url" id="wptv_slides_url" value="<?php echo esc_attr( wp_unslash( $_GET['wptv_slides_url'] ?? '' ) ); ?>" />
					</p>
					<p>
						<label for="wptv_file"><?php esc_html_e( 'Video file' ); ?><span class="required"> * </span></label>
						<input type="file" name="wptv_file" id="wptv_file" />
					</p>
					<p class="last">
						<input type="submit" id="wptv_video_upload" style="display:none;" value="<?php esc_attr_e( 'Submit' ); ?>" />
					</p>
					<p id="upload-progress" style="display:none;">
						<progress value="0" max="100" style="width:90%;"></progress><span class="percent"></span><br>
  						<span class="status"></span> <a href="#" class="abort">Cancel</a>
					</p>
				</form>
			</div>
		</div>
	</div>

	<script type="text/javascript">
		jQuery( function($) {
			var invalid,
				val,
				uploaded_by = $( '#wptv_uploaded_by' ),
				email       = $( '#wptv_email' ),
				file        = $( '#wptv_file' ),
				recorded    = $( '#wptv_date' ),
				honey       = $( '#wptv_honey' );

			invalid = function( el, e ) {
				el.addClass( 'invalid' );
				el.one( 'click', function() {
					$( this ).removeClass( 'invalid' );
				} );
				e.preventDefault();
			};

			$( '#wptv_video_upload' ).show();
			$( '#video-upload-form input[type="text"]' ).prop( 'maxlength', 100 );
			$( '#video-upload-form input[type="text"]#wptv_slides_url' ).prop( 'maxlength', 200 );
			$( 'ul.cats-checkboxes input' ).prop( 'disabled', false );

			// Float selected items to the start.
			$( '.location ul.cats-checkboxes, .cats ul.cats-checkboxes' ).each( function() {
				var list = $(this),
					selected = list.find( 'input:checked, #in-category-2648' ); // checked & World-Wide-Web

				selected.each( function() {
					var li = $(this).parents( 'li' );
					li.remove();
					list.prepend( li );
				} );
			} );

			// Generate the Event Name
			$( '#wptv_video_wordcamp, .location ul.cats-checkboxes input, .cats ul.cats-checkboxes input, #wptv_date' ).on( 'change', function() {
				if ( $( '#wptv_event' ).data('user-altered') ) {
					return;
				}

				var title = '';
				// Get the Location
				title += $( '.location input:checked' ).parent().text().trim() + " ";

				// .. and the Year
				title += $( '#wptv_date' ).val().substring( 0, 4 );

				// If a location or year has been selected, build the Event Name.
				if ( $.trim( title ) ) {
					if ( $( '#wptv_video_wordcamp' ).prop( 'checked' ) ) {
						title = "WordCamp " + title;
					} else if ( $( '#in-category-107686937:checked, #in-category-467571547:checked' ).length ) {
						/* BuddyCamp * Global Translation Day */
						title = $( '#in-category-107686937:checked, #in-category-467571547:checked' ).parent().text().trim() + " " + title;
					} else {
						title = "WordPress Meetup " + title;
					}

					$( '#wptv_event' ).val( $.trim( title ) );
				}
			});
			$( '#wptv_event' ).on( 'focus', function() {
				// Not perfect, but will do.
				$( '#wptv_event' ).data( 'user-altered', true );
			});

			// Make the Speakers field "Name, Name, Name" and not allow "Name and Name".
			$( '#wptv_speakers' ).on( 'change', function() {
				var $this = $(this);
				$this.val( $this.val().replace( /\s(and|&|\+)\s/g, ', ' ).replace( /[ ]{2,}/g, ' ' ) );
			});

			$( '#wptv_video_wordcamp' ).on( 'change', function() {
				// WordCampTV cat
				$( '#in-category-12784353' ).prop( 'checked', $( this ).prop( 'checked' ) );
			});

			$( '#video-upload-form' ).submit( function( e ) {
				var scroll = false;

				if ( uploaded_by.length && ! uploaded_by.val() ) {
					invalid( uploaded_by, e );
					scroll = true;
				}

				if ( email.length ) {
					val = email.val();

					if ( !val || !/\S+@\S+\.\S+/.test( val ) ) {
						invalid(email, e);
						scroll = true;
					}
				}

				// Changes to this list must be synced with WPTV_Anon_Upload::save()
				if ( ! file.val() || !/\.(avi|mov|qt|mpeg|mpg|mpe|mp4|m4v|asf|asx|wax|wmv|wmx|ogv|3gp|3g2)$/.test( file.val() ) ) {
					invalid(file, e);
					scroll = true;
				}

				// If there's any input in the honeypot field, it was probably put there by a bot, so reject the submission
				if ( honey.val().length > 0 ) {
					invalid( honey, e );
					scroll = true;
				}

				if ( scroll ) {
					jQuery( '.invalid' ).get(0).scrollIntoView();
					return;
				}

				// Start the upload!
				if ( 
					'undefined' != typeof XMLHttpRequest &&
					'undefined' != typeof FormData
				) {
					e.preventDefault();
					processXHRUpload();
				}
			} );
		} );

		function processXHRUpload() {
			var $form        = jQuery( '#video-upload-form' ),
				$file        = $form.find('input[type="file"]'),
				$submit      = $form.find( 'p.last' ),
				$progressBar = $form.find( '#upload-progress' ),
				$progress    = $progressBar.find( 'progress' ),
				$status      = $progressBar.find( '.status' ),
				$percent     = $progressBar.find( '.percent' ),
				$abort       = $progressBar.find( '.abort' ),
				formdata     = new FormData( $form.get(0) ),
				xhr          = new XMLHttpRequest(),
				startTime    = (new Date()).getTime(),
				round_to, disable_form;

			round_to = function(x, precision ) {
				return x.toLocaleString(
					undefined,
					{
						minimumFractionDigits: precision,
						maximumFractionDigits: precision
					}
				);
			};

			disable_form = function( disable ) {
				$form.find( 'input,select,option,textarea' ).prop( 'disabled', !! disable );
			};

			disable_form( true );
			$submit.hide();
			$progress.val( 0 );
			$percent.text( '0%' );
			$progressBar.show();
			$abort.show();

			$status.text( 'Preparing upload..' );

			xhr.upload.addEventListener( 'progress', function(e) {
				var percent     = Math.round( e.loaded / e.total * 100 ),
					size        = round_to( e.total / 1024 / 1024, 1 ),
					uploaded    = round_to( e.loaded / 1024 / 1024, 1 ),
					elapsed     = ( (new Date()).getTime() - startTime ) / 1000,
					// This isn't a perfect speed measurement, but is close enough for our needs.
					speed_kbps  = e.loaded / elapsed / 1024,
					eta_seconds = Math.round( ( e.total - e.loaded ) / 1024 / speed_kbps ),
					eta_minutes = Math.floor( eta_seconds / 60 ),
					eta         = '',
					speed;

				// Give some time for the upload speed to settle.
				if ( elapsed > 10 || percent > 5 ) {
					if ( eta_minutes ) {
						eta += eta_minutes + ( eta_minutes > 1 ? ' mins ' : ' min ' );
					}
					if ( eta_seconds % 60 ) {
						eta += ( eta_seconds - eta_minutes * 60 ) + 's';
					}
				} else {
					eta = 'Calculating..';
				}

				if ( speed_kbps > 1024 ) {
					speed = round_to( speed_kbps / 1024, 2 ) + 'mb/s';
				} else {
					speed = round_to( Math.round( speed_kbps ), 0 ) + 'kb/s';
				}

				$progress.val( percent );

				$percent.text( `${percent}%` );

				$status.text(
					`Uploaded ${uploaded}MB of ${size}MB. ${speed} Remaining: ${eta}`
				);
				if ( percent >= 100 ) {
					$status.text( 'Processing upload.. please wait..' );
					$abort.hide();
				}

			}, false);

			xhr.addEventListener( 'load', function( e ) {
				$status.text( 'Done.. please wait..' );
				$abort.hide();
				// Redirect. Upload done.
				document.location = e.target.responseText;
			}, false);

			xhr.addEventListener( 'error', function( e ) {
				$status.text( 'Upload failed. Network or Browser issue encountered.' );
				console.log( e );

				disable_form( false );
				$submit.show();
				$abort.hide();
			}, false );

			xhr.addEventListener( 'abort', function( e ) {
				$status.text( 'Aborted.' );

				disable_form( false );
				$submit.show();
				$progressBar.hide();
			}, false );

			// Note: form.action will return the `<input name="action">` element, which is why a data attribute is ued.
			xhr.withCredentials = true;
			xhr.open( $form.prop( 'method' ), $form.data( 'xhr-action' ) );
			xhr.send( formdata );

			$abort.click( function( e ) {
				xhr.abort();
				e.preventDefault();
			} );
		}
	</script>

<?php get_footer();
