# Chart Block

This Chart Block provides a way of displaying data in charts.

## Getting Started

1. Make sure you have [`git`](https://git-scm.com/), [`node`](https://nodejs.org/), and [`npm`](https://www.npmjs.com/get-npm) installed.
2. Clone this repository into your `\plugins` folder.
3. Execute `npm install` from the root directory of the repository to install the dependencies.
4. Execute `npm start` for development mode (`npm run build` for a production build).
5. Activate the `Chart Block` plugin in your WordPress plugin directory
6. While editing your page/post, add in the `Chart Block` block and publish!

# Block attributes

| Attribute  | Descriptions                                                                                          |
| ---------- | ----------------------------------------------------------------------------------------------------- |
| URL        | The URL of a rest endpoint that returns Google Chart formatted data.                                  |
| Title      | Appears as the title of the card.                                                                     |
| Notes      | Comma separated list of items to appear under the chart. Used to communicate details about the chart. |
| Headings   | Comma separated list of column headings that match the data.                                          |
| Chart Type | The type of chart to render. Ie: `ColumnChart`                                                        |
| Options    | A JSON object passed into Google Charts as `options`.                                                 |

## Supported Chart Libraries

This block currently uses [Google Charts](https://developers.google.com/chart).

## Development environment

You can (optionally) use [`wp-env`](https://developer.wordpress.org/block-editor/packages/packages-env/) to set up a local environment.

1. Install the node dependencies `npm install`
2. Start the wp-env environment with `npm run wp-env start`
3. Visit your new local environment at `http://localhost:8888`

## License

Chart Block is licensed under [GNU General Public License v2 (or later)](./LICENSE.md).

## Attribution

-   [React Google Charts](https://www.react-google-charts.com/)
