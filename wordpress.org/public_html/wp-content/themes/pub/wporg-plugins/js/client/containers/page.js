import { connect } from 'react-redux';
import find from 'lodash/find';

/**
 * Internal dependencies.
 */
import Page from 'components/page';

const mapStateToProps = ( state, ownProps ) => ( {
	page: find( state.pages, { slug: ownProps.route.path } )
} );

export default connect( mapStateToProps )( Page );
