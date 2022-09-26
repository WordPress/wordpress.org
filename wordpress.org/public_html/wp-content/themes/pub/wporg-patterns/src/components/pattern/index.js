/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { decodeEntities } from '@wordpress/html-entities';
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
import StatusNotice from './status-notice';
import { store as patternStore } from '../../store';

const Pattern = ( { postId, userHasReported } ) => {
	// postId as passed from the HTML dataset is a string.
	postId = Number( postId ) || 0;
	const { pattern, categories } = useSelect(
		( select ) => {
			const _pattern = select( patternStore ).getPattern( postId );
			const allCategories = select( patternStore ).getCategories() || [];
			const _categories = _pattern?.[ 'pattern-categories' ]
				.map( ( cid ) => allCategories.find( ( { id } ) => id === cid ) )
				.filter( Boolean );

			return {
				pattern: _pattern,
				categories: _categories || [],
			};
		},
		[ postId ]
	);
	if ( ! pattern ) {
		return null;
	}

	const isMyPattern = window.wporgPatternsData.userId === pattern.author;

	return (
		<>
			<header className="entry-header">
				{ isMyPattern && <StatusNotice pattern={ pattern } /> }
				<h1 className="entry-title">{ decodeEntities( pattern.title.rendered ) }</h1>
				<div className="pattern__categories">
					{ categories.map( ( { id, name, link } ) => (
						<a href={ link } key={ id }>
							{ decodeEntities( name ) }
						</a>
					) ) }
				</div>
			</header>
			<PatternPreviewActions postId={ postId } showOptions={ isMyPattern } />
			<div className="pattern-preview__container">
				<PatternPreview pattern={ pattern } />
				<div className="pattern__meta">
					<ReportPatternButton userHasReported={ userHasReported === 'true' } postId={ postId } />
				</div>
			</div>
			<div className="pattern__related-patterns">
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
