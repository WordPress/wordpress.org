/**
 * WordPress dependencies
 */
import { __, _x, sprintf } from '@wordpress/i18n';
import { getQueryString } from '@wordpress/url';

/**
 * Internal dependencies
 */
import getPaginationList from '../../utils/get-pagination-list';
import { useRoute } from '../../hooks';

export default function Pagination( { currentPage = 1, onNavigation, totalPages } ) {
	const { path, update: updatePath } = useRoute();
	if ( ! totalPages || totalPages <= 1 ) {
		return null;
	}

	const hasPrevious = currentPage > 1;
	const hasNext = currentPage < totalPages;
	const queryString = getQueryString( path ) ? '?' + getQueryString( path ) : '';
	const basePath = path.replace( queryString, '' ).replace( /page\/\d+\/?/, '' );

	const pages = getPaginationList( totalPages, currentPage );

	const urlFormat = `${ basePath }page/%s/${ queryString }`;
	const getPageUrl = ( page ) => ( page === 1 ? `${ basePath }${ queryString }` : sprintf( urlFormat, page ) );

	const onClick = ( event, page ) => {
		event.preventDefault();
		updatePath( getPageUrl( page ) );
		if ( 'function' === typeof onNavigation ) {
			onNavigation();
		}
	};

	return (
		<nav className="pagination" aria-label={ __( 'Pagination', 'wporg-patterns' ) }>
			<ul className="pagination__list">
				<li className="pagination__item pagination__item-previous-page">
					{ hasPrevious && (
						<a
							className="pagination__link"
							href={ getPageUrl( currentPage - 1 ) }
							onClick={ ( event ) => onClick( event, currentPage - 1 ) }
						>
							<span className="screen-reader-text">{ __( 'Previous page', 'wporg-patterns' ) }</span>
							<span aria-hidden>
								{ _x( 'Previous', 'previous page link label', 'wporg-patterns' ) }
							</span>
						</a>
					) }
				</li>
				{ pages.map( ( page, index ) => {
					if ( '…' === page ) {
						return (
							<li className="pagination__item" key={ `${ index }-${ page }` }>
								{ page }
							</li>
						);
					}
					return (
						<li className="pagination__item" key={ page }>
							<a
								className="pagination__link"
								href={ getPageUrl( page ) }
								aria-current={ page === currentPage ? 'page' : undefined }
								onClick={ ( event ) => onClick( event, page ) }
							>
								<span className="screen-reader-text">
									{ sprintf(
										// translators: %s is the page number.
										__( 'Page %s', 'wporg-patterns' ),
										page
									) }
								</span>
								<span aria-hidden>{ page }</span>
							</a>
						</li>
					);
				} ) }
				<li className="pagination__item pagination__item-next-page">
					{ hasNext && (
						<a
							className="pagination__link"
							href={ getPageUrl( currentPage + 1 ) }
							onClick={ ( event ) => onClick( event, currentPage + 1 ) }
						>
							<span className="screen-reader-text">{ __( 'Next page', 'wporg-patterns' ) }</span>
							<span aria-hidden>{ _x( 'Next', 'next page link label', 'wporg-patterns' ) }</span>
						</a>
					) }
				</li>
			</ul>
		</nav>
	);
}
