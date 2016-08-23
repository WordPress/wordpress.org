import React from 'react';
import { connect } from 'react-redux';

import Search from './search';
import { searchPlugins } from 'actions';

const SearchContainer = React.createClass( {
	componentDidMount() {
		this.searchPlugins();
	},

	componentDidUpdate( previousProps ) {
		if ( this.props.params.searchTerm !== previousProps.params.searchTerm ) {
			this.searchPlugins();
		}
	},

	searchPlugins() {
		this.props.dispatch( searchPlugins( this.props.params.searchTerm ) );
	},

	render() {
		return <Search { ...this.props } />;
	}
} );

const mapStateToProps = ( state, ownProps ) => ( {
	plugins: state.search[ ownProps.params.searchTerm ] || null
} );

export default connect( mapStateToProps )( SearchContainer );
