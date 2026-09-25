/**
 * Link helpers for createInterpolateElement strings.
 */

/**
 * External anchor element; its text comes from the interpolated string.
 *
 * @param {string} href URL.
 * @return {Element} Anchor.
 */
export const externalAnchor = ( href ) => (
	// eslint-disable-next-line jsx-a11y/anchor-has-content -- filled by createInterpolateElement.
	<a href={ href } target="_blank" rel="noopener noreferrer" />
);
