/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';

export const SiteMain = ( { children, params } ) => {
	const classNames = [ 'site-main' ];

	if ( params.slug ) {
		classNames.push( 'single' );
	}

	return (
		<main id="main" className={ classNames.join( ' ' ) } role="main">
			{ children }
		</main>
	);
};

SiteMain.propTypes = {
	params: PropTypes.object,
};

SiteMain.defaultProps = {
	params: {},
};

export default SiteMain;
