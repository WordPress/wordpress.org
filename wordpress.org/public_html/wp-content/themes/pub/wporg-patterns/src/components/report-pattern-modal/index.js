/**
 * WordPress dependencies
 */
import { speak } from '@wordpress/a11y';
import { useReducer, useRef, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Button, Modal, RadioControl, Spinner, TextareaControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { store as patternStore } from '../../store';

const reducer = ( state, action ) => {
	switch ( action.status ) {
		case 'submitting':
			return { isSubmitting: true };
		case 'submitted':
			return { isSubmitted: true };
		case 'error':
			return { hasError: true, message: action.message };
		default:
			return {};
	}
};

const ReportPatternModal = ( { postId, onClose } ) => {
	const [ state, dispatch ] = useReducer( reducer, {} );
	const [ selectedOption, setOption ] = useState( '' );
	const [ details, setDetails ] = useState( '' );
	const container = useRef();

	const submittedText = __( 'Your report has been submitted.', 'wporg-patterns' );

	const { isLoading, mappedReasons } = useSelect( ( select ) => {
		const { getPatternFlagReasons, isLoadingPatternFlagReasons } = select( patternStore );
		const reasons = getPatternFlagReasons() || [];

		return {
			isLoading: isLoadingPatternFlagReasons(),
			mappedReasons: reasons
				.sort( ( a, b ) => {
					// Using the slug allows us to set a custom order for the terms through the admin UI.
					switch ( true ) {
						case a.slug < b.slug:
							return -1;
						case a.slug > b.slug:
							return 1;
						default:
							return 0;
					}
				} )
				.map( ( i ) => {
					// We need to convert id to string to make the RadioControl match the selected item.
					return { label: i.name, value: i.id.toString() };
				} ),
		};
	} );

	const handleSubmit = ( event ) => {
		event.preventDefault();

		if ( ! selectedOption || ! details.length || state.isSubmitted || state.isSubmitting ) {
			return;
		}

		dispatch( { status: 'submitting' } );

		apiFetch( {
			path: addQueryArgs( '/wp/v2/wporg-pattern-flag' ),
			method: 'POST',
			data: {
				parent: postId,
				'wporg-pattern-flag-reason': selectedOption,
				excerpt: details,
			},
		} )
			.then( () => {
				dispatch( { status: 'submitted' } );
				speak( submittedText );
				container.current.closest( '[role="dialog"]' ).focus();
			} )
			.catch( ( err ) => {
				dispatch( {
					status: 'error',
					message: err.message,
				} );

				speak(
					sprintf(
						/* translators: %s: Error message. */
						__( 'Error: %s', 'wporg-patterns' ),
						err.message
					)
				);
			} );
	};

	const handleClose = () => {
		onClose( state.isSubmitted );
	};

	const renderView = () => {
		if ( isLoading ) {
			return <Spinner />;
		}

		if ( state.isSubmitted ) {
			return <p className="pattern-report-modal__copy">{ submittedText }</p>;
		}

		return (
			<form onSubmit={ handleSubmit }>
				<RadioControl
					className="pattern-report-modal__radio"
					label={ __( 'Please choose a reason:', 'wporg-patterns' ) }
					selected={ selectedOption }
					options={ mappedReasons }
					onChange={ setOption }
					required={ true }
				/>
				<TextareaControl
					label={ __( 'Please provide details (required)', 'wporg-patterns' ) }
					value={ details }
					onChange={ setDetails }
					required={ true }
				/>

				{ state.hasError && (
					<div className="notice notice-large notice-alt notice-error">{ state.message }</div>
				) }
				<div className="pattern-report-modal__actions">
					<Button isSecondary onClick={ handleClose }>
						{ __( 'Cancel', 'wporg-patterns' ) }
					</Button>
					<Button type="submit" isBusy={ state.isSubmitting } isPrimary>
						{ state.isSubmitting
							? __( 'Submitting …', 'wporg-patterns' )
							: __( 'Report', 'wporg-patterns' ) }
					</Button>
				</div>
			</form>
		);
	};

	return (
		<Modal
			className={ `pattern-report-modal ${
				! state.isSubmitted ? 'pattern-report-modal__has-fixed-height' : ''
			}` }
			title={ __( 'Report this pattern', 'wporg-patterns' ) }
			onRequestClose={ handleClose }
		>
			<div ref={ container }>{ renderView() }</div>
		</Modal>
	);
};

export default ReportPatternModal;
