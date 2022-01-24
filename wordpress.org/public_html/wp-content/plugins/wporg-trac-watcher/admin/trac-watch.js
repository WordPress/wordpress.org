jQuery(document).ready( function($) {
	var row;

	$('.propstable').on( 'click', '.user .add, .user .edit, .user .delete', function(e) {
		e.preventDefault();

		var template = $('#propedit-template'),
			user = $(this).parents( '.user' ),
			what = $(this).hasClass('add') ? 'add' : 'edit';

		row = user.parents('tr');

		// Reset
		template.find('input').prop('disabled', '').val( '' );

		// Fill, these could be done at POST time, but easier to just have them in the form.
		template.find('input[name="revision"]').val( row.data('revision') );
		template.find('input[name="svn"]').val( row.data('svn') );
		template.find('input[name="what"]').val( what );
		template.find('input[name="_wpnonce"]').val( TracWatchData.edit_nonce );

		// Maybe hide the delete button.
		template.find('button.delete').toggle( !! user.data('prop') );

		if ( user.data('prop' ) ) {
			template.find('input[name="prop_name"], input[name="prop_name_orig"]').val( user.data('prop') );
			template.find('input[name="user_id"]').val( user.data('user') );
		} else if ( 'add' === what ) {
			template.find('input[name="prop_name"], input[name="prop_name_orig"]').val( window.getSelection().toString() );
		}

		tb_show( '', '#TB_inline?height=350&width=500&inlineId=propedit-template&modal=true' );
	} );

	$(document).on( 'click', '#TB_ajaxContent button.save, #TB_ajaxContent button.delete', function() {
		var form = $('#TB_ajaxContent form');
		if ( $(this).hasClass('delete') ) {
			form.find('input[name="what"]').val( 'delete' );
		}

		// Fetch the form before we disable it.
		var payload = form.serialize();

		// Disable the form during HTTP request.
		form.find('input').prop('disabled', 'disabled' );

		form.parent().css( 'opacity', 0.3 );

		$.post( 'admin-post.php?action=svn_save', payload, function( data ) {

			if ( data ) {
				row.html( data );
			} else {
				alert( 'Something went wrong.. Better check that..' );
			}

			row = false;
			tb_remove();
		} );
	} );

	$(document).on( 'click', '#TB_ajaxContent button.cancel', function() {
		row = false;
		tb_remove();
	} );

	$('.propstable').on( 'click', '.actions .reparse', function(e) {
		e.preventDefault();

		var row = $(this).parents('tr');
		row.addClass( 'disabled' );

		var payload = {
			svn: row.data('svn'),
			revision: row.data('revision'),
			_wpnonce: TracWatchData.reparse_nonce,
		};

		$.post( 'admin-post.php?action=svn_reparse', payload, function( data ) {

			if ( data ) {
				row.html( data );
			} else {
				alert( 'Something went wrong.. Better check that..' );
			}

			row.removeClass( 'disabled' );
		} );
	} );
} );