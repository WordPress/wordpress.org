import React from 'react';

import DeveloperList from './list';

export default React.createClass( {
	displayName: 'Developers',

	render() {
		return (
			<div>
				<div id="developers" className="read-more" aria-expanded="false">
					<h2>Contributors & Developers</h2>
					<p>This is open source software. The following people have contributed to this plugin.</p>
					<DeveloperList contributors={ this.props.contributors } />

					<h5>Interested in development?</h5>
					<p><a href={ `https://plugins.svn.wordpress.org/${ this.props.slug }/` } rel="nofollow">Browse the code</a> or subscribe to the <a href={ `https://plugins.trac.wordpress.org/log/${ this.props.slug }/` } rel="nofollow">development log</a> by <a href={ `https://plugins.trac.wordpress.org/log/${ this.props.slug }/?limit=100&mode=stop_on_copy&format=rss` } rel="nofollow">RSS</a>.</p>
				</div>
				<button type="button" className="button-link section-toggle" aria-controls="developers">Read more</button>
			</div>
		)
	}
} );
