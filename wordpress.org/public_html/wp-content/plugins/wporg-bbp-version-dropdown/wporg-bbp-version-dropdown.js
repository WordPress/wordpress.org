( function ( $ ) {
	"use strict";

	var Plugin = {
		initialize: function () {
			var $otherVersion = $( '#wp-other-version' );
			var $label = $( 'label[for=wp-other-version]' );

			if ( ! $otherVersion.length ) {
				return;
			}

			Plugin.$otherVersion = $otherVersion;
			Plugin.$label = $label;

			$otherVersion.hide();
			$label.hide();

			var $wpVersion = $( '#wp-version' );
			if ( ! $wpVersion.length ) {
				return;
			}

			Plugin.updateOtherVersion.call( $wpVersion );

			$wpVersion.change( Plugin.updateOtherVersion );
		},

		updateOtherVersion: function () {
			if ( 'other' == $( this ).val() ) {
				Plugin.$label.show();
				Plugin.$otherVersion.show().focus();
			} else {
				Plugin.$label.hide();
				Plugin.$otherVersion.hide();
			}
		}
	};

	$( Plugin.initialize );

} )( jQuery );
