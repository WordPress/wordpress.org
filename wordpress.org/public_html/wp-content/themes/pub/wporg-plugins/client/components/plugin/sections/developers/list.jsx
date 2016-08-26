import React from 'react';

export default React.createClass( {
	displayName: 'DeveloperList',

	render() {
		if ( ! this.props.contributors.length ) {
			return <div />;
		}

		return (
			<ul>
				{ this.props.contributors.map( contributor =>
					<li>
						<img className="avatar avatar-32 photo" height="32" width="32" src={ contributor.avatar } />
						<a href={ contributor.profile }>{ contributor.display_name }</a>
					</li>
				) }
			</ul>
		)
	}
} );
