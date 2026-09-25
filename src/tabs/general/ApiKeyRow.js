/**
 * WordPress dependencies
 */
import { Button, TextControl } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
import { createInterpolateElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, lock, seen, unseen } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import SettingRow from '../../components/SettingRow';

/**
 * The API key: write-only. The server only tells whether a key is set, where
 * it comes from and a hint (prefix + last 4). A typed key replaces it on
 * Save; "Remove key" clears it on Save; a wp-config.php constant pins it.
 *
 * @param {Object}   props
 * @param {Object}   props.store  Settings store.
 * @param {Function} props.onSave Enter in the field saves.
 */
export default function ApiKeyRow( { store, onSave } ) {
	const [ visible, setVisible ] = useState( false );
	const id = useInstanceId( ApiKeyRow, 'lw-img-api-key' );
	const { apiKey, lockedConstants } = store.data.meta;
	const draft = store.data.options.api_key;
	const errors = store.errors.api_key || [];
	const hint = apiKey.hint ? <code>{ apiKey.hint }</code> : null;

	if ( store.isLocked( 'api_key' ) ) {
		// One read-only line: nothing to edit, so no two-column field row.
		return (
			<p className="lw-img-keyline">
				<strong>{ __( 'API key', 'lw-img' ) }</strong>
				<Icon icon={ lock } size={ 16 } />
				<span>
					{ sprintf(
						/* translators: %s: PHP constant name. */
						__( 'Set in wp-config.php (%s)', 'lw-img' ),
						lockedConstants.api_key || 'LW_IMG_API_KEY'
					) }
				</span>
				{ hint }
			</p>
		);
	}

	let status = __( 'No key saved yet.', 'lw-img' );
	if ( draft === '' ) {
		status = __( 'The saved key will be removed when you save.', 'lw-img' );
	} else if ( apiKey.set ) {
		status = createInterpolateElement(
			__( 'Saved key: <hint />', 'lw-img' ),
			{ hint: hint || <span>•••</span> }
		);
	}

	return (
		<SettingRow
			title={ __( 'API key', 'lw-img' ) }
			htmlFor={ id }
			errors={ errors }
			help={ __(
				'Stored in wp_options and never shown again after saving.',
				'lw-img'
			) }
		>
			<p className="lw-admin-hint lw-img-keystatus">{ status }</p>
			<div className="lw-admin-inline lw-img-keyrow">
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					id={ id }
					label={ __( 'HelloImg API key', 'lw-img' ) }
					hideLabelFromVision
					type={ visible ? 'text' : 'password' }
					placeholder={
						apiKey.set
							? __( 'Paste a new key to replace it', 'lw-img' )
							: 'himg_...'
					}
					autoComplete="off"
					spellCheck={ false }
					value={ draft || '' }
					onChange={ ( value ) => {
						const key = value.trim();
						store.set( 'api_key', key === '' ? null : key );
					} }
					onKeyDown={ ( event ) => {
						if ( event.key === 'Enter' ) {
							event.preventDefault();
							onSave();
						}
					} }
				/>
				<Button
					__next40pxDefaultSize
					variant="secondary"
					icon={ visible ? unseen : seen }
					label={
						visible
							? __( 'Hide key', 'lw-img' )
							: __( 'Show key', 'lw-img' )
					}
					isPressed={ visible }
					onClick={ () => setVisible( ! visible ) }
				/>
				{ apiKey.set && draft !== '' && (
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						isDestructive
						onClick={ () => store.set( 'api_key', '' ) }
					>
						{ __( 'Remove key', 'lw-img' ) }
					</Button>
				) }
				{ draft === '' && (
					<Button
						__next40pxDefaultSize
						variant="tertiary"
						onClick={ () => store.set( 'api_key', null ) }
					>
						{ __( 'Keep the key', 'lw-img' ) }
					</Button>
				) }
			</div>
		</SettingRow>
	);
}
