jQuery(document).ready( function( $ ) {
	$('#menu li').hover(
		function() {
			$(this).children('.sub-menu').slideDown('fast');
		}, function () {
			$(this).children('.sub-menu').fadeOut('fast');
		}
	);
});
