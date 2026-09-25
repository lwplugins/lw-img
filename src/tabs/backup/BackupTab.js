/**
 * WordPress dependencies
 */
import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NumberInput, SwitchRow, fieldOf } from '../../components/Fields';
import Section from '../../components/Section';
import Segmented from '../../components/Segmented';
import SettingRow from '../../components/SettingRow';
import { api } from '../../data/api';
import { daysLabel } from '../../data/format';
import useRemote from '../../data/useRemote';
import BackupHero from './BackupHero';
import BackupStorage from './BackupStorage';
import RestoreGuide from './RestoreGuide';

// Labels of the classic presets; any other server preset gets "%d days".
const PRESET_LABELS = {
	7: __( '7 days', 'lw-img' ),
	30: __( '30 days', 'lw-img' ),
	90: __( '90 days', 'lw-img' ),
	365: __( '1 year', 'lw-img' ),
	0: __( 'Forever', 'lw-img' ),
};

/**
 * Backup lifecycle, danger notice when off, storage (GET /admin/backup),
 * retention presets + custom days, restore guide, uninstall behaviour.
 * Retention stays editable while backups are off (it still cleans up the
 * existing backups).
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function BackupTab( { store } ) {
	const loadBackup = useCallback( () => api.backup(), [] );
	const storage = useRemote( loadBackup );
	const { options, meta } = store.data;
	const presets = meta.retentionPresets.map( ( n ) => ( {
		value: String( n ),
		label: PRESET_LABELS[ n ] ?? daysLabel( n ),
	} ) );
	const days = options.backup_retention_days;
	const daysNumber = Number( days ) || 0;
	const preset = presets.find(
		( p ) => days !== '' && p.value === String( days )
	);

	return (
		<>
			<BackupHero store={ store } days={ daysNumber } />

			<BackupStorage storage={ storage } days={ daysNumber } />

			<Section title={ __( 'Retention', 'lw-img' ) }>
				<SettingRow
					title={ __( 'Keep backups for', 'lw-img' ) }
					help={ __(
						'Backups older than this are deleted by a daily cleanup task. Forever keeps everything — watch the folder size on large sites.',
						'lw-img'
					) }
					stacked
					{ ...fieldOf( store, 'backup_retention_days' ) }
				>
					<div className="lw-img-retention">
						<Segmented
							label={ __( 'Keep backups for', 'lw-img' ) }
							value={ preset?.value }
							options={ presets }
							onChange={ ( value ) =>
								store.set(
									'backup_retention_days',
									Number( value )
								)
							}
						/>
						<span className="lw-admin-muted">
							{ __( 'or', 'lw-img' ) }
						</span>
						<NumberInput
							label={ __( 'Backup retention in days', 'lw-img' ) }
							store={ store }
							name="backup_retention_days"
							unit={ __( 'days', 'lw-img' ) }
						/>
					</div>
				</SettingRow>
			</Section>

			<RestoreGuide />

			<Section title={ __( 'On uninstall', 'lw-img' ) }>
				<SwitchRow
					title={ __(
						'Delete all LW Image data when the plugin is deleted',
						'lw-img'
					) }
					help={ __(
						'Off (default): settings, API key, pattern rules, event log and per-image statistics survive a delete and reinstall. On: they are removed on uninstall. Backup originals are kept either way. The Deactivate link on the Plugins screen asks the same question.',
						'lw-img'
					) }
					store={ store }
					name="delete_on_uninstall"
				/>
			</Section>
		</>
	);
}
