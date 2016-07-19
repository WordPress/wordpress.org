import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import PluginSection from 'components/plugin-section';

const mapStateToProps = () => ( {
	plugins: [] //todo
} );

export default connect( mapStateToProps )( PluginSection );
