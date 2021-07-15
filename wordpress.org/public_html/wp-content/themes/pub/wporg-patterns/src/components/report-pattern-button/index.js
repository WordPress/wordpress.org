/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Icon, check } from '@wordpress/icons';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import ReportPatternModal from '../report-pattern-modal';

const ReportPatternButton = ( { postId, userHasReported } ) => {
	const [ showModal, setShowModal ] = useState( false );
	const [ hasSubmitted, setHasSubmitted ] = useState( false );
	const alreadySubmitted = userHasReported || hasSubmitted;
	const isLoggedIn = !! wporgPatternsData.userId;

	if ( alreadySubmitted ) {
		return (
			<p className="pattern-report-button__copy">
				<Icon icon={ check } />
				{ __( "You've reported this pattern", 'wporg-patterns' ) }
			</p>
		);
	}

	if ( ! isLoggedIn ) {
		return (
			<p className="pattern-report-button__copy">
				<a
					href={ addQueryArgs( '/wp-login.php', {
						redirect_to: window.location.pathname,
					} ) }
				>
					{ __( 'Login to report this pattern', 'wporg-patterns' ) }
				</a>
			</p>
		);
	}

	return (
		<>
			<Button className="pattern-report-button" isLink onClick={ () => setShowModal( true ) }>
				{ __( 'Report this pattern', 'wporg-patterns' ) }
			</Button>

			{ showModal && (
				<ReportPatternModal
					postId={ postId }
					onClose={ ( submitted ) => {
						setShowModal( false );
						setHasSubmitted( submitted );
					} }
				/>
			) }
		</>
	);
};

export default ReportPatternButton;
