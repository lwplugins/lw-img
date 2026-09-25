/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import StatTile from '../../components/StatTile';
import StatusBadge from '../../components/StatusBadge';
import {
	formatBytes,
	formatDecimal,
	formatDuration,
	formatEta,
	formatNumber,
	formatSpeed,
	percent,
} from '../../data/format';
import RunBanner from './RunBanner';
import RunFeed from './RunFeed';

const STATE_BADGES = {
	idle: [ 'idle', __( 'Ready', 'lw-img' ) ],
	running: [ 'info', __( 'Running', 'lw-img' ) ],
	done: [ 'ok', __( 'Finished', 'lw-img' ) ],
	cancelled: [ 'warning', __( 'Cancelled', 'lw-img' ) ],
	halted: [ 'critical', __( 'Stopped', 'lw-img' ) ],
};

/**
 * The CURRENT run: hero percentage, segmented progress, elapsed / speed /
 * ETA / saved, now processing, live feed — plus the outcome banner once the
 * run is over (finished, cancelled, or halted with the reason).
 *
 * @param {Object} props
 * @param {Object} props.data         Bulk status.
 * @param {number} props.elapsed      Live elapsed seconds.
 * @param {number} props.since        Seconds since the status arrived.
 * @param {string} props.dashboardUrl HelloImg dashboard.
 */
export default function RunPanel( { data, elapsed, since, dashboardUrl } ) {
	const { state, run } = data;
	const [ badge, badgeLabel ] = STATE_BADGES[ state ];
	if ( state === 'idle' ) {
		return null;
	}
	const banner = <RunBanner data={ data } dashboardUrl={ dashboardUrl } />;
	if ( ! run.exists ) {
		return banner;
	}

	const running = state === 'running';
	const { pending } = run;
	const pct = run.percent; // Server-clamped to ≤ 100.
	const segments = [
		[ 'ok', __( 'Optimized', 'lw-img' ), run.optimized ],
		[ 'skip', __( 'Skipped', 'lw-img' ), run.skipped ],
		[ 'fail', __( 'Failed', 'lw-img' ), run.failed ],
		[ 'pending', __( 'Pending', 'lw-img' ), pending ],
	];
	const width = ( count ) =>
		`${ Math.max( 0, Math.min( 100, percent( count, Math.max( run.total, run.processed ) ) ) ) }%`;

	return (
		<div className="lw-img-run">
			{ banner }
			<div className="lw-img-run__hero">
				<span className="lw-img-run__pct">
					{ sprintf(
						/* translators: %s: percentage of the run done. */
						__( '%s%%', 'lw-img' ),
						formatDecimal( pct )
					) }
				</span>
				<span className="lw-admin-muted">
					{ sprintf(
						/* translators: 1: images processed, 2: images in the run. */
						__( '%1$s / %2$s processed', 'lw-img' ),
						formatNumber( run.processed ),
						formatNumber( run.total )
					) }
				</span>
				<StatusBadge status={ badge }>{ badgeLabel }</StatusBadge>
			</div>
			<div
				className="lw-img-segments"
				role="progressbar"
				aria-valuemin={ 0 }
				aria-valuemax={ 100 }
				aria-valuenow={ Math.round( pct ) }
				aria-label={ __( 'Bulk run progress', 'lw-img' ) }
			>
				{ segments.slice( 0, 3 ).map( ( [ id, , count ] ) => (
					<span
						key={ id }
						className={ `is-${ id }` }
						style={ { width: width( count ) } }
					/>
				) ) }
			</div>
			<ul className="lw-img-segments__legend">
				{ segments.map( ( [ id, label, count ] ) => (
					<li key={ id } className={ `is-${ id }` }>
						{ label } <strong>{ formatNumber( count ) }</strong>
					</li>
				) ) }
			</ul>
			<div className="lw-admin-tiles">
				<StatTile
					label={ __( 'Elapsed', 'lw-img' ) }
					value={ elapsed ? formatDuration( elapsed ) : '—' }
				/>
				<StatTile
					label={ __( 'Speed', 'lw-img' ) }
					value={ formatSpeed( run.processed, elapsed ) }
				/>
				<StatTile
					label={ __( 'Est. remaining', 'lw-img' ) }
					value={
						running && run.eta !== null
							? formatEta( run.eta - since )
							: '—'
					}
				/>
				<StatTile
					label={ __( 'Saved so far', 'lw-img' ) }
					value={ formatBytes( run.bytesSaved ) }
				/>
			</div>
			{ running && data.stalled && (
				<Callout tone="warning">
					{ __(
						'The background worker has not reported for a while. While this page is open it keeps the run moving; WP-CLI (wp lw-img optimize --all) is the reliable path on sites without traffic.',
						'lw-img'
					) }
				</Callout>
			) }
			{ running && (
				<p className="lw-img-now">
					<span className="lw-admin-muted">
						{ __( 'Now processing', 'lw-img' ) }
					</span>{ ' ' }
					<code>{ run.current || '…' }</code>
				</p>
			) }
			<RunFeed items={ data.recent } running={ running } />
		</div>
	);
}
