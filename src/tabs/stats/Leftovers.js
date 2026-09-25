/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { createInterpolateElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, check, update } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import Section from '../../components/Section';
import { api, errorMessage } from '../../data/api';
import { ago, formatBytes, formatNumber, percent } from '../../data/format';

/**
 * Space taken by originals other optimizers left behind. Measured only (LW
 * Image never deletes them); the scan runs only when asked ("Scan again").
 *
 * @param {Object} props
 * @param {Object} props.stats useRemote( stats ) with data loaded.
 */
export default function Leftovers( { stats } ) {
	const [ scanning, setScanning ] = useState( false );
	const { createErrorNotice } = useDispatch( noticesStore );
	const data = stats.data.leftovers;
	const found = data.sources.filter( ( s ) => s.bytes > 0 );

	const rescan = async () => {
		setScanning( true );
		try {
			stats.setData( await api.rescanLeftovers() );
		} catch ( e ) {
			createErrorNotice( errorMessage( e ), { type: 'snackbar' } );
		}
		setScanning( false );
	};

	const foot = (
		<div className="lw-admin-inline">
			<span className="lw-admin-hint">
				{ data.scannedAt
					? sprintf(
							/* translators: %s: relative time, e.g. "3 days ago". */
							__(
								'Scanned %s — never runs on its own.',
								'lw-img'
							),
							ago( data.scannedAt )
						)
					: __( 'Not scanned yet.', 'lw-img' ) }
			</span>
			<Button
				size="compact"
				variant="secondary"
				icon={ update }
				isBusy={ scanning }
				disabled={ scanning }
				accessibleWhenDisabled
				onClick={ rescan }
			>
				{ __( 'Scan again', 'lw-img' ) }
			</Button>
		</div>
	);

	return (
		<Section
			title={ __( 'Leftovers from other optimizers', 'lw-img' ) }
			actions={ foot }
		>
			{ found.length === 0 ? (
				<div className="lw-img-allclear">
					<Icon icon={ check } size={ 24 } />
					<span>
						<strong>
							{ __( 'No leftovers found', 'lw-img' ) }
						</strong>
						{ data.knownSources.length > 0 && (
							<span>
								{ sprintf(
									/* translators: %s: comma-separated list of other optimizer plugins. */
									__(
										'Checked %s — nothing of theirs is taking up space.',
										'lw-img'
									),
									data.knownSources.join( ', ' )
								) }
							</span>
						) }
					</span>
				</div>
			) : (
				<>
					<p className="lw-img-leftovers__band">
						<strong>
							{ data.partial
								? sprintf(
										/* translators: %s: size, e.g. "1.2 GB". */
										__( 'at least %s', 'lw-img' ),
										formatBytes( data.total )
									)
								: formatBytes( data.total ) }
						</strong>{ ' ' }
						{ createInterpolateElement(
							__(
								'of originals from optimizers you no longer use — <b>safe to delete</b> once you are sure you will not restore them.',
								'lw-img'
							),
							{ b: <strong /> }
						) }
					</p>
					<ul className="lw-img-leftovers">
						{ found.map( ( s ) => (
							<li key={ s.name + s.path }>
								<span className="lw-img-leftovers__name">
									<strong>{ s.name }</strong>
									<span className="lw-img-tag">
										{ s.type === 'beside'
											? __(
													'beside each image',
													'lw-img'
												)
											: __( 'backup folder', 'lw-img' ) }
									</span>
									{ s.path && <code>{ s.path }</code> }
								</span>
								<span className="lw-img-leftovers__size">
									<strong>{ formatBytes( s.bytes ) }</strong>
									<span className="lw-admin-hint">
										{ sprintf(
											/* translators: %s: number of files. */
											__( '%s files', 'lw-img' ),
											formatNumber( s.files )
										) }
									</span>
								</span>
								<span
									className="lw-img-share"
									aria-hidden="true"
								>
									<span
										style={ {
											width: `${ Math.max(
												2,
												percent( s.bytes, data.total )
											) }%`,
										} }
									/>
								</span>
							</li>
						) ) }
					</ul>
					{ data.partial && (
						<Callout tone="warning">
							{ __(
								'The scan stopped early on this very large uploads folder, so the real total is higher than shown.',
								'lw-img'
							) }
						</Callout>
					) }
					<Callout>
						{ __(
							"LW Image never touches these files: it only measures them. To reclaim the space, delete the folder or the files with your file manager, over SFTP, or from that plugin's own settings if it is still installed.",
							'lw-img'
						) }
					</Callout>
				</>
			) }
		</Section>
	);
}
