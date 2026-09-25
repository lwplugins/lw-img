/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __, _n, sprintf } from '@wordpress/i18n';
import { update } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import LoadError from '../../components/LoadError';
import Section from '../../components/Section';
import StatusBadge from '../../components/StatusBadge';
import StatusIcon from '../../components/StatusIcon';
import {
	SkeletonBlock,
	SkeletonRegion,
	SkeletonRows,
	SkeletonSection,
} from '../../components/skeleton';
import { errorMessage } from '../../data/api';
import { ago } from '../../data/format';
import CheckSection from './CheckSection';
import FixList from './FixList';

function headline( counts ) {
	if ( counts.critical ) {
		return sprintf(
			/* translators: 1: critical issues, 2: warnings. */
			__( '%1$s, %2$s', 'lw-img' ),
			sprintf(
				/* translators: %d: number of critical issues. */
				_n(
					'%d critical issue',
					'%d critical issues',
					counts.critical,
					'lw-img'
				),
				counts.critical
			),
			sprintf(
				/* translators: %d: number of warnings. */
				_n( '%d warning', '%d warnings', counts.warning, 'lw-img' ),
				counts.warning
			)
		);
	}
	if ( counts.warning ) {
		return sprintf(
			/* translators: %d: number of warnings. */
			_n(
				'%d warning found',
				'%d warnings found',
				counts.warning,
				'lw-img'
			),
			counts.warning
		);
	}
	return __( 'All checks passed — ready for bulk optimization', 'lw-img' );
}

const PILLS = [
	[
		'critical',
		( n ) =>
			sprintf(
				/* translators: %d: count. */ _n(
					'%d critical',
					'%d critical',
					n,
					'lw-img'
				),
				n
			),
	],
	[
		'warning',
		( n ) =>
			sprintf(
				/* translators: %d: count. */ _n(
					'%d warning',
					'%d warnings',
					n,
					'lw-img'
				),
				n
			),
	],
	[
		'ok',
		( n ) =>
			sprintf( /* translators: %d: count. */ __( '%d OK', 'lw-img' ), n ),
	],
	[
		'info',
		( n ) =>
			sprintf(
				/* translators: %d: count. */ __( '%d info', 'lw-img' ),
				n
			),
	],
];

/**
 * Environment report: verdict, count pills, needs-attention (with copyable
 * fixes), one card per section. Cached 10 minutes on the server; "Run tests
 * again" bypasses the cache.
 *
 * @param {Object} props
 * @param {Object} props.tester useRemote( tester ), lazy.
 */
export default function TesterTab( { tester } ) {
	useEffect( () => {
		tester.ensure();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const data = tester.data;
	if ( ! data ) {
		return tester.error ? (
			<LoadError
				message={ errorMessage( tester.error ) }
				onRetry={ () => tester.reload() }
			/>
		) : (
			<SkeletonRegion
				className="lw-skel-tab"
				label={ __( 'Running environment checks…', 'lw-img' ) }
			>
				<SkeletonSection>
					<SkeletonBlock height={ 64 } />
				</SkeletonSection>
				<SkeletonSection description={ false }>
					<SkeletonRows count={ 4 } />
				</SkeletonSection>
				<SkeletonSection description={ false }>
					<SkeletonRows count={ 3 } />
				</SkeletonSection>
			</SkeletonRegion>
		);
	}

	const { counts, verdict } = data;

	return (
		<>
			{ tester.error && (
				<LoadError
					message={ errorMessage( tester.error ) }
					onRetry={ () => tester.reload( true ) }
				/>
			) }
			<Section
				title={ __( 'Tester', 'lw-img' ) }
				description={ __(
					'Environment checks — hosting problems surface here before a bulk run trips over them.',
					'lw-img'
				) }
				actions={
					<Button
						size="compact"
						variant="secondary"
						icon={ update }
						isBusy={ tester.isLoading }
						disabled={ tester.isLoading }
						accessibleWhenDisabled
						onClick={ () => tester.reload( true ) }
					>
						{ __( 'Run tests again', 'lw-img' ) }
					</Button>
				}
			>
				<div className={ `lw-img-verdict is-${ verdict }` }>
					<StatusIcon
						status={ verdict === 'info' ? 'ok' : verdict }
						size={ 32 }
					/>
					<div>
						<strong>{ headline( counts ) }</strong>
						<span>
							{ sprintf(
								/* translators: %s: relative time, e.g. "3 minutes ago". */
								__(
									'last run %s · cached for 10 minutes',
									'lw-img'
								),
								ago( data.generatedAt )
							) }
						</span>
						<span className="lw-admin-inline lw-img-pills">
							{ PILLS.filter(
								( [ status ] ) => counts[ status ] > 0
							).map( ( [ status, label ] ) => (
								<StatusBadge key={ status } status={ status }>
									{ label( counts[ status ] ) }
								</StatusBadge>
							) ) }
						</span>
					</div>
				</div>
			</Section>

			<FixList items={ data.attention } />

			{ data.sections.length > 0 && (
				<h2 className="lw-img-subhead">
					{ __( 'All checks', 'lw-img' ) }
				</h2>
			) }
			{ data.sections.map( ( section ) => (
				<CheckSection key={ section.id } section={ section } />
			) ) }
		</>
	);
}
