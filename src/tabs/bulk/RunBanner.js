/**
 * WordPress dependencies
 */
import { Notice } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { mediaLibraryUrl } from '../../data/boot';
import {
	formatBytes,
	formatDecimal,
	formatDuration,
	formatNumber,
} from '../../data/format';

// Halt reasons (no_key|quota|auth): the key → General tab, quota → dashboard.
const KEY_REASONS = [ 'no_key', 'auth' ];

/**
 * Outcome of the last run: finished (with the skipped link), cancelled, or
 * halted with the server's reason — the classic screen showed nothing for
 * cancelled/halted runs (the reason was only in the Log).
 *
 * @param {Object} props
 * @param {Object} props.data         Bulk status.
 * @param {string} props.dashboardUrl HelloImg dashboard.
 */
export default function RunBanner( { data, dashboardUrl } ) {
	const { state, run, halt } = data;

	if ( state === 'done' ) {
		const duration =
			run.finishedAt && run.startedAt
				? run.finishedAt - run.startedAt
				: run.elapsed;
		return (
			<Notice status="success" isDismissible={ false }>
				{ sprintf(
					/* translators: 1: images optimized, 2: duration, 3: bytes saved, 4: percentage saved. */
					__(
						'Run finished — %1$s images optimized in %2$s, %3$s saved (−%4$s%%).',
						'lw-img'
					),
					formatNumber( run.optimized ),
					formatDuration( duration ),
					formatBytes( run.bytesSaved ),
					formatDecimal( run.savedPercent )
				) }
				{ run.skipped > 0 && (
					<>
						{ ' ' }
						<a href={ mediaLibraryUrl( 'skipped' ) }>
							{ sprintf(
								/* translators: %s: number of skipped images. */
								__(
									'See the %s skipped images in the Media Library',
									'lw-img'
								),
								formatNumber( run.skipped )
							) }
						</a>
					</>
				) }
			</Notice>
		);
	}

	if ( state === 'cancelled' ) {
		return (
			<Notice status="warning" isDismissible={ false }>
				{ sprintf(
					/* translators: 1: images processed, 2: images in the run. */
					__(
						'Run cancelled — %1$s of %2$s images processed. Converted images stay converted; press Start to continue with the rest.',
						'lw-img'
					),
					formatNumber( run.processed ),
					formatNumber( run.total )
				) }
			</Notice>
		);
	}

	if ( state === 'halted' ) {
		const reason = halt?.reason || '';
		let fix = null;
		if ( KEY_REASONS.includes( reason ) ) {
			fix = (
				<a href="#general">
					{ __( 'Check the key on the General tab', 'lw-img' ) }
				</a>
			);
		} else if ( reason === 'quota' ) {
			fix = (
				<a
					href={ dashboardUrl }
					target="_blank"
					rel="noopener noreferrer"
				>
					{ __(
						'Manage your plan in the HelloImg dashboard',
						'lw-img'
					) }
				</a>
			);
		}
		return (
			<Notice status="error" isDismissible={ false }>
				<strong>{ __( 'The run was stopped.', 'lw-img' ) }</strong>{ ' ' }
				{ halt?.message }
				{ halt?.detail && (
					<>
						{ ' ' }
						<span className="lw-admin-hint">{ halt.detail }</span>
					</>
				) }
				{ fix && <> { fix }</> }
			</Notice>
		);
	}

	return null;
}
