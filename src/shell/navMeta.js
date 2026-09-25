/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import { Icon, caution } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import StatusBadge from '../components/StatusBadge';
import { formatNumber, percent } from '../data/format';
import { tabOfField } from './tabs';

/**
 * Nav extras: a flag on every tab holding a field the last save rejected,
 * the connection dot on General, run progress on Bulk, and the Tester's
 * problem count once the report has been loaded.
 *
 * @param {Object}      props
 * @param {Object}      props.errors  Field errors { key: [ messages ] }.
 * @param {Object|null} props.account Account digest.
 * @param {Object|null} props.bulk    Bulk status.
 * @param {Object|null} props.tester  Tester report.
 * @return {Object} { tabId: node }.
 */
export default function navMeta( { errors, account, bulk, tester } ) {
	const meta = {};

	if ( account ) {
		let dot = 'idle';
		if ( account.connected ) {
			dot = 'ok';
		} else if ( account.error ) {
			dot = 'error';
		}
		if ( dot !== 'idle' ) {
			meta.general = (
				<span className={ `lw-admin-sidenav__dot is-${ dot }` }>
					<span className="screen-reader-text">
						{ dot === 'ok'
							? __( 'Connected', 'lw-img' )
							: __( 'Connection error', 'lw-img' ) }
					</span>
				</span>
			);
		}
	}

	if ( bulk?.state === 'running' ) {
		const pct = Math.min(
			100,
			Math.floor( percent( bulk.run.processed, bulk.run.total ) )
		);
		meta.bulk = (
			<StatusBadge status="info">
				{ sprintf(
					/* translators: %s: percentage of the bulk run done. */
					__( '%s%%', 'lw-img' ),
					formatNumber( pct )
				) }
			</StatusBadge>
		);
	}

	const problems = tester
		? tester.counts.critical + tester.counts.warning
		: 0;
	if ( problems ) {
		meta.tester = (
			<StatusBadge
				status={ tester.counts.critical ? 'critical' : 'warning' }
			>
				<span aria-hidden="true">{ String( problems ) }</span>
				<span className="screen-reader-text">
					{ sprintf(
						/* translators: %d: number of failed environment checks. */
						_n( '%d problem', '%d problems', problems, 'lw-img' ),
						problems
					) }
				</span>
			</StatusBadge>
		);
	}

	Object.keys( errors ).forEach( ( key ) => {
		const tab = tabOfField( key );
		if ( tab ) {
			meta[ tab ] = (
				<span className="lw-admin-navflag">
					<Icon icon={ caution } size={ 18 } />
					<span className="screen-reader-text">
						{ __( 'Has invalid fields', 'lw-img' ) }
					</span>
				</span>
			);
		}
	} );

	return meta;
}
