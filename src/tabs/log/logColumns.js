/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import StatusBadge from '../../components/StatusBadge';
import { datetime, formatBytes, formatDecimal } from '../../data/format';

export const TYPES = {
	converted: [ 'ok', __( 'Converted', 'lw-img' ) ],
	skipped: [ 'idle', __( 'Skipped', 'lw-img' ) ],
	failed: [ 'critical', __( 'Failed', 'lw-img' ) ],
	restored: [ 'info', __( 'Restored', 'lw-img' ) ],
};

function details( e ) {
	if ( e.status === 'converted' ) {
		return (
			<span
				title={ [ e.mime, '→', e.mimeTo, e.jobId ]
					.filter( Boolean )
					.join( ' ' ) }
			>
				{ formatBytes( e.sizeIn ) } → { formatBytes( e.sizeOut ) }{ ' ' }
				<strong>
					{ sprintf(
						/* translators: %s: percentage saved. */
						__( '−%s%%', 'lw-img' ),
						formatDecimal( e.percent )
					) }
				</strong>
			</span>
		);
	}
	if ( e.status === 'failed' ) {
		return e.error;
	}
	if ( e.status === 'restored' ) {
		return __( 'original restored from backup', 'lw-img' );
	}
	if ( e.sizeIn && e.sizeOut ) {
		return sprintf(
			/* translators: 1: skip reason, 2: original size, 3: converted size. */
			__( '%1$s — %2$s original, converted would be %3$s', 'lw-img' ),
			e.reason,
			formatBytes( e.sizeIn ),
			formatBytes( e.sizeOut )
		);
	}
	return e.reason;
}

/**
 * Log table columns: time, event chip, file (linked to the attachment when
 * known), details.
 *
 * @return {Array} Columns.
 */
export function logColumns() {
	return [
		{
			id: 'time',
			label: __( 'Time', 'lw-img' ),
			render: ( e ) => (
				<span className="lw-admin-nowrap">{ datetime( e.ts ) }</span>
			),
		},
		{
			id: 'status',
			label: __( 'Event', 'lw-img' ),
			render: ( e ) => {
				const [ status, label ] = TYPES[ e.status ] || [
					'info',
					e.status,
				];
				return <StatusBadge status={ status }>{ label }</StatusBadge>;
			},
		},
		{
			id: 'file',
			label: __( 'File', 'lw-img' ),
			render: ( e ) => (
				<code className="lw-admin-code lw-admin-clip" title={ e.file }>
					{ e.editUrl ? (
						<a href={ e.editUrl }>{ e.file }</a>
					) : (
						e.file
					) }
				</code>
			),
		},
		{
			id: 'details',
			label: __( 'Details', 'lw-img' ),
			render: ( e ) => (
				<span className="lw-img-logdetail">{ details( e ) }</span>
			),
		},
	];
}
