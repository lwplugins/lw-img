/**
 * WordPress dependencies
 */
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import LoadError from '../../components/LoadError';
import ResultBox from '../../components/ResultBox';
import Section from '../../components/Section';
import {
	SkeletonBlock,
	SkeletonRegion,
	SkeletonRows,
	SkeletonSection,
	SkeletonTiles,
} from '../../components/skeleton';
import { errorMessage } from '../../data/api';
import BulkControls from './BulkControls';
import LibraryTiles from './LibraryTiles';
import RunPanel from './RunPanel';
import RunSummary from './RunSummary';

const BulkSkeleton = () => (
	<SkeletonRegion className="lw-skel-tab">
		<SkeletonSection>
			<SkeletonBlock height={ 40 } width={ 220 } />
			<SkeletonRows count={ 2 } />
		</SkeletonSection>
		<SkeletonTiles count={ 4 } />
	</SkeletonRegion>
);

/**
 * Bulk dashboard: current run (live while running), controls with the real
 * start gate, the settings the run uses, all-time library totals (never
 * mixed with the run's counters), CLI hint.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 * @param {Object} props.bulk  useBulk().
 */
export default function BulkTab( { store, bulk } ) {
	const [ result, setResult ] = useState( null );

	useEffect( () => {
		bulk.ensure();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const data = bulk.data;
	if ( ! data ) {
		return bulk.error ? (
			<LoadError
				message={ errorMessage( bulk.error ) }
				onRetry={ bulk.reload }
			/>
		) : (
			<BulkSkeleton />
		);
	}

	/**
	 * Runs an action; its notice (e.g. "12 images queued — press Start")
	 * or error (start refusals: no key, key rejected, redirects, nothing to
	 * do) shows inline under the controls.
	 *
	 * @param {Function} call Returns a promise of a status.
	 * @return {Promise<Object|null>} Status, or null on failure.
	 */
	const run = async ( call ) => {
		setResult( null );
		try {
			const status = await bulk.act( call );
			if ( status.notice?.message ) {
				setResult( status.notice );
			}
			return status;
		} catch ( e ) {
			setResult( { tone: 'error', message: errorMessage( e ) } );
			return null;
		}
	};

	return (
		<>
			<Section
				title={ __( 'Bulk optimize', 'lw-img' ) }
				description={ __(
					'Runs in the background — you can close this tab. References to converted files in post content and page-builder data are rewritten automatically.',
					'lw-img'
				) }
			>
				{ bulk.error && (
					<ResultBox
						tone="warning"
						message={ __(
							'The last status update failed; retrying in a few seconds.',
							'lw-img'
						) }
					/>
				) }
				<RunPanel
					data={ data }
					elapsed={ bulk.elapsed }
					since={ bulk.since }
					dashboardUrl={ store.data.meta.dashboardUrl }
				/>
				<BulkControls data={ data } run={ run } store={ store } />
				{ result && (
					<ResultBox
						tone={ result.tone }
						message={ result.message }
					/>
				) }
			</Section>

			{ data.state !== 'running' && (
				<RunSummary options={ store.saved } />
			) }

			<LibraryTiles data={ data } />

			<Section>
				<p className="lw-admin-hint">
					{ __(
						'WP-Cron only runs while the site receives traffic. For very large libraries the command line is the guaranteed path:',
						'lw-img'
					) }{ ' ' }
					<code>wp lw-img optimize --all</code>
				</p>
			</Section>
		</>
	);
}
