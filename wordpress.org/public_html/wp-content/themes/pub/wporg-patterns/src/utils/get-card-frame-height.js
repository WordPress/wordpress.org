/**
 * Module constants
 */
const ASPECT_RATIO = 2 / 3;

/**
 * Returns the height of the preview window.
 *
 * @param {number} cardWidth The width of the card
 */
export default function getCardFrameHeight( cardWidth ) {
	return cardWidth * ASPECT_RATIO;
}
