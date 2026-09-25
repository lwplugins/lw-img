/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, arrowRight, gallery, update } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import LoadError from '../../components/LoadError';
import Section from '../../components/Section';
import StatTile from '../../components/StatTile';
import { errorMessage } from '../../data/api';
import {
	ago,
	formatBytes,
	formatDecimal,
	formatNumber,
	percent,
} from '../../data/format';
import Leftovers from './Leftovers';
import StatsSkeleton from './StatsSkeleton';

/**
 * Savings hero with the before/after bar, tiles, biggest wins, leftovers of
 * other optimizers. Figures are cached for an hour on the server; "Refresh
 * figures" bypasses the cache.
 *
 * @param {Object} props
 * @param {Object} props.stats useRemote( stats ), lazy.
 */
export default function StatsTab( { stats } ) {
	useEffect( () => {
		stats.ensure();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const data = stats.data;
	if ( ! data ) {
		return stats.error ? (
			<LoadError
				message={ errorMessage( stats.error ) }
				onRetry={ () => stats.reload() }
			/>
		) : (
			<StatsSkeleton />
		);
	}

	const retention = data.retentionDays;
	const refresh = (
		<div className="lw-admin-inline">
			<span className="lw-admin-hint">
				{ sprintf(
					/* translators: %s: relative time, e.g. "12 minutes ago". */
					__( 'Updated %s, refreshes hourly on its own.', 'lw-img' ),
					ago( data.generatedAt )
				) }
			</span>
			<Button
				size="compact"
				variant="secondary"
				icon={ update }
				isBusy={ stats.isLoading }
				disabled={ stats.isLoading }
				accessibleWhenDisabled
				onClick={ () => stats.reload( true ) }
			>
				{ __( 'Refresh figures', 'lw-img' ) }
			</Button>
		</div>
	);

	return (
		<>
			{ stats.error && (
				<LoadError
					message={ errorMessage( stats.error ) }
					onRetry={ () => stats.reload( true ) }
				/>
			) }
			{ data.count === 0 ? (
				<Section
					title={ __( 'Stats', 'lw-img' ) }
					description={ __(
						'What optimization has saved on this site so far.',
						'lw-img'
					) }
					actions={ refresh }
				>
					<div className="lw-img-empty">
						<Icon icon={ gallery } size={ 48 } />
						<strong>
							{ __( 'No optimized images yet', 'lw-img' ) }
						</strong>
						<p>
							{ __(
								'Stats appear here after the first conversion. New uploads convert automatically — for everything already in the Media Library, run a bulk optimization.',
								'lw-img'
							) }
						</p>
						<Button variant="primary" href="#bulk">
							{ __( 'Open the Bulk tab', 'lw-img' ) }
						</Button>
					</div>
				</Section>
			) : (
				<>
					<Section
						title={ __( 'Stats', 'lw-img' ) }
						description={ __(
							'What optimization has saved on this site so far.',
							'lw-img'
						) }
						actions={ refresh }
					>
						<div className="lw-img-savings__big">
							{ formatBytes( data.saved ) }{ ' ' }
							<small>{ __( 'saved', 'lw-img' ) }</small>
							<span className="lw-img-savings__pct">
								{ sprintf(
									/* translators: %s: percentage saved. */
									__( '−%s%%', 'lw-img' ),
									formatDecimal( data.percent )
								) }
							</span>
						</div>
						<div className="lw-img-bars" aria-hidden="true">
							<span className="lw-img-bars__before" />
							<span
								className="lw-img-bars__after"
								style={ {
									width: `${ Math.max(
										1,
										Math.min(
											100,
											percent(
												data.optimized,
												data.original
											)
										)
									) }%`,
								} }
							/>
						</div>
						<div className="lw-img-bars__legend">
							<span>
								<strong>{ __( 'After:', 'lw-img' ) }</strong>{ ' ' }
								{ formatBytes( data.optimized ) }
							</span>
							<span>
								<strong>{ __( 'Before:', 'lw-img' ) }</strong>{ ' ' }
								{ formatBytes( data.original ) }
							</span>
						</div>
					</Section>

					<div className="lw-admin-tiles">
						<StatTile
							label={ __( 'Optimized images', 'lw-img' ) }
							value={ formatNumber( data.count ) }
							meter={
								data.libraryTotal
									? Math.min( 100, data.libraryPercent )
									: undefined
							}
							detail={
								<>
									{ sprintf(
										/* translators: %s: share of the Media Library. */
										__(
											'%s%% of the Media Library',
											'lw-img'
										),
										formatDecimal( data.libraryPercent, 0 )
									) }
									{ ' · ' }
									<a href="#bulk">
										{ __( 'optimize the rest', 'lw-img' ) }
									</a>
								</>
							}
						/>
						<StatTile
							label={ __( 'Average saving', 'lw-img' ) }
							value={
								<>
									{ formatBytes( data.average ) }{ ' ' }
									<small>/ { __( 'image', 'lw-img' ) }</small>
								</>
							}
							detail={ __(
								'across all optimized images',
								'lw-img'
							) }
						/>
						<StatTile
							label={ __( 'Backup folder', 'lw-img' ) }
							value={ formatBytes( data.backup.bytes ) }
							detail={
								<>
									{ sprintf(
										/* translators: 1: number of files, 2: retention ("kept forever" or "30-day retention"). */
										__( '%1$s files · %2$s', 'lw-img' ),
										formatNumber( data.backup.files ),
										retention
											? sprintf(
													/* translators: %d: number of days. */
													__(
														'%d-day retention',
														'lw-img'
													),
													retention
												)
											: __( 'kept forever', 'lw-img' )
									) }
									{ ' · ' }
									<a href="#backup">
										{ __( 'Backup', 'lw-img' ) }
									</a>
								</>
							}
						/>
					</div>

					{ data.wins.length > 0 && (
						<Section title={ __( 'Biggest wins', 'lw-img' ) }>
							<ul className="lw-img-wins">
								{ data.wins.map( ( win ) => (
									<li key={ win.key }>
										<span className="lw-img-wins__file">
											{ win.file }
										</span>
										<span className="lw-img-wins__sizes">
											{ formatBytes( win.original ) }
											<Icon
												icon={ arrowRight }
												size={ 16 }
											/>
											<span className="screen-reader-text">
												{ __( 'to', 'lw-img' ) }
											</span>
											{ formatBytes( win.new ) }
										</span>
										<span className="lw-img-wins__pct">
											{ sprintf(
												/* translators: %s: percentage saved. */
												__( '−%s%%', 'lw-img' ),
												formatDecimal( win.percent, 0 )
											) }
										</span>
									</li>
								) ) }
							</ul>
						</Section>
					) }
				</>
			) }

			<Leftovers stats={ stats } />
		</>
	);
}
