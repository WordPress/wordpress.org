module.exports = {
	extends : 'plugin:@wordpress/eslint-plugin/recommended',
	globals : {
		wp : true,
	},
	rules : {
		/**
		 * Instead of turning this off altogether, we should safelist the parameters that are coming in from
		 * the REST API. However, the `allow` config for this rule is only available in eslint 5+. Currently
		 * the @wordpress/scripts package uses eslint 4.x, but the next version will bump it up to 5.
		 *
		 * Here is the config to use once this is possible:
		 *
		 * 'camelcase' : [
		 *     'error',
		 *     {
		 *         allow: [ // These are variables defined in PHP and exposed via the REST API.
		 *             // Speakers block
		 *  		   'post_ids', 'term_ids', 'grid_columns',
		 *  		   'show_avatars', 'avatar_size', 'avatar_align',
		 *  		   'speaker_link', 'show_session',
		 *         ],
		 *     },
		 * ],
		 */
		'camelcase' : 'off',
		/**
		 * Copied from our previous custom .eslintrc.js file.
		 */
		'key-spacing' : [ 'error', {
			'align' : {
				'beforeColon' : true,
				'afterColon'  : true,
				'on'          : 'colon',
			},
		} ],
		/**
		 * Copied from our previous custom .eslintrc.js file.
		 *
		 * Ideally this should be on, because we don't want to allow things like `const foo  == bar;`, but the rule
		 * currently isn't flexible enough to allow all the exceptions we need. Specifically, there are times where
		 * readability is vastly improved by aligning attributes in consecutive lines, like the
		 * `padding-line-between-statements` objects in this file.
		 *
		 * Alternate configuration if we ever want to re-enable this:
		 *
		 * 'no-multi-spaces': [ 'error', {
		 *      // Use the `type` value from the parser demo to find these properties: https://eslint.org/parser/.
		 *	    exceptions: {
		 *		    VariableDeclarator : true,
		 *		    ImportDeclaration  : true,
		 *		    JSXAttribute       : true,
		 *	    },
		 * } ],
		 */
		'no-multi-spaces' : 'off',
	},
};
