$gp.wporgjswarnings = {
	save_handler: function() {
		var button = jQuery(this);

		if (!$gp.editor.current) return;
		var editor = $gp.editor.current;
		button.prop('disabled', true);

		$gp.notices.notice('Validating&hellip;');

		var name = "translation["+editor.original_id+"][]";
		var entries = jQuery("textarea[name='"+name+"']", editor).map(function( prop, el ) {
			var $el = jQuery(el);

			return {
				index: prop,
				name: name,
				translation: $el.val(),
				original: // Singular original, or plural original, or.. the single-original plural view..
					$el.parent('div.textareas').prev('p.original').text() || 
					$el.parent('div.textareas').prev('p').find('span.original').text() ||
					jQuery( $el.parents('div.strings').find('span.original').get(prop) ).text() 
			}
		}).get();

		var warnings = [];
		for ( var i in entries ) {
			var _warnings = $gp.wporgjswarnings.check_entry( entries[ i ] );
			if ( _warnings.length ) {
				warnings[ i ] = _warnings
			}
		}

		if ( warnings.length ) {
			var str = "The following warnings have been generated, continue?\n";

			for ( var index in warnings ) {
				str += "\n" + warnings[ index ] + "\n";
				str += "Original: " + entries[ index ].original.replace( /↵/g, '') + "\n";
				str += "Translation: " + entries[ index ].translation.replace( /↵/g, '' ) + "\n";
			}

			str += "\n[Cancel] to Edit, [OK] to Save";

			if ( ! confirm( str ) ) {
				$gp.notices.notice('Save aborted&hellip;');
				button.prop('disabled', false);
				return;
			}
		}

		// No warnings, or warnings skipped
		$gp.editor.hooks.ok( button );
	},
	check_entry: function( entry ) {
		var warnings = [];
		for ( var i in this.warning_tests ) {
			var test = this.warning_tests[ i ];

			var res = test( entry );
			if ( typeof res == "string" ) {
				warnings.push( res );
			}
		}

		if ( warnings.length ) {
			return warnings;
		}
		return true;
	},

	overwrite: function() {
		// Remove the standard save hook and intercept
		jQuery($gp.editor.table).off( 'click', 'button.ok', $gp.editor.hooks.ok );
		jQuery($gp.editor.table).on( 'click', 'button.ok', this.save_handler );
	},

	warning_tests: []
};

jQuery( function() {
	$gp.wporgjswarnings.overwrite();
} );

// Check all placeholders exist - probably a better way than this.
$gp.wporgjswarnings.warning_tests.push( function( entry ) {
	var warnings = [];

	var re = /%(\d+\$(?:\d+)?)?[bcdefgosuxEFGX]/g;
	var orig_ph = entry.original.match( re );
	var trans_ph = entry.translation.match( re );

	var orig_ph_count = {}, trans_ph_count = {};
	for ( var i in orig_ph ) {
		var ph = orig_ph[ i ];
		orig_ph_count[ ph ] = orig_ph_count[ ph ] || 0;
		if ( orig_ph_count[ ph ] ) {
			continue;
		}
		for ( j in orig_ph ) {
			if ( ph == orig_ph[ j ] ) {
				orig_ph_count[ ph ]++;
			}
		}
	}
	for ( var i in trans_ph ) {
		var ph = trans_ph[ i ];
		trans_ph_count[ ph ] = trans_ph_count[ ph ] || 0;
		if ( trans_ph_count[ ph ] ) {
			continue;
		}
		for ( j in trans_ph ) {
			if ( ph == trans_ph[ j ] ) {
				trans_ph_count[ ph ]++;
			}
		}
	}

	// Missing or Extra placeholder counts
	for ( var ph in orig_ph_count ) {
		var count = orig_ph_count[ ph ];
		var trans_count = trans_ph_count[ ph ] || 0;
		if ( count == trans_count ) {
			continue;
		}
		if ( count > trans_count ) {
			warnings.push( "Missing " + ph + " in translation" );
		}
		if ( trans_count > count ) {
			warnings.push( "Extra " + ph + " in translation" );
		}
	}
	// Extra unknown placeholders
	for ( var ph in trans_ph_count ) {
		if ( ! orig_ph_count[ ph ] ) {
			warnings.push( "Extra " + ph + " in translation" );
		}
	}

	if ( warnings.length ) {
		return warnings.join( ", " );
	}

	return true;
} );

// Check the same leading, ending whitespace.
$gp.wporgjswarnings.warning_tests.push( function( entry ) {
	var warnings = [];
	var original = entry.original.replace( /↵\n/g, "\n" ).replace( /↵/g, "\n" );
	var translation = entry.translation.replace( /↵\n/g, "\n" ).replace( /↵/g, "\n" );

	var startswith = function(string, prefix) {
		return string.slice( 0, prefix.length ) == prefix;
	}
	var endswith = function(string, suffix) {
		return string.slice( -suffix.length ) == suffix;
	}

	if ( startswith( original, "\n" ) && ! startswith( translation, "\n" ) ) {
		warnings.push( "Translation must start on a new line" );
	}
	if ( ! startswith( original, "\n" ) && startswith( translation, "\n" ) ) {
		warnings.push( "Translation must not start on a new line" );
	}
	if ( endswith( original, "\n" ) && ! endswith( translation, "\n" ) ) {
		warnings.push( "Translation must end on a new line" );
	}
	if ( ! endswith( original, "\n" ) && endswith( translation, "\n" ) ) {
		warnings.push( "Translation must not end on a new line" );
	}

	if ( warnings.length ) {
		return warnings.join( ", " );
	}

	return true;
} );

// Check all HTML tags exist, and none were added.
$gp.wporgjswarnings.warning_tests.push( function( entry ) {
	var warnings = [];

	// Convert the encoded HTML back
	var original = entry.original.replace( /&lt;/g, '<' ).replace( /&gt;/g, '>' );
	var translation = entry.translation;

	var re = /<.+?>/g;
	var orig_html = original.match( re ) || [];
	var trans_html = translation.match( re ) || [];

	// We don't care about title, or aria-label attributes, strip them out.
	var attr_re = /(title|aria-label)=(["'])[^\2]+\2/g;
	orig_html = orig_html.map( function( el ) {
		return el.replace( attr_re, '' );
	} );
	trans_html = trans_html.map( function( el ) {
		return el.replace( attr_re, '' );
	} );

	var diff = jQuery(orig_html).not(trans_html).get();
	var rdiff = jQuery(trans_html).not(orig_html).get();

	if ( rdiff.length ) {
		warnings.push( "Unexpected HTML detected: " + rdiff.join( ", " ) );
	}
	if ( diff.length ) {
		warnings.push( "Missing HTML Tags: " + diff.join( ", " ) );
	}

	if ( warnings.length ) {
		return warnings.join( ", " );
	}
	return true;

} );

/*
// Template for further warnings
// Return true for "All is okay", return a string for "Are you sure you want to submit that?"
$gp.wporgjswarnings.warning_tests.push( function( entry ) {
	var warnings = [];

	if ( warnings.length ) {
		return warnings.join( ", " );
	}
	return true;
} );
*/