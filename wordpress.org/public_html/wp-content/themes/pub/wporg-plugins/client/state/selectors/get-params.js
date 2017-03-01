/**
 *
 * @param {Object} state Global state object.
 * @return {String} Params.
 */
export const getParams = ( state ) => state.router.params;
export default getParams;
