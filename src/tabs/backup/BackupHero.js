/**
 * WordPress dependencies
 */
import { Card, Notice, ToggleControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, backup } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { fieldOf } from '../../components/Fields';

/**
 * Backup state bar: icon, what the switch means right now, the switch, and
 * three facts (where, how to restore, how long). Off shows why it matters.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 * @param {number} props.days  Draft retention in days (0 = forever).
 */
export default function BackupHero( { store, days } ) {
	const enabled = !! store.data.options.backup_enabled;
	const field = fieldOf( store, 'backup_enabled' );

	const facts = [
		{
			label: __( 'Location', 'lw-img' ),
			value: <code>{ store.data.meta.backupPath }</code>,
		},
		{
			label: __( 'Restore', 'lw-img' ),
			value: __( 'Any time, from the Media Library', 'lw-img' ),
		},
		{
			label: __( 'Retention', 'lw-img' ),
			value: days
				? sprintf(
						/* translators: %d: number of days. */
						__( '%d days', 'lw-img' ),
						days
					)
				: __( 'Forever', 'lw-img' ),
		},
	];

	return (
		<Card
			className={ `lw-admin-section lw-img-backup${
				enabled ? '' : ' is-off'
			}` }
		>
			<div className="lw-img-backup__bar">
				<span className="lw-img-backup__icon" aria-hidden="true">
					<Icon icon={ backup } size={ 26 } />
				</span>
				<div className="lw-img-backup__text">
					<strong>
						{ enabled
							? __( 'Backing up originals is on', 'lw-img' )
							: __( 'Backing up originals is off', 'lw-img' ) }
					</strong>
					<span>
						{ enabled
							? __( 'Every conversion is reversible.', 'lw-img' )
							: __(
									'New conversions cannot be undone.',
									'lw-img'
								) }
					</span>
					{ field.locked && (
						<span>
							{ sprintf(
								/* translators: %s: PHP constant name. */
								__( 'Set in wp-config.php (%s)', 'lw-img' ),
								field.locked
							) }
						</span>
					) }
				</div>
				<ToggleControl
					__nextHasNoMarginBottom
					label={
						enabled ? __( 'On', 'lw-img' ) : __( 'Off', 'lw-img' )
					}
					checked={ enabled }
					disabled={ !! field.locked }
					onChange={ ( value ) =>
						store.set( 'backup_enabled', value )
					}
				/>
			</div>
			<dl className="lw-img-backup__facts">
				{ facts.map( ( fact ) => (
					<div key={ fact.label } className="lw-img-backup__fact">
						<dt>{ fact.label }</dt>
						<dd>{ fact.value }</dd>
					</div>
				) ) }
			</dl>
			{ ! enabled && (
				<div className="lw-img-backup__warn">
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'With backups off there is no way to restore an image once it has been converted — Restore original disappears from the Media Library, and re-optimizing at another level becomes impossible. Existing backups are kept and still restorable.',
							'lw-img'
						) }
					</Notice>
				</div>
			) }
		</Card>
	);
}
