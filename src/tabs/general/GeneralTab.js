/**
 * WordPress dependencies
 */
import { useEffect, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NumberRow } from '../../components/Fields';
import Section from '../../components/Section';
import AccountTiles from './AccountTiles';
import ConnectionSection from './ConnectionSection';
import DefaultsChips from './DefaultsChips';
import Onboarding from './Onboarding';

/**
 * Connection hero, then Account + Current defaults (connected) or the first
 * steps (no key), then Advanced.
 *
 * @param {Object} props
 * @param {Object} props.store   Settings store.
 * @param {Object} props.account useRemote( account ).
 */
export default function GeneralTab( { store, account } ) {
	const { apiKey, dashboardUrl, dashboardHost } = store.data.meta;
	const connected = !! account.data?.connected;
	const keySignature = `${ apiKey.set }|${ apiKey.source }|${ apiKey.hint }`;
	const firstSignature = useRef( keySignature );

	// A saved or removed key makes the cached account digest stale.
	useEffect( () => {
		if ( keySignature !== firstSignature.current ) {
			firstSignature.current = keySignature;
			account.reload( true );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ keySignature ] );

	return (
		<>
			<ConnectionSection store={ store } account={ account } />

			{ connected && (
				<Section title={ __( 'Account', 'lw-img' ) }>
					<AccountTiles
						account={ account.data }
						dashboardUrl={ dashboardUrl }
						dashboardHost={ dashboardHost }
					/>
				</Section>
			) }

			{ connected && (
				<Section title={ __( 'Current defaults', 'lw-img' ) }>
					<DefaultsChips options={ store.saved } />
				</Section>
			) }

			{ ! apiKey.set && <Onboarding /> }

			<Section title={ __( 'Advanced', 'lw-img' ) }>
				<NumberRow
					title={ __( 'Request timeout (s)', 'lw-img' ) }
					help={ __(
						"How long to wait for the API per image. Slow jobs past the API's own 30 s window are polled automatically before giving up.",
						'lw-img'
					) }
					store={ store }
					name="request_timeout"
					unit={ __( 'seconds', 'lw-img' ) }
				/>
			</Section>
		</>
	);
}
