/**
 * Translated labels of option values (source strings of the classic screen,
 * so existing translations keep matching). Shared by the Upload tab, the
 * General "Current defaults" chips and the Bulk "This run uses" card.
 */
/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

export const FORMATS = [
	{
		value: 'webp',
		label: 'WebP',
		help: __(
			'WebP — widest support. If the converted file would not be smaller than the original, the original is kept.',
			'lw-img'
		),
	},
	{
		value: 'avif',
		label: 'AVIF',
		help: __(
			'AVIF — smaller files, modern browsers. If the converted file would not be smaller than the original, the original is kept.',
			'lw-img'
		),
	},
];

export const LEVELS = [
	{
		value: 'lossless',
		label: __( 'Lossless', 'lw-img' ),
		help: __( 'Lossless — pixel-perfect, larger files.', 'lw-img' ),
	},
	{
		value: 'normal',
		label: __( 'Normal', 'lw-img' ),
		help: __( 'Normal — balanced quality and size.', 'lw-img' ),
	},
	{
		value: 'aggressive',
		label: __( 'Aggressive', 'lw-img' ),
		help: __(
			'Aggressive — smaller files, slight compression may show.',
			'lw-img'
		),
	},
	{
		value: 'ultra',
		label: __( 'Ultra', 'lw-img' ),
		help: __(
			'Ultra — maximum compression, visible on detailed images.',
			'lw-img'
		),
	},
];

export const SPEEDS = [
	{
		value: 'gentle',
		label: __( 'Gentle', 'lw-img' ),
		help: __( 'Gentle — lightest server load', 'lw-img' ),
	},
	{
		value: 'normal',
		label: __( 'Normal', 'lw-img' ),
		help: __( 'Normal', 'lw-img' ),
	},
	{
		value: 'fast',
		label: __( 'Fast', 'lw-img' ),
		help: __( 'Fast — highest server load', 'lw-img' ),
	},
];

export const RULE_ACTIONS = [
	{
		value: 'exclude',
		label: __( 'Skip entirely (never sent to the API)', 'lw-img' ),
	},
	{ value: 'keep_size', label: __( 'Keep original dimensions', 'lw-img' ) },
	{ value: 'level', label: __( 'Use optimization level…', 'lw-img' ) },
	{ value: 'keep_exif', label: __( 'Keep EXIF metadata', 'lw-img' ) },
];

/**
 * Local choice list restricted to the values the server allows (meta), in
 * the local order; an unknown server value is appended with the server's
 * raw value so it stays selectable.
 *
 * @param {Array} local  Local choices.
 * @param {Array} server Allowed values from meta (strings; may be empty).
 * @return {Array} Choices.
 */
export function allowed( local, server = [] ) {
	if ( ! server.length ) {
		return local;
	}
	const known = local.filter( ( c ) => server.includes( c.value ) );
	const extra = server
		.filter( ( value ) => ! local.some( ( l ) => l.value === value ) )
		.map( ( value ) => ( { value, label: value } ) );
	return [ ...known, ...extra ];
}

/**
 * Choice of a value, or a stub labelled with the raw value.
 *
 * @param {Array}  list  Choices.
 * @param {string} value Value.
 * @return {Object} Choice.
 */
export const choiceOf = ( list, value ) =>
	list.find( ( c ) => c.value === value ) || {
		value,
		label: String( value ?? '' ),
		help: '',
	};
