(function($){
	function initGridOptions() {
		$('#visibility-row input[type="checkbox"]').change( function() {
			var t = $(this),
				id = t.siblings('.grid-row-id').val();

			$( '#' + id ).toggle( t.is(':checked') );
		});


		$('.grid-row-layout .edit').click( function() {
			var picker = $(this).siblings('.picker');

			if ( ! picker.is(':visible') )
				picker.slideDown('fast');

			return false;
		});

		$('.grid-row-layout .cancel').click( function() {
			var picker = $(this).parents('.picker');

			picker.slideUp('fast');
			return false;
		});

		$('.grid-row-selector').click( function() {
			var t = $(this);
				picker = t.parents('.picker'),
				layout = t.parents('.grid-row-layout'),
				selected = layout.children('.grid-row'),
				signature = layout.children('.signature'),
				active = picker.find('.active');

			// Update the selected row's contents
			selected.html( t.children('.grid-row').html() );
			signature.val( t.children('.grid-row-signature').val() );

			// Update the picker's active row
			active.removeClass('active');
			t.addClass('active');

			// Close the picker
			picker.slideUp('fast');
			return false;
		});
	};

	function initFeaturedButton() {
		$('#featured-button-visible').change( function() {
			var t = $(this);
			t.parents('.featured-button').toggleClass('visible', t.is(':checked') );
		});
	}

	$(document).ready( function(){
		initGridOptions();
		initFeaturedButton();
	});
})(jQuery);