/**
 * WordPress dependencies
 */
// Core has no stable segmented control yet (same as the sibling LW admins).
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */

/**
 * Segmented control (core ToggleGroupControl, restyled in admin.scss).
 *
 * @param {Object}   props
 * @param {string}   props.label    Accessible label (hidden).
 * @param {string}   props.value    Selected value (undefined = none).
 * @param {Array}    props.options  [ { value, label } ].
 * @param {Function} props.onChange Receives the value.
 * @param {boolean}  props.disabled Disabled.
 */
export default function Segmented( {
	label,
	value,
	options,
	onChange,
	disabled = false,
} ) {
	return (
		<ToggleGroupControl
			__next40pxDefaultSize
			__nextHasNoMarginBottom
			isBlock
			hideLabelFromVision
			label={ label }
			value={ value }
			onChange={ onChange }
			disabled={ disabled }
		>
			{ options.map( ( option ) => (
				<ToggleGroupControlOption
					key={ option.value }
					value={ option.value }
					label={ option.label }
					disabled={ disabled }
				/>
			) ) }
		</ToggleGroupControl>
	);
}
