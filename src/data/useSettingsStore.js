/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import { api, errorMessage, fieldErrors } from './api';

const same = ( a, b ) => JSON.stringify( a ) === JSON.stringify( b );

// Error keys that belong to an option: `rules.3.pattern` → pattern_rules.
const optionOfError = ( key ) =>
	key.startsWith( 'rules.' ) ? 'pattern_rules' : key.split( '.' )[ 0 ];

/**
 * The lw_img_options draft. Save sends only changed keys (never a key pinned
 * in wp-config.php, never the no-UI keys, which are never edited), so every
 * omitted key survives on the server. `api_key` is write-only: null = keep,
 * a string = replace, '' = remove. A `400 lw_img_invalid` keeps the draft
 * and puts `data.fields` next to each field; the server saved nothing.
 *
 * @return {Object} Store: data { options, meta }, set, hasEdits, save, discard…
 */
export default function useSettingsStore() {
	const [ server, setServer ] = useState( null );
	const [ options, setOptions ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ errors, setErrors ] = useState( {} );
	const [ isSaving, setIsSaving ] = useState( false );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );

	const apply = useCallback( ( data ) => {
		setServer( data );
		setOptions( data.options );
		setErrors( {} );
	}, [] );

	const reload = useCallback( () => {
		setError( null );
		return api
			.settings()
			.then( apply, ( e ) => setError( errorMessage( e ) ) );
	}, [ apply ] );

	useEffect( () => {
		reload();
	}, [ reload ] );

	const locked = server?.meta.locked || [];
	const patch = options
		? Object.fromEntries(
				Object.keys( options )
					.filter(
						( key ) =>
							! locked.includes( key ) &&
							! same( options[ key ], server.options[ key ] )
					)
					.map( ( key ) => [ key, options[ key ] ] )
			)
		: {};
	const hasEdits = Object.keys( patch ).length > 0;

	const set = ( key, value ) => {
		setOptions( ( prev ) => ( { ...prev, [ key ]: value } ) );
		setErrors( ( prev ) => {
			const next = Object.fromEntries(
				Object.entries( prev ).filter(
					( [ k ] ) => optionOfError( k ) !== key
				)
			);
			return Object.keys( next ).length === Object.keys( prev ).length
				? prev
				: next;
		} );
	};

	/**
	 * A value another route already saved (bulk speed): the server copy and
	 * the draft both take it, so it never shows up as an unsaved edit.
	 *
	 * @param {string} key   Option key.
	 * @param {*}      value Saved value.
	 */
	const absorb = useCallback( ( key, value ) => {
		setServer( ( prev ) =>
			prev
				? { ...prev, options: { ...prev.options, [ key ]: value } }
				: prev
		);
		setOptions( ( prev ) => ( prev ? { ...prev, [ key ]: value } : prev ) );
	}, [] );

	const save = async () => {
		if ( ! hasEdits ) {
			return false;
		}
		setIsSaving( true );
		let ok = false;
		try {
			apply( await api.saveSettings( patch ) );
			ok = true;
			createSuccessNotice( __( 'Settings saved.', 'lw-img' ), {
				type: 'snackbar',
			} );
		} catch ( e ) {
			const fields = fieldErrors( e );
			if ( fields ) {
				setErrors( fields );
			}
			createErrorNotice(
				fields
					? __(
							'Nothing was saved. Fix the highlighted fields and save again.',
							'lw-img'
						)
					: errorMessage( e ),
				{ type: 'snackbar' }
			);
		}
		setIsSaving( false );
		return ok;
	};

	return {
		data: options ? { options, meta: server.meta } : null,
		saved: server?.options || {},
		isLoading: ! options && ! error,
		error,
		reload,
		apply,
		absorb,
		isLocked: ( key ) => locked.includes( key ),
		isDirty: ( key ) => key in patch,
		errors,
		set,
		hasEdits,
		isSaving,
		discard: () => {
			setOptions( server.options );
			setErrors( {} );
		},
		save,
	};
}
