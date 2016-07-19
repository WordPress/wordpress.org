import React from 'react';

export default React.createClass( {
	displayName: 'ArchiveBrowse',

	render() {
		return (
			<div>{ this.props.params.type }</div>
		)
	}
} );
