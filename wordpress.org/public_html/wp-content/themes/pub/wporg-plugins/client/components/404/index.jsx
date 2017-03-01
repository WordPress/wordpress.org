/**
 * External dependencies.
 */
import React, { Component, PropTypes } from 'react';
import { identity } from 'lodash';
import { IndexLink } from 'react-router';
import { localize } from 'i18n-calypso';

export class NotFound extends Component {
	static propTypes = {
		translate: PropTypes.func,
	};

	static defaultProps = {
		translate: identity,
	};

	componentDidMount() {
		setTimeout( () => jQuery( '.hinge' ).hide(), 1800 );
	}

	render() {
		return (
			<section className="error-404 not-found">
				<header className="page-header">
					<h1 className="page-title">{ this.props.translate( 'Oops! That page can&rsquo;t be found.' ) }</h1>
				</header>
				<div className="page-content">
					<p>
						{ this.props.translate(
							'Try searching from the field above, or go to the {{link}}home page{{/link}}.', {
								component: { link: <IndexLink to="/" /> },
							}
						) }
					</p>

					<div className="logo-swing">
						<img src="http://messislore.com/images/wp-logo-blue-trans-blur.png" className="wp-logo" />
						<img src="http://messislore.com/images/wp-logo-blue.png" className="wp-logo hinge" />
					</div>
				</div>
			</section>
		);
	}
}

export default localize( NotFound );
