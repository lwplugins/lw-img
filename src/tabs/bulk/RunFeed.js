/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import StatusBadge from '../../components/StatusBadge';
import { clockTime } from '../../data/format';

const RESULTS = {
	optimized: [ 'ok', __( 'Optimized', 'lw-img' ) ],
	skipped: [ 'idle', __( 'Skipped', 'lw-img' ) ],
	failed: [ 'critical', __( 'Failed', 'lw-img' ) ],
};

/**
 * Recent activity of the run (newest first, as the server sends it).
 *
 * @param {Object}  props
 * @param {Array}   props.items   Feed items.
 * @param {boolean} props.running Run in progress.
 */
export default function RunFeed( { items, running } ) {
	if ( ! items.length ) {
		return running ? (
			<p className="lw-admin-hint">
				{ __(
					'Waiting for the first background tick… WP-Cron starts within a minute on most hosts.',
					'lw-img'
				) }
			</p>
		) : null;
	}

	return (
		<ul
			className="lw-img-feed"
			aria-label={ __( 'Recent activity', 'lw-img' ) }
		>
			{ items.map( ( item ) => {
				const [ status, label ] = RESULTS[ item.result ] || [
					'info',
					item.result,
				];
				return (
					<li key={ item.key }>
						<span className="lw-img-feed__time">
							{ clockTime( item.ts ) }
						</span>
						<span className="lw-img-feed__name">
							{ item.label }
						</span>
						<span className="lw-img-feed__result">
							<StatusBadge status={ status }>
								{ item.result === 'optimized' && item.detail
									? item.detail
									: label }
							</StatusBadge>
							{ item.result !== 'optimized' && item.detail && (
								<span className="lw-admin-hint">
									{ item.detail }
								</span>
							) }
						</span>
					</li>
				);
			} ) }
		</ul>
	);
}
