/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Section from '../../components/Section';
import { mediaLibraryUrl } from '../../data/boot';
import { formatNumber } from '../../data/format';

/**
 * All-time Media Library totals (the table), each linking to the filtered
 * Media Library, plus the most common skip reasons.
 *
 * @param {Object} props
 * @param {Object} props.data Bulk status.
 */
export default function LibraryTiles( { data } ) {
	const tiles = [
		[ 'pending', __( 'Pending', 'lw-img' ), data.library.pending ],
		[ 'optimized', __( 'Optimized', 'lw-img' ), data.library.optimized ],
		[ 'skipped', __( 'Skipped', 'lw-img' ), data.library.skipped ],
		[ 'failed', __( 'Failed', 'lw-img' ), data.library.failed ],
	];

	return (
		<Section
			title={ __( 'Media Library', 'lw-img' ) }
			description={ __(
				'All-time totals, independent of the current run.',
				'lw-img'
			) }
		>
			<div className="lw-admin-tiles">
				{ tiles.map( ( [ status, label, count ] ) => (
					<a
						key={ status }
						className={ `lw-admin-tile lw-img-libtile is-${ status }` }
						href={ mediaLibraryUrl( status ) }
						title={ __(
							'Show these images in the Media Library',
							'lw-img'
						) }
					>
						<span className="lw-admin-tile__label">{ label }</span>
						<span className="lw-admin-tile__value">
							{ formatNumber( count ) }
						</span>
					</a>
				) ) }
			</div>
			{ data.skipReasons.length > 0 && (
				<div className="lw-img-chips">
					<span className="lw-admin-muted">
						{ __( 'Skip reasons', 'lw-img' ) }
					</span>
					{ data.skipReasons.map( ( r ) => (
						<span
							key={ r.reason }
							className="lw-img-chip is-static"
						>
							{ sprintf(
								/* translators: 1: skip reason, 2: number of images. */
								__( '%1$s · %2$s', 'lw-img' ),
								r.reason,
								formatNumber( r.count )
							) }
						</span>
					) ) }
				</div>
			) }
		</Section>
	);
}
