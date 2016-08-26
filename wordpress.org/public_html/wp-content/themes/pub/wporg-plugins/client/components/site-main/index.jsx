import React from 'react';

export default React.createClass( {
	displayName: 'SiteMain',

	render() {
		let classNames = [ 'site-main' ];

		if ( this.props.params.slug ) {
			classNames.push( 'single' );
		}

		return (
			<main id="main" className={ classNames.join( ' ' ) } role="main">
				{ this.props.children }
			</main>
		)
	}
} );
