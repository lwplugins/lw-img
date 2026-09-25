/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { SwitchRow } from '../../components/Fields';
import Section from '../../components/Section';
import LogTable from './LogTable';

/**
 * Logging switch (saved with the top bar) + the event log.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function LogTab( { store } ) {
	return (
		<>
			<Section
				title={ __( 'Log', 'lw-img' ) }
				description={ __(
					'The last 200 events — uploads, bulk runs, Media Library actions and restores: what converted, what was skipped, and why.',
					'lw-img'
				) }
			>
				<SwitchRow
					title={ __( 'Logging enabled', 'lw-img' ) }
					help={ __(
						'existing entries remain until cleared',
						'lw-img'
					) }
					store={ store }
					name="enable_log"
				/>
			</Section>
			<LogTable />
		</>
	);
}
