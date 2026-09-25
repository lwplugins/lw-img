/**
 * WordPress dependencies
 */
import { Notice } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { backup, file, scheduled, undo } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { NumberInput, SwitchRow, fieldOf } from '../../components/Fields';
import Pipeline from '../../components/Pipeline';
import Section from '../../components/Section';
import Segmented from '../../components/Segmented';
import SettingRow from '../../components/SettingRow';
import { api } from '../../data/api';
import { daysLabel } from '../../data/format';
import useRemote from '../../data/useRemote';
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
	const enabled = !! options.backup_enabled;
	const days = options.backup_retention_days;
	const daysNumber = Number( days ) || 0;
	const preset = presets.find(
		( p ) => days !== '' && p.value === String( days )
	);

	return (
		<>
			<Section
				title={ __( 'Backup', 'lw-img' ) }
				description={ __(
					'Every conversion keeps the original, so nothing is ever lost.',
					'lw-img'
				) }
			>
				<SwitchRow
					title={ __( 'Back up originals', 'lw-img' ) }
					store={ store }
					name="backup_enabled"
					onText={ __(
						'On — every conversion is reversible',
						'lw-img'
					) }
					offText={ __(
						'Off — conversions cannot be undone',
						'lw-img'
					) }
				/>
				<Pipeline
					active={ enabled }
					steps={ [
						{
							icon: backup,
							label: __( 'Original saved', 'lw-img' ),
						},
						{ icon: file, label: meta.backupPath },
						{
							icon: undo,
							label: __( 'Restorable any time', 'lw-img' ),
						},
						{
							icon: scheduled,
							label: daysNumber
								? sprintf(
										/* translators: %d: number of days. */
										__(
											'Cleaned up after %d days',
											'lw-img'
										),
										daysNumber
									)
								: __( 'Kept forever', 'lw-img' ),
						},
					] }
				/>
				{ ! enabled && (
					<Notice status="warning" isDismissible={ false }>
						<strong>
							{ __(
								'Originals are deleted after conversion',
								'lw-img'
							) }
						</strong>
						<br />
						{ __(
							'With backups off there is no way to restore an image once it has been converted — Restore original disappears from the Media Library, and re-optimizing at another level becomes impossible. Existing backups are kept and still restorable.',
							'lw-img'
						) }
					</Notice>
				) }
			</Section>

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
