/**
 * External dependencies
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CopyGuide from './copy-guide';
import CopyPatternButton from '../copy-pattern-button';
import CopySuccessMessage from './copy-success-message';
import FavoriteButton from '../favorite-button';
import ManageOptions from '../manage-options';

const PatternPreviewActions = ( { postId, showOptions } ) => {
	const [ showSuccess, setShowSuccess ] = useState( false );
	const [ showGuide, setShowGuide ] = useState( false );

	return (
		<div className="pattern-actions">
			<div className="pattern-actions__container">
				<CopyPatternButton onSuccess={ () => setShowSuccess( true ) } />
				<FavoriteButton patternId={ postId } />
				{ showOptions && <ManageOptions patternId={ postId } /> }
				{ showSuccess && <CopySuccessMessage onClick={ () => setShowGuide( true ) } /> }
				{ showGuide && <CopyGuide onFinish={ () => setShowGuide( false ) } /> }
			</div>
		</div>
	);
};

export default PatternPreviewActions;
