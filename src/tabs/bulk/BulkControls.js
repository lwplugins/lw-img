/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { play } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import ConfirmButton from '../../components/ConfirmButton';
import Segmented from '../../components/Segmented';
import SettingRow from '../../components/SettingRow';
import { api } from '../../data/api';
import { formatNumber } from '../../data/format';
import { SPEEDS, choiceOf } from '../../data/labels';
import GateReasons from './GateReasons';

/**
 * Start (really disabled, with the reason), Cancel / Retry / Re-scan (each
 * asks first), and the speed profile (applied at once, no Save).
 *
 * @param {Object}   props
 * @param {Object}   props.data  Bulk status.
 * @param {Function} props.run   Runs an action ( call ) => Promise.
 * @param {Object}   props.store Settings store (bulk_speed is mirrored in).
 */
export default function BulkControls( { data, run, store } ) {
	const [ busy, setBusy ] = useState( '' );
	const running = data.state === 'running';
	const { failed, skipped } = data.library;

	const action = ( id, call ) => async () => {
		setBusy( id );
		await run( call );
		setBusy( '' );
	};

	const setSpeed = async ( profile ) => {
		setBusy( 'speed' );
		const status = await run( () => api.bulkSpeed( profile ) );
		if ( status ) {
			store.absorb( 'bulk_speed', status.speed );
		}
		setBusy( '' );
	};

	return (
		<div className="lw-img-controls">
			<div className="lw-admin-inline">
				{ running ? (
					<ConfirmButton
						__next40pxDefaultSize
						variant="secondary"
						isDestructive
						isBusy={ busy === 'cancel' }
						disabled={ !! busy }
						accessibleWhenDisabled
						question={ __(
							'Cancel the run? Images converted so far stay converted; the rest waits for the next run.',
							'lw-img'
						) }
						confirmText={ __( 'Cancel run', 'lw-img' ) }
						onConfirm={ action( 'cancel', api.bulkCancel ) }
					>
						{ __( 'Cancel run', 'lw-img' ) }
					</ConfirmButton>
				) : (
					<Button
						__next40pxDefaultSize
						variant="primary"
						icon={ play }
						isBusy={ busy === 'start' }
						disabled={ ! data.canStart || !! busy }
						accessibleWhenDisabled
						aria-describedby={
							data.canStart ? undefined : 'lw-img-gate'
						}
						onClick={ action( 'start', api.bulkStart ) }
					>
						{ __( 'Optimize all in background', 'lw-img' ) }
					</Button>
				) }
				{ failed > 0 && (
					<ConfirmButton
						__next40pxDefaultSize
						variant="secondary"
						isBusy={ busy === 'retry' }
						disabled={ !! busy }
						accessibleWhenDisabled
						question={ sprintf(
							/* translators: %s: number of failed images. */
							__(
								'Put all %s failed images back in the queue? They are processed by the next run.',
								'lw-img'
							),
							formatNumber( failed )
						) }
						confirmText={ __( 'Retry all', 'lw-img' ) }
						onConfirm={ action( 'retry', api.bulkRetryFailed ) }
					>
						{ sprintf(
							/* translators: %s: number of failed images. */
							__( 'Retry %s failed', 'lw-img' ),
							formatNumber( failed )
						) }
					</ConfirmButton>
				) }
				{ skipped > 0 && (
					<ConfirmButton
						__next40pxDefaultSize
						variant="tertiary"
						isBusy={ busy === 'rescan' }
						disabled={ !! busy }
						accessibleWhenDisabled
						question={ sprintf(
							/* translators: %s: number of skipped images. */
							__(
								'Put all %s skipped images back in the queue? The next run checks them again against the current settings.',
								'lw-img'
							),
							formatNumber( skipped )
						) }
						confirmText={ __( 'Re-scan all', 'lw-img' ) }
						onConfirm={ action( 'rescan', api.bulkRescan ) }
					>
						{ sprintf(
							/* translators: %s: number of skipped images. */
							__( 'Re-scan %s skipped', 'lw-img' ),
							formatNumber( skipped )
						) }
					</ConfirmButton>
				) }
			</div>
			{ busy === 'start' && (
				<p className="lw-admin-hint" role="status">
					{ __(
						'Checking API key and redirects, counting images…',
						'lw-img'
					) }
				</p>
			) }
			{ ! running && ! data.canStart && (
				<GateReasons gate={ data.gate } />
			) }
			<SettingRow
				title={ __( 'Processing speed', 'lw-img' ) }
				help={ choiceOf( SPEEDS, data.speed ).help }
			>
				<Segmented
					label={ __( 'Processing speed', 'lw-img' ) }
					value={ data.speed }
					options={ SPEEDS }
					disabled={ busy === 'speed' }
					onChange={ setSpeed }
				/>
			</SettingRow>
		</div>
	);
}
