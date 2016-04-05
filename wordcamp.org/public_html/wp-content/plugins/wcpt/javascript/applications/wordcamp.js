// Modified from https://polldaddy.com/js/survey/jane-theme.js

jQuery( document ).ready( function( $ ) {
	var questions = Array();

	questions[ "pd-question-6" ]  = Array( "7" );
	questions[ "pd-question-9" ]  = Array( "10", "11", "12", "13" );
	questions[ "pd-question-13" ] = Array( "14" );
	questions[ "pd-question-15" ] = Array( "16" );
	questions[ "pd-question-20" ] = Array( "21" );
	questions[ "pd-question-22" ] = Array( "23" );
	questions[ "pd-question-27" ] = Array( "28" );

	var yesanswers                 = Array();
	yesanswers[ "pd-question-6" ]  = Array( "Yes, more than one", "Yes, I've been to one" );
	yesanswers[ "pd-question-9" ]  = Array( "Yes" );
	yesanswers[ "pd-question-13" ] = Array( "Once per month", "Several per month", "One every couple of months" );
	yesanswers[ "pd-question-15" ] = Array( "Yes, and I've attended some of them", "Yes, though I haven't attended any", "No, we've had a few but they weren't very well-attended" );
	yesanswers[ "pd-question-20" ] = Array( "Yes, I've planned events of similar size/scope", "I've organized similar types of events, but smaller", "I've organized other events" );
	yesanswers[ "pd-question-22" ] = Array( "Yes, I have co-organizers already" );
	yesanswers[ "pd-question-27" ] = Array( "Yes, I know lots of local WordPress users/developers", "Yes, I know a couple of people who would be qualified" );

	$( "input" ).click( function() {
		qid = $( this ).closest( ".PDF_question" ).attr( "id" );

		if ( questions[ qid ] ) {
			enabled = false;

			for ( i = 0; i < yesanswers[ qid ].length; i++ ) {
				if ( $( this ).val() == yesanswers[ qid ][ i ] ) {
					for ( n = 0; n < questions[ qid ].length; n++ ) {
						$( "#pd-question-" + questions[ qid ][ n ] ).css( "display", "block" );
						$( "#pd-divider-"  + questions[ qid ][ n ] ).css( "display", "block" );
					}

					enabled = true;
				}
			}

			if ( enabled == false ) {
				for ( i = 0; i < questions[ qid ].length; i++ ) {
					$( "#pd-question-" + questions[ qid ][ i ] ).css( "display", "none" );
					$( "#pd-divider-"  + questions[ qid ][ i ] ).css( "display", "none" );
				}
			}
		}
	} );
} );
