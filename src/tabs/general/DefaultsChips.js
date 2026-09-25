/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { daysLabel } from '../../data/format';
import { LEVELS, SPEEDS, choiceOf } from '../../data/labels';
import { tabLabel } from '../../shell/tabs';

/**
 * Saved defaults as chips, each linking to the tab that changes it.
 *
 * @param {Object} props
 * @param {Object} props.options Saved options.
 */
export default function DefaultsChips( { options } ) {
	const retention = options.backup_retention_days
		? daysLabel( options.backup_retention_days )
		: __( 'forever', 'lw-img' );
	const chips = [
		[ 'upload', options.output_format === 'avif' ? 'AVIF' : 'WebP' ],
		[
			'upload',
			sprintf(
				/* translators: %s: optimization level name. */
				__( '%s level', 'lw-img' ),
				choiceOf( LEVELS, options.level ).label
			),
		],
		[
			'upload',
			options.keep_exif
				? __( 'EXIF kept', 'lw-img' )
				: __( 'EXIF stripped', 'lw-img' ),
		],
		[
			'backup',
			options.backup_enabled
				? sprintf(
						/* translators: %s: retention, e.g. "30 days" or "forever". */
						__( 'backups on · %s', 'lw-img' ),
						retention
					)
				: __( 'backups off', 'lw-img' ),
		],
		[
			'bulk',
			sprintf(
				/* translators: %s: bulk speed name. */
				__( 'bulk speed: %s', 'lw-img' ),
				choiceOf( SPEEDS, options.bulk_speed ).label
			),
		],
	];

	return (
		<div className="lw-img-chips">
			<span className="lw-admin-muted">
				{ __( 'New uploads:', 'lw-img' ) }
			</span>
			{ chips.map( ( [ tab, label ] ) => (
				<a key={ label } className="lw-img-chip" href={ `#${ tab }` }>
					{ label }
					<span>{ tabLabel( tab ) }</span>
				</a>
			) ) }
		</div>
	);
}
