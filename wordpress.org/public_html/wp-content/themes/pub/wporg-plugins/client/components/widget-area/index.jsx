/**
 * External dependencies.
 */
import React, { PropTypes } from 'react';
import { withRouter } from 'react-router';

export const WidgetArea = ( { children, router } ) => {
	const classNames = [ 'widget-area' ];

	if ( router.isActive( '/', true ) ) {
		classNames.push( 'home' );
	}

	return (
		<aside id="secondary" className={ classNames.join( ' ' ) } role="complementary">
			{ children }
		</aside>
	);
};

WidgetArea.propTypes = {
	router: PropTypes.object,
};

WidgetArea.defaultProps = {
	router: {},
};

export default withRouter( WidgetArea );
