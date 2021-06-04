/**
 * External dependencies
 */
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import CopyPatternButton from '../copy-pattern-button';
import FavoriteButton from '../favorite-button';
import CopySuccessMessage from './copy-success-message';
import CopyGuide from './copy-guide';

const PatternPreviewActions = ( { postId } ) => {
	const [ showSuccess, setShowSuccess ] = useState( false );
	const [ showGuide, setShowGuide ] = useState( false );

	return (
		<div className="pattern-actions">
			<div className="pattern-actions__container">
				<CopyPatternButton onSuccess={ () => setShowSuccess( true ) } />
				<FavoriteButton patternId={ postId } />
				{ showSuccess && <CopySuccessMessage onClick={ () => setShowGuide( true ) } /> }
				{ showGuide && <CopyGuide onFinish={ () => setShowGuide( false ) } /> }
			</div>
		</div>
	);
};

export default PatternPreviewActions;
