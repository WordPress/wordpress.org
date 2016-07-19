import { connect } from 'react-redux';
import { findWhere } from 'underscore';

/**
 * Internal dependencies.
 */
import Page from 'components/page';

const mapStateToProps = ( state, ownProps ) => ( {
	page: findWhere( state.pages, { slug: ownProps.route.path } )
} );

export default connect( mapStateToProps )( Page );
