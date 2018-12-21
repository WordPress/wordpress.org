module.exports = {
	parser: 'babel-eslint',

	env: {
		browser : true,
		es6     : true,
	},

	extends: [
		'wordpress',
		'plugin:react/recommended',
		'plugin:jsx-a11y/recommended',
	],

	parserOptions: {
		ecmaFeatures: {
			jsx: true,
		},

		ecmaVersion : 2018,
		sourceType  : 'module',
	},

	settings: {
		react: {
			// This should equal the version of React that ships with Core.
			version: '16.4.1',
		},
	},

	globals: {
		document       : true,
		module         : true,
		window         : true,
		WordCampBlocks : true,
	},

	plugins: [
		'react',
		'jsx-a11y',
	],

	/*
	 * Rules that aren't enforced yet:
	 *
	 * - Consecutive assignment statements should be aligned on the `=` operator. See https://github.com/eslint/eslint/issues/11025.
	 * - Consecutive import statements should be aligned on the `from` keyword. See https://github.com/eslint/eslint/issues/11025.
	 * - Attributes within multi-line JSX elements should be aligned on the `=` operator. See https://github.com/yannickcr/eslint-plugin-react/issues/2030.
	 */
	rules: {
		/* eslint-disable quote-props, no-console *//*
		 *
		 * Most of the rule names contain dashes, and therefore have to be quoted. Because of that, it's more readable
		 * if all of the rule names are quoted, rather than shifting back and forth based.
		 */

		'array-bracket-spacing' : [ 'error', 'always' ],
		'arrow-parens'          : [ 'error', 'always' ],
		'arrow-spacing'         : 'error',
		'brace-style'           : [ 'error', '1tbs' ],

		'camelcase': [
			'error', {
				allow: [
					// Whitelisting REST API parameters like this is not very elegant. There may be a better solution.
					'avatar_size', 'per_page', 'posts_per_page', 'show_all_posts', 'speaker_link', 'show_avatars',
				],
			},
		],

		'comma-dangle'  : [ 'error', 'always-multiline' ],
		'comma-spacing' : 'error',
		'comma-style'   : 'error',

		/*
		 * Technically this violates WP's JS standard, but `computed-property-spacing` doesn't allow exceptions for
		 * strings. The JS rule only exists for consistency w/ PHP's rule, but IMO there should be a space around
		 * string values in PHP too.
		 */
		'computed-property-spacing' : [ 'error', 'always' ],
		'dot-notation'              : 'error',
		'eol-last'                  : 'error',
		'eqeqeq'                    : 'error',
		'func-call-spacing'         : 'error',
		'indent'                    : [ 'error', 'tab', { SwitchCase: 1 } ],

		'jsx-a11y/label-has-for'                : [ 'error', { required: 'id' } ],
		'jsx-a11y/media-has-caption'            : 'off',
		'jsx-a11y/no-noninteractive-tabindex'   : 'off',
		'jsx-a11y/role-has-required-aria-props' : 'off',
		'jsx-quotes'                            : 'error',

		'key-spacing': [ 'error', {
			'align': {
				'beforeColon' : true,
				'afterColon'  : true,
				'on'          : 'colon',
			},
		} ],

		'keyword-spacing'      : 'error',
		'lines-around-comment' : [ 'error', {
			beforeBlockComment : true,
			beforeLineComment  : true,
			allowBlockStart    : true,
			allowObjectStart   : true,
			allowArrayStart    : true,
			allowClassStart    : true,
		} ],

		'no-alert'                 : 'error',
		'no-bitwise'               : 'error',
		'no-caller'                : 'error',
		'no-console'               : 'error',
		'no-debugger'              : 'error',
		'no-dupe-args'             : 'error',
		'no-dupe-keys'             : 'error',
		'no-duplicate-case'        : 'error',
		'no-else-return'           : 'error',
		'no-eval'                  : 'error',
		'no-extra-semi'            : 'error',
		'no-fallthrough'           : 'error',
		'no-lonely-if'             : 'error',
		'no-mixed-operators'       : 'error',
		'no-mixed-spaces-and-tabs' : 'error',
		'no-multiple-empty-lines'  : [ 'error', { max: 1 } ],

		/*
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
		'no-multi-space': 'off',

		'no-multi-str'      : 'off',
		'no-negated-in-lhs' : 'error',
		'no-nested-ternary' : 'error',
		'no-redeclare'      : 'error',

		'no-restricted-syntax': [
			'error',
			{
				selector : 'CallExpression[callee.name=/^__|_n|_x$/]:not([arguments.0.type=/^Literal|BinaryExpression$/])',
				message  : 'Translate function arguments must be string literals.',
			},

			{
				selector : 'CallExpression[callee.name=/^_n|_x$/]:not([arguments.1.type=/^Literal|BinaryExpression$/])',
				message  : 'Translate function arguments must be string literals.',
			},

			{
				selector : 'CallExpression[callee.name=_nx]:not([arguments.2.type=/^Literal|BinaryExpression$/])',
				message  : 'Translate function arguments must be string literals.',
			},
		],

		'no-shadow'                     : 'error',
		'no-undef'                      : 'error',
		'no-undef-init'                 : 'error',
		'no-unreachable'                : 'error',
		'no-unsafe-negation'            : 'error',
		'no-unused-expressions'         : 'error',
		'no-unused-vars'                : 'error',
		'no-useless-return'             : 'error',
		'no-whitespace-before-property' : 'error',
		'object-curly-spacing'          : [ 'error', 'always' ],

		'padded-blocks'                   : [ 'error', 'never' ],
		'padding-line-between-statements' : [ 'error',
			{ blankLine: 'always', prev: '*',          next: 'block-like' },
			{ blankLine: 'always', prev: 'block-like', next: '*'          },
			{ blankLine: 'always', prev: '*',          next: 'class'      },
			{ blankLine: 'always', prev: '*',          next: 'continue'   },
			{ blankLine: 'always', prev: 'const',      next: 'import'     },
			{ blankLine: 'always', prev: 'import',     next: 'const'      },
			{ blankLine: 'always', prev: '*',          next: 'return'     },

			{ blankLine: 'always', prev: 'break', next: '*'        }, // This should be requiring blank line after break, but it's not.
			{ blankLine: 'always', prev: '*',     next: 'function' }, // This should be requiring blank line before function, but it's not.
		],

		'quote-props' : [ 'error', 'as-needed' ],
		'quotes'      : [ 'error', 'single', {
			allowTemplateLiterals : true,
			avoidEscape           : true,
		} ],

		'react/display-name'       : 'off',
		'react/no-children-prop'   : 'off',
		'react/prop-types'         : 'off',
		'react/react-in-jsx-scope' : 'off',

		// Disabled because it doesn't support our style: https://github.com/yannickcr/eslint-plugin-react/issues/2030
		'react/jsx-equals-spacing' : 'off',
		'react/jsx-indent'         : [ 'error', 'tab' ],
		'react/jsx-indent-props'   : [ 'error', 'tab' ],
		'react/jsx-key'            : 'error',
		'react/jsx-tag-spacing'    : 'error',
		'react/jsx-curly-spacing'  : [ 'error', {
			when     : 'always',
			children : true,
		} ],

		'require-jsdoc': [ 'error', {
			'require': {
				FunctionDeclaration     : true,
				MethodDefinition        : true,
				ClassDeclaration        : true,
				ArrowFunctionExpression : true,
				FunctionExpression      : true,
			},
		} ],

		'semi'                : 'error',
		'semi-spacing'        : 'error',
		'space-before-blocks' : [ 'error', 'always' ],

		'space-before-function-paren': [ 'error', {
			anonymous  : 'never',
			named      : 'never',
			asyncArrow : 'always',
		} ],

		'space-in-parens' : [ 'error', 'always' ],
		'space-infix-ops' : [ 'error', { int32Hint: false } ],
		'space-unary-ops' : [ 'error', {
			overrides: {
				'!'   : true,
				yield : true,
			},
		} ],

		'valid-jsdoc': [ 'error', {
			prefer: {
				arg      : 'param',
				argument : 'param',
				extends  : 'augments',
				returns  : 'return',
			},

			preferType: {
				array   : 'Array',
				bool    : 'boolean',
				Boolean : 'boolean',
				Float   : 'float',
				int     : 'integer',
				Int     : 'integer',
				Integer : 'integer',
				Number  : 'number',
				object  : 'Object',
				String  : 'string',
				Void    : 'void',
			},

			requireParamDescription  : false,
			requireReturnDescription : false,
		} ],

		'valid-typeof' : 'error',
		'yoda'         : 'error',

		/* eslint-enable quote-props, no-console */
	},
};
