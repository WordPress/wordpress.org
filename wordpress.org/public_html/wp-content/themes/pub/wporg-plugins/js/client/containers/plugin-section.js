import { connect } from 'react-redux';

/**
 * Internal dependencies.
 */
import PluginSection from 'components/plugin-section';

const mapStateToProps = () => ( {
	plugins:  [
		{
			id: 0,
			slug: 'zero',
			title: {
				rendered: 'Zero'
			},
			excerpt: 'An excerpt for Zero',
			rating: 4.5,
			rating_count: 345
		},
		{
			id: 1,
			slug: 'one',
			title: {
				rendered: 'One'
			},
			excerpt: 'An excerpt for One',
			rating: 4.5,
			rating_count: 345
		},
		{
			id: 2,
			slug: 'two',
			title: {
				rendered: 'Two'
			},
			excerpt: 'An excerpt for Two',
			rating: 4.5,
			rating_count: 345
		}
	] //todo
} );

export default connect( mapStateToProps )( PluginSection );
