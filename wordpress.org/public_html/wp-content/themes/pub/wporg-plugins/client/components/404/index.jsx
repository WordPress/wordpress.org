import React from 'react';
import { IndexLink } from 'react-router';

export default React.createClass( {
	displayName: 'NotFound',

	componentDidMount() {
		setTimeout( function() {
			jQuery( '.hinge' ).hide();
		}, 1800 );
	},

	render() {
		return (
			<section className="error-404 not-found">
				<header className="page-header">
					<h1 className="page-title">Oops! That page can&rsquo;t be found.</h1>
				</header>
				<div className="page-content">
					<p>Try searching from the field above, or go to the <IndexLink to="/">home page</IndexLink>.</p>

					<div className="logo-swing">
						<img src="http://messislore.com/images/wp-logo-blue-trans-blur.png" className="wp-logo" />
						<img src="http://messislore.com/images/wp-logo-blue.png" className="wp-logo hinge" />
					</div>
				</div>
			</section>
		)
	}
} );


