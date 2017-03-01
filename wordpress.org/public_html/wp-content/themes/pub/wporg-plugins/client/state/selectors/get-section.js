/**
 *
 * @param {Object} state Global state object.
 * @param {String} slug  Taxonomy slug.
 * @return {String} Taxonomy ID.
 */
export const getSection = ( state, slug ) => state.sections.items[ slug ] || {};
export default getSection;
