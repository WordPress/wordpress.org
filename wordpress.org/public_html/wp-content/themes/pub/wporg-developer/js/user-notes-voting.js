/**
 * Dynamic functionality for voting on user submitted notes.
 *
 */

( function( $ ) {
	$( document ).on( 'click', '.user-note-voting a', function(e) {
		e.preventDefault();

		var item = $(this);

		$.post(ajaxurl, {
				action:   "note_vote",
				comment:  $(this).attr('data-id'),
				vote:     $(this).attr('data-vote'),
				_wpnonce: $(this).parent().attr('data-nonce')
			}, function(data) {
				if ("0" != data) {
					item.closest('.user-note-voting').replaceWith(data);
				}
			}, "text"
		);
		return false;
	});
} )( jQuery );
