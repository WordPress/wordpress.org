/**
 *
 * @param {Object} state Global state object.
 * @return {String} Path.
 */
export const getPath = ( state ) => state.router ? state.router.location.pathname : null;
export default getPath;
