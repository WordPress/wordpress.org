/**
 * External dependencies.
 */
import { isEmpty } from 'lodash';

export const hasSections = ( state ) => ! isEmpty( state.sections.items );

export default hasSections;
