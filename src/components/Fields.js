/**
 * Field shorthands bound to the settings store, so the tabs stay declarative.
 * Every row disables itself when its key is pinned in wp-config.php and shows
 * the server's validation messages for its key.
 */
/**
 * WordPress dependencies
 */
import { SelectControl, TextControl } from '@wordpress/components';

/**
 * Internal dependencies
 */
import { rangeOf } from '../data/ranges';
import SettingRow from './SettingRow';
import ToggleRow from './ToggleRow';

/**
 * SettingRow props for an option key.
 *
 * @param {Object} store Settings store.
 * @param {string} name  Option key.
 * @return {Object} { locked: constant name or '', errors }.
 */
export const fieldOf = ( store, name ) => ( {
	locked: store.isLocked( name )
		? store.data.meta.lockedConstants[ name ] ||
			`LW_IMG_${ name.toUpperCase() }`
		: '',
	errors: store.errors[ name ] || [],
} );

export function SwitchRow( { title, help, store, name, onText, offText } ) {
	const field = fieldOf( store, name );
	return (
		<ToggleRow
			title={ title }
			help={ help }
			checked={ !! store.data.options[ name ] }
			disabled={ !! field.locked }
			onChange={ ( value ) => store.set( name, value ) }
			onText={ onText }
			offText={ offText }
			field={ field }
		/>
	);
}

export function SelectRow( { title, help, store, name, options } ) {
	const field = fieldOf( store, name );
	return (
		<SettingRow title={ title } help={ help } { ...field }>
			<SelectControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ title }
				hideLabelFromVision
				disabled={ !! field.locked }
				value={ String( store.data.options[ name ] ?? '' ) }
				options={ options }
				onChange={ ( value ) => store.set( name, value ) }
			/>
		</SettingRow>
	);
}

export function TextRow( { title, help, store, name, placeholder, type } ) {
	const field = fieldOf( store, name );
	return (
		<SettingRow title={ title } help={ help } { ...field }>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ title }
				hideLabelFromVision
				type={ type || 'text' }
				disabled={ !! field.locked }
				placeholder={ placeholder }
				value={ store.data.options[ name ] ?? '' }
				onChange={ ( value ) => store.set( name, value ) }
			/>
		</SettingRow>
	);
}

/**
 * Bare integer input bound to an option (for rows holding several numbers).
 * min/max are the server's range (meta.ranges), never stricter. A cleared
 * field stays '' in the draft, so the server reports it instead of silently
 * saving the minimum.
 *
 * @param {Object} props
 * @param {string} props.label Accessible label.
 * @param {Object} props.store Settings store.
 * @param {string} props.name  Option key.
 * @param {string} props.unit  Unit after the field (translated).
 */
export function NumberInput( { label, store, name, unit = '' } ) {
	const field = fieldOf( store, name );
	const [ min, max ] = rangeOf( store.data.meta, name );
	const current = store.data.options[ name ];

	return (
		<span
			className={ `lw-admin-inline lw-admin-number ${
				field.errors.length ? 'has-error' : ''
			}` }
		>
			<TextControl
				__next40pxDefaultSize
				__nextHasNoMarginBottom
				label={ label }
				hideLabelFromVision
				type="number"
				min={ min }
				max={ max }
				step={ 1 }
				disabled={ !! field.locked }
				value={ String( current ?? '' ) }
				onChange={ ( value ) =>
					store.set( name, value === '' ? '' : Number( value ) )
				}
			/>
			{ unit && <span className="lw-admin-muted">{ unit }</span> }
		</span>
	);
}

/**
 * Integer option row.
 *
 * @param {Object} props
 * @param {string} props.title Title.
 * @param {string} props.help  Help.
 * @param {Object} props.store Settings store.
 * @param {string} props.name  Option key.
 * @param {string} props.unit  Unit shown after the field (translated).
 */
export function NumberRow( { title, help, store, name, unit = '' } ) {
	return (
		<SettingRow title={ title } help={ help } { ...fieldOf( store, name ) }>
			<NumberInput
				label={ title }
				store={ store }
				name={ name }
				unit={ unit }
			/>
		</SettingRow>
	);
}
