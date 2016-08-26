import React from 'react';
import values from 'lodash/values';

export default React.createClass( {
	displayName: 'DeveloperList',

	render() {
		if ( ! this.props.contributors ) {
			return <div />;
		}

		return (
			<ul className="plugin-developers">
				{ values( this.props.contributors ).map( ( contributor, index ) =>
					<li key={ index }>
						<img className="avatar avatar-32 photo" height="32" width="32" src={ contributor.avatar } />
						<a href={ contributor.profile }>{ contributor.display_name }</a>
					</li>
				) }
			</ul>
		)
	}
} );
