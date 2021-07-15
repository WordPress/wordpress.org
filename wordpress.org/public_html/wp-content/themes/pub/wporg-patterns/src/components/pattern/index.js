/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import AuthorDetails from './author-details';
import PatternGrid from '../pattern-grid';
import PatternPreview from '../pattern-preview';
import PatternPreviewActions from '../pattern-preview-actions';
import PatternThumbnail from '../pattern-thumbnail';
import ReportPatternButton from '../report-pattern-button';
import { store as patternStore } from '../../store';

const Pattern = ( { postId, userHasReported } ) => {
	// postId as passed from the HTML dataset is a string.
	postId = Number( postId ) || 0;
	const pattern = useSelect( ( select ) => select( patternStore ).getPattern( postId ), [ postId ] );
	if ( ! pattern ) {
		return null;
	}

	return (
		<>
			<PatternPreviewActions postId={ postId } />
			<div className="pattern-preview__container">
				<PatternPreview blockContent={ pattern.content.rendered } />
				<div className="pattern__meta">
					<ReportPatternButton userHasReported={ userHasReported === 'true' } postId={ postId } />
				</div>
			</div>
			<div className="entry-content">
				<PatternGrid
					query={ { author: pattern.author, per_page: 3, exclude: postId } }
					showPagination={ false }
					header={
						<>
							<h2>{ __( 'More from this designer', 'wporg-patterns' ) }</h2>
							<AuthorDetails { ...pattern.author_meta } />
						</>
					}
				>
					{ ( post ) => <PatternThumbnail key={ post.id } pattern={ post } /> }
				</PatternGrid>
			</div>
		</>
	);
};

export default Pattern;
