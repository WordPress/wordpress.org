/* global $gp, window, $gp_translation_helpers_settings */
$gp.translation_helpers = (
	function( $ ) {
		return {
			init: function( table, fetchNow ) {
				$gp.translation_helpers.table = table;
				$gp.translation_helpers.install_hooks();
				if ( fetchNow ) {
					$gp.translation_helpers.fetch( false, $( '.translations' ) );
				}
			},
			install_hooks: function() {
				$( $gp.translation_helpers.table )
					.on( 'beforeShow', '.editor', $gp.translation_helpers.hooks.initial_fetch )
					.on( 'click', '.helpers-tabs li', $gp.translation_helpers.hooks.tab_select )
					.on( 'click', 'a.comment-reply-link', $gp.translation_helpers.hooks.reply_comment_form )
					.on( 'click', 'a.opt-out-discussion,a.opt-in-discussion', $gp.translation_helpers.hooks.optin_optout_discussion );
			},
			initial_fetch: function( $element ) {
				var $helpers = $element.find( '.translation-helpers' );

				if ( $helpers.hasClass( 'loaded' ) || $helpers.hasClass( 'loading' ) ) {
					return;
				}

				$gp.translation_helpers.fetch( false, $element );
			},
			fetch: function( which, $element ) {
				var $helpers;
				if ( $element ) {
					$helpers = $element.find( '.translation-helpers' );
				} else {
					$helpers = $( '.editor:visible, .translations' ).find( '.translation-helpers' ).first();
				}

				var originalId = $helpers.parent().attr( 'row' ); // eslint-disable-line vars-on-top
				var replytocom = $helpers.parent().attr( 'replytocom' ); // eslint-disable-line vars-on-top
				var requestUrl = $gp_translation_helpers_settings.th_url + originalId + '?nohc'; // eslint-disable-line

				if ( which ) {
					requestUrl = requestUrl + '&helpers[]=' + which;
				} else {
					$helpers.find( 'div.helper:not(.loaded) ' ).each( function() {
						requestUrl = requestUrl + '&helpers[]=' + $( this ).data( 'helper' );
					} );
				}
				requestUrl = requestUrl + '&replytocom=' + replytocom;

				if ( $helpers.find( 'div:first' ).is( ':not(.loaded)' ) ) {
					$helpers.addClass( 'loading' );
				}

				$.getJSON(
					requestUrl,
					function( data ) {
						$helpers.addClass( 'loaded' ).removeClass( 'loading' );
						$.each( data, function( id, result ) {
							jQuery( '.helpers-tabs li[data-tab="' + id + '"]' ).find( '.count' ).text( '(' + result.count + ')' );
							$( '#' + id ).find( '.loading' ).remove();
							$( '#' + id ).find( '.async-content' ).html( result.content );
						} );
						$( '.helper-translation-discussion' ).find( 'form.comment-form' ).removeAttr( 'novalidate' );
					}
				);
			},
			tab_select: function( $tab ) {
				var tabId = $tab.attr( 'data-tab' );

				$tab.siblings().removeClass( 'current' );
				$tab.parents( '.translation-helpers ' ).find( '.helper' ).removeClass( 'current' );

				$tab.addClass( 'current' );
				$( '#' + tabId ).addClass( 'current' );
			},
			reply_comment_form: function( $comment ) {
				var commentId = $comment.attr( 'data-commentid' );
				$( '#comment-reply-' + commentId ).toggle().find( 'textarea' ).focus();
				if ( 'Reply' === $comment.text() ) {
					$comment.text( 'Cancel Reply' );
				} else {
					$comment.text( 'Reply' );
				}
			},
			optin_optout_discussion: function( $link ) {
				var data = {
					action: 'optout_discussion_notifications',
					data: {
						nonce: $gp_translation_helpers_settings.nonce,
						originalId: $link.attr( 'data-original-id' ),
						optType: $link.attr( 'data-opt-type' ),
					},
				};
				$.ajax(
					{
						type: 'POST',
						url: $gp_translation_helpers_settings.ajax_url,
						data: data,
					}
				).done(
					function() {
						$gp.translation_helpers.fetch( 'discussion' );
					}
				);
			},
			hooks: {
				initial_fetch: function() {
					$gp.translation_helpers.initial_fetch( $( this ) );
					return false;
				},
				tab_select: function() {
					$gp.translation_helpers.tab_select( $( this ) );
					return false;
				},
				reply_comment_form: function( event ) {
					event.preventDefault();
					$gp.translation_helpers.reply_comment_form( $( this ) );
					return false;
				},
				optin_optout_discussion: function( event ) {
					event.preventDefault();
					$gp.translation_helpers.optin_optout_discussion( $( this ) );
					return false;
				},
			},
		};
	}( jQuery )
);

jQuery( function( $ ) {
	var _oldShow = $.fn.show;
	$gp.translation_helpers.init( $( '.translations' ), true );
	if ( typeof window.newShowFunctionAttached === 'undefined' ) {
		window.newShowFunctionAttached = true;
		$.fn.show = function( speed, oldCallback ) {
			return $( this ).each( function() {
				var obj = $( this ),
					newCallback = function() {
						if ( $.isFunction( oldCallback ) ) {
							oldCallback.apply( obj );
						}
					};

				obj.trigger( 'beforeShow' );
				_oldShow.apply( obj, [ speed, newCallback ] );
			} );
		};
	}

	$( '.tooltip' ).tooltip( {
		tooltipClass: 'hoverTooltip',
	} );
} );
