/* globals wporgUserMentionAutocompleteData */
var wporgUserMentionAutocomplete;

(function($){

	wporgUserMentionAutocomplete = (function() {
		var threadParticipants = [],
			settings = wporgUserMentionAutocompleteData || [],
			currentUser = settings.currentUser || false;

		return {
			init: function() {

				wporgUserMentionAutocomplete.initThreadParticipants();

				$( 'textarea.bbp-the-content.wp-editor-area' ).atwho({
					at: '@',
					callbacks: {
						filter: function( query ) {
							return wporgUserMentionAutocomplete.filterUsers( threadParticipants, query );
						}
					}
				});
			},

			filterUsers: function( users, query ) {
				// Bail out if the query is empty.
				if ( '' === query ) {
					return users;
				}

				var results = [],
					regex = new RegExp( '^' + query, 'ig' ); // start of string

				$.each( users, function( key, value ){
					if ( value.toLowerCase().match( regex ) ) {
						if ( value !== currentUser ) {
							results.push( value );
						}
					}
				});

				return results;
			},

			initThreadParticipants: function() {
				var users = [];

				// Most recent should show up first.
				$( $( 'p.bbp-user-nicename' ).get().reverse() ).each( function() {
					var username = $(this).text().replace(/(^\(@|\)$)/g, '');
					if (
						-1 === $.inArray( username, users ) &&
						username !== currentUser
					) {
						users.push( username );
					}
				});

				// Include users mentioned
				$( 'a.mention' ).each( function() {
					var username = $(this).text().replace(/^@/, '');
					if (
						-1 === $.inArray( username, users ) &&
						username !== currentUser
					) {
						users.push( username );
					}
				} );

				threadParticipants = users;
			},

		};
	})();

	$(document).ready( wporgUserMentionAutocomplete.init );

})(jQuery);
