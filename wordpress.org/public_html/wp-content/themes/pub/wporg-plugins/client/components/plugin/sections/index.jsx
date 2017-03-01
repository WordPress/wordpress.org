/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { identity } from 'lodash';
import { localize } from 'i18n-calypso';

export const Section = ( { content, slug, title, translate } ) => (
	<div>
		<div id={ slug } className={ `section read-more plugin-${ slug }` }>
			<h2>{ title }</h2>
			<div dangerouslySetInnerHTML={ { __html: content } } />
		</div>
		<button
			type="button"
			className="button-link section-toggle"
			aria-controls={ slug }
			aria-expanded="false"
			data-show-less={ translate( 'Show less' ) }
			data-read-more={ translate( 'Read more' ) }
		>
			{ translate( 'Read more' ) }
		</button>
	</div>
);

Section.propTypes = {
	content: PropTypes.string.isRequired,
	slug: PropTypes.string.isRequired,
	title: PropTypes.string.isRequired,
	translate: PropTypes.func,
};

Section.defaultProps = {
	translate: identity,
};

export default localize( Section );
