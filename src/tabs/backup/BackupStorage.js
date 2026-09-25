/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import LoadError from '../../components/LoadError';
import StatTile from '../../components/StatTile';
import { SkeletonTiles } from '../../components/skeleton';
import { errorMessage } from '../../data/api';
import { datetime, formatBytes, formatNumber } from '../../data/format';

/**
 * Backup folder size / file count / cleanup schedule. `days` is the draft
 * retention, so the Cleanup tile follows the field before saving.
 *
 * @param {Object} props
 * @param {Object} props.storage useRemote( backup ).
 * @param {number} props.days    Retention in days (0 = forever).
 */
export default function BackupStorage( { storage, days } ) {
	if ( ! storage.data ) {
		return storage.error ? (
			<LoadError
				message={ errorMessage( storage.error ) }
				onRetry={ () => storage.reload() }
			/>
		) : (
			<SkeletonTiles />
		);
	}
	const { bytes, files, path, nextCleanup, wpCronDisabled } = storage.data;
	let cleanup = __( 'backups are kept forever', 'lw-img' );
	if ( days ) {
		cleanup = sprintf(
			/* translators: %d: number of days. */
			__( 'removes backups older than %d days', 'lw-img' ),
			days
		);
		if ( wpCronDisabled ) {
			cleanup = sprintf(
				/* translators: %s: "removes backups older than N days". */
				__(
					'%s · runs when the system cron triggers WP-Cron',
					'lw-img'
				),
				cleanup
			);
		} else if ( nextCleanup ) {
			cleanup = sprintf(
				/* translators: 1: "removes backups older than N days", 2: date and time. */
				__( '%1$s · next run %2$s', 'lw-img' ),
				cleanup,
				datetime( nextCleanup )
			);
		}
	}

	return (
		<div className="lw-admin-tiles">
			<StatTile
				label={ __( 'Backup folder', 'lw-img' ) }
				value={ formatBytes( bytes ) }
				detail={ <code>{ path }</code> }
			/>
			<StatTile
				label={ __( 'Backed-up originals', 'lw-img' ) }
				value={
					<>
						{ formatNumber( files ) }{ ' ' }
						<small>{ __( 'files', 'lw-img' ) }</small>
					</>
				}
				detail={ __( 'one per optimized image', 'lw-img' ) }
			/>
			<StatTile
				label={ __( 'Cleanup', 'lw-img' ) }
				value={
					days ? __( 'Daily', 'lw-img' ) : __( 'Never', 'lw-img' )
				}
				detail={ cleanup }
			/>
		</div>
	);
}
