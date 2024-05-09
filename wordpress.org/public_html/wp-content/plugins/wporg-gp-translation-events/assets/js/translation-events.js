(
	function ( $, $gp ) {
		jQuery( document ).ready(
			function ( $ ) {
				$gp.notices.init();
				const timezoneElement = $( '#event-timezone' );
				if ( timezoneElement.length && ! timezoneElement.val() ) {
					selectUserTimezone();
				}
				validateEventDates();
				convertToUserLocalTime();
				setInterval( convertToUserLocalTime, 10000 );

				$( '.submit-event' ).on(
					'click',
					function ( e ) {
						e.preventDefault();
						let eventStatus = $( this ).data( 'event-status' );
						let isDraft     = $( 'button.save-draft[data-event-status="draft"]:visible' ).length > 0;
						handleSubmit( eventStatus, isDraft );
					}
				);

				$( '.trash-event' ).on(
					'click',
					function ( e ) {
						e.preventDefault();
						handleTrash()
					}
				);

				$( '.text-snippet' ).on(
					'click',
					function ( e ) {
						e.preventDefault();
						var textArea        = $( this ).closest( 'div' ).find( 'textarea' );
						var textAreaContent = textArea.val();
						textArea.val( textAreaContent + $( this ).data( 'snippet' ) );
					}
				);

				$( '.event-attendees h2, .event-contributors h2' ).on(
					'click',
					function ( e ) {
							e.preventDefault();
							$( this ).closest( 'body' ).toggleClass( 'icons' );
							$( '.convert-to-host, .remove-as-host' ).toggle();
					}
				);

				$( '#quick-add' ).on(
					'toggle',
					function () {
						if ( $( this ).data( 'loaded' ) ) {
							return;
						}
						$( this ).addClass( 'loading' );
						const options = {
							weekday: 'short',
							day: 'numeric',
							month: 'short',
							year: 'numeric'
						};

						fetch( 'https://central.wordcamp.org/wp-json/wp/v2/wordcamps?per_page=30&status=wcpt-scheduled' ).then(
							response => response.json()
						).then(
							function ( data ) {
								data.sort( ( a, b ) => a['Start Date (YYYY-mm-dd)'] - b['Start Date (YYYY-mm-dd)'] );
								const ul = $( '<ul>' );
								for ( const wordcamp of data ) {
									const li = $( '<li>' ).data( 'wordcamp', wordcamp );
									li.append( $( '<a>' ).attr( 'href', wordcamp.link ).text( wordcamp.title.rendered ) );
									li.append( $( '<span>' ).text( ' ' + new Date( 1000 * wordcamp['Start Date (YYYY-mm-dd)'] ).toLocaleDateString( navigator.language, options ) + ' - ' + new Date( 1000 * wordcamp['End Date (YYYY-mm-dd)'] ).toLocaleDateString( navigator.language, options ) ) );
									ul.append( li );
								}
								$( '#quick-add' ).data( 'loaded', true ).removeClass( 'loading' ).append( ul );
							}
						);
					}
				);
				$( document ).on(
					'click',
					'#quick-add a',
					function ( e ) {
						e.preventDefault();
						e.stopPropagation();

						const wordcamp = $( e.target ).closest( 'li' ).data( 'wordcamp' );
						if ( ! wordcamp ) {
							return;
						}

						$( '#event-title' ).val( wordcamp.title.rendered );
						$( '#event-description' ).val( wordcamp.content.rendered );
						$( '#event-start' ).val( new Date( 1000 * wordcamp['Start Date (YYYY-mm-dd)'] ).toISOString().slice( 0,11 ) + '09:00' );
						$( '#event-end' ).val( new Date( 1000 * wordcamp['End Date (YYYY-mm-dd)'] ).toISOString().slice( 0,11 ) + '18:00' );
						$( '#event-timezone' ).val( wordcamp['Event Timezone'] );

					}
				);

			}
		);

		/**
		 * Handles the form submission
		 *
		 * @param eventStatus The new status of the event
		 * @param isDraft	  Whether the current event status is a draft or not
		 */
		function handleSubmit( eventStatus, isDraft ) {
			const $form = $( '.translation-event-form' );
			if ( ! $form[0].reportValidity() ) {
				return;
			}

			if ( '' === $( '#event-title' ).val() ) {
				$gp.notices.error( 'Event title must be set.' );
				return;
			}
			if ( '' === $( '#event-start' ).val() ) {
				$gp.notices.error( 'Event start date and time must be set.' );
				return;
			}
			if ( '' === $( '#event-end' ).val() ) {
				$gp.notices.error( 'Event end date and time must be set.' );
				return;
			}
			if ( $( '#event-end' ).val() <= $( '#event-start' ).val() ) {
				$gp.notices.error( 'Event end date and time must be later than event start date and time.' );
				return;
			}
			if ( eventStatus === 'publish' && isDraft ) {
				const submitPrompt = 'Are you sure you want to publish this event?';
				if ( ! confirm( submitPrompt ) ) {
					return;
				}
			}
			$( '#event-form-action' ).val( eventStatus );
			const $is_creation = $( '#form-name' ).val() === 'create_event';

			$.ajax(
				{
					type: 'POST',
					url: $translation_event.url,
					data:$form.serialize(),
					success: function ( response ) {
						if ( response.data.eventId ) {
							history.replaceState( '', '', response.data.eventEditUrl );
							$( '#form-name' ).val( 'edit_event' );
							$( '.event-page-title' ).text( 'Edit Event' );
							$( '#event-id' ).val( response.data.eventId );
							if ( eventStatus === 'publish' ) {
								$( 'button[data-event-status="draft"]' ).hide();
								$( '#published-update-text' ).show();
								$( 'button[data-event-status="publish"]' ).text( 'Update Event' );
							}
							if ( eventStatus === 'draft' ) {
								$( 'button[data-event-status="draft"]' ).text( 'Update Draft' );
							}
							$( '#event-url' ).removeClass( 'hide-event-url' ).find( 'a' ).attr( 'href', response.data.eventUrl ).text( response.data.eventUrl );
							if ( $is_creation ) {
								$( '#trash-button' ).toggle();
							}
							$gp.notices.success( response.data.message );
						}
					},
					error: function ( xhr, msg ) {
						/* translators: %s: Error message. */
						msg = xhr.responseJSON.data ? wp.i18n.sprintf( wp.i18n.__( 'Error: %s', 'gp-translation-events' ), xhr.responseJSON.data ) : wp.i18n.__( 'Error saving the event!', 'gp-translation-events' );
						$gp.notices.error( msg );
					},
				}
			);
		}

		function handleTrash() {
			if ( ! confirm( 'Are you sure you want to delete this event?' ) ) {
				return;
			}
			const $form = $( '.translation-event-form' );
			$( '#form-name' ).val( 'trash_event' );
			$( '#event-form-action' ).val( 'trash' );
			$.ajax(
				{
					type: 'POST',
					url: $translation_event.url,
					data:$form.serialize(),
					success: function ( response ) {
						window.location = response.data.eventTrashUrl;
					},
					error: function ( error ) {
						$gp.notices.error( response.data.message );
					},
				}
			);
		}

		function validateEventDates() {
			const startDateTimeInput = $( '#event-start' );
			const endDateTimeInput   = $( '#event-end' );
			if ( ! startDateTimeInput.length || ! endDateTimeInput.length ) {
				return;
			}

			startDateTimeInput.add( endDateTimeInput ).on(
				'input',
				function () {
					endDateTimeInput.prop( 'min', startDateTimeInput.val() );
					if (endDateTimeInput.val() < startDateTimeInput.val()) {
						endDateTimeInput.val( startDateTimeInput.val() );
					}
				}
			);
		}
		function selectUserTimezone() {
			const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;
			// phpcs:disable WordPress.WhiteSpace.OperatorSpacing.NoSpaceBefore
			// phpcs:disable WordPress.WhiteSpace.OperatorSpacing.NoSpaceAfter
			document.querySelector( `#event-timezone option[value="${timezone}"]` ).selected = true
			// phpcs:enable
		}

		function convertToUserLocalTime() {
			const timeElements = document.querySelectorAll( 'time.event-utc-time' );
			if ( timeElements.length === 0 ) {
				return;
			}
			timeElements.forEach(
				function ( timeEl ) {
					const datetime = timeEl.getAttribute( 'datetime' );
					if ( ! datetime ) {
						return;
					}
					const eventDateObj = new Date( datetime );
					timeEl.title       = eventDateObj.toUTCString();

					const userTimezoneOffset   = new Date().getTimezoneOffset();
					const userTimezoneOffsetMs = userTimezoneOffset * 60 * 1000;
					const userLocalDateTime    = new Date( eventDateObj.getTime() - userTimezoneOffsetMs );

					if ( timeEl.classList.contains( 'relative' ) ) {
						// Display the relative time.
						const now    = new Date();
						let diff     = userLocalDateTime - now;
						let in_text  = 'in ';
						let ago_text = '';
						if ( diff < 0 ) {
							if ( timeEl.classList.contains( 'future' ) ) {
								// If an event transitions from future to past, reload the page to move it from active to past events and vice versa.
								location.reload();
							}
							in_text  = '';
							ago_text = ' ago';
							diff     = - diff;
						}

						const seconds    = Math.floor( diff / 1000 );
						const minutes    = Math.floor( seconds / 60 );
						const hours      = Math.floor( minutes / 60 );
						const days       = Math.floor( hours / 24 );
						const weeks      = Math.floor( days / 7 );
						const months     = Math.floor( days / 30 );
						const years      = Math.floor( days / 365.25 );
						let relativeTime = '';
						if ( years > 1 ) {
							if ( ! timeEl.classList.contains( 'hide-if-too-far' ) ) {
								relativeTime = years + ' year' + ( years > 1 ? 's' : '' );
							} else {
								in_text = '';
							}
						} else if ( months > 1 ) {
							if ( ! timeEl.classList.contains( 'hide-if-too-far' ) ) {
								relativeTime = months + ' month' + ( months > 1 ? 's' : '' );
							} else {
								in_text = '';
							}
						} else if ( weeks > 1 ) {
							if ( ! timeEl.classList.contains( 'hide-if-too-far' ) || weeks < 3 ) {
								relativeTime = weeks + ' week' + ( weeks > 1 ? 's' : '' );
							} else {
								in_text = '';
							}
						} else if ( days > 0 ) {
							relativeTime = days + ' day' + ( days > 1 ? 's' : '' );
						} else if ( hours > 0 ) {
							relativeTime = hours + ' hour' + ( hours > 1 ? 's' : '' );
						} else if ( minutes > 0 ) {
							relativeTime = minutes + ' minute' + ( minutes > 1 ? 's' : '' );
						} else {
							relativeTime = 'less than a minute';
						}
						timeEl.textContent = in_text + relativeTime + ago_text;
						return;
					}

					const options = {
						weekday: 'short',
						year: 'numeric',
						month: 'short',
						day: 'numeric',
						hour: 'numeric',
						minute: 'numeric',
						timeZoneName: 'short'
					};
					if ( timeEl.dataset.format ) {
						if ( timeEl.dataset.format.includes( 'l' ) ) {
							options.weekday = 'long';
						} else if ( ! timeEl.dataset.format.includes( 'D' ) ) {
							delete options.weekday;
						}
						if ( timeEl.dataset.format.includes( 'F' ) ) {
							options.month = 'long';
						} else if ( timeEl.dataset.format.includes( 'm' ) || timeEl.dataset.format.includes( 'n' ) ) {
							options.month = 'numeric';
						}
					}
					timeEl.textContent = userLocalDateTime.toLocaleTimeString( navigator.language, options );
				}
			);
		}
	}( jQuery, $gp )
);
