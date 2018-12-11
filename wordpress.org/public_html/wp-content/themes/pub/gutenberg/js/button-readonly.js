var el = wp.element.createElement,
	registerBlockType = wp.blocks.registerBlockType;

registerBlockType( 'wporg/download-button', {
	title: 'Download Gutenberg Button',
	icon: 'button',
	category: 'layout',

	attributes: {
		url: {
			source: 'attribute',
			selector: 'a',
			attribute: 'href',
		},
		title: {
			source: 'attribute',
			selector: 'a',
			attribute: 'title',
		},
		text: {
			source: 'text',
			selector: 'a',
		},
		align: {
			type: 'string',
			default: 'center',
		}
	},

	supports: {
		inserter: false
	},

	edit: function( props ) {

		return el(
			'div',
			{ className: 'wp-block-button align' + props.attributes.align, },
			el(
				'a',
				{ className: 'wp-block-button__link has-background has-strong-blue-background-color', href: props.attributes.url,
					style: { backgroundColor: 'rgb(0,115,170)', color: '#fff' },
					title: props.attributes.title
				},
				props.attributes.text
			)
		);
	},

	save: function( props ) {

		return el(
			'div',
			{ className: 'wp-block-button align' + props.attributes.align },
			el(
				'a',
				{ className: 'wp-block-button__link has-background has-strong-blue-background-color', href: props.attributes.url,
					style: { backgroundColor: 'rgb(0,115,170)', color: '#fff' },
					title: props.attributes.title
				},
				props.attributes.text
			)
		);
	},
} );

