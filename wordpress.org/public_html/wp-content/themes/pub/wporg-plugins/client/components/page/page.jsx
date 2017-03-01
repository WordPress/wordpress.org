/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import { getPage } from 'state/selectors';

const Page = ( { page } ) => {
	if ( page && page.title ) {
		return (
			<article className="page type-page">
				<header className="entry-header">
					<h1 className="entry-title">{ page.title.rendered }</h1>
				</header>
				<div className="entry-content">
					<section>
						<div className="container" dangerouslySetInnerHTML={ { __html: page.content.rendered } } />
					</section>
				</div>
			</article>
		);
	}

	return (
		<article className="page type-page">
			<header className="entry-header">
				<h1 className="entry-title"> </h1>
			</header>
			<div className="entry-content">
				<section>
					<div className="container"> LOADING </div>
				</section>
			</div>
		</article>
	);
};

Page.propTypes = {
	page: PropTypes.object,
};

Page.defaultProps = {
	page: {},
};

export default connect(
	( state ) => ( {
		page: getPage( state ),
	} ),
)( Page );
