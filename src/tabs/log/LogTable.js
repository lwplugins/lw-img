/**
 * External dependencies
 */
import { DataTable, DEFAULT_QUERY } from '@lwplugins/data-table';
import '@lwplugins/data-table/style.css';

/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useDispatch } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { trash, update } from '@wordpress/icons';
import { store as noticesStore } from '@wordpress/notices';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import ConfirmButton from '../../components/ConfirmButton';
import Section from '../../components/Section';
import { tableLabels } from '../../components/tableLabels';
import { api, errorMessage } from '../../data/api';
import { formatNumber } from '../../data/format';
import { LOG_TYPES } from '../../data/shapes';
import { TYPES, logColumns } from './logColumns';

const EMPTY = {
	items: [],
	total: 0,
	totalPages: 1,
	counts: { all: 0 },
	enabled: true,
};

/**
 * Event log, server-paged (GET /admin/log): type chips with counts, file
 * name search (no form around it, so Enter submits nothing), refresh, and
 * "Clear log" (asks first).
 */
export default function LogTable() {
	const [ query, setQuery ] = useState( { ...DEFAULT_QUERY, perPage: 25 } );
	const [ result, setResult ] = useState( EMPTY );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ tick, setTick ] = useState( 0 );
	const { createSuccessNotice, createErrorNotice } =
		useDispatch( noticesStore );
	const reload = () => setTick( ( t ) => t + 1 );

	useEffect( () => {
		// Abort the previous request so a slow answer never wins.
		const controller = new AbortController();
		setLoading( true );
		api.log(
			{
				page: query.page,
				perPage: query.perPage,
				type: query.filters?.type?.[ 0 ],
				search: query.search,
			},
			controller.signal
		)
			.then( ( data ) => {
				setResult( data );
				setError( null );
			} )
			.catch(
				( e ) =>
					e.name !== 'AbortError' && setError( errorMessage( e ) )
			)
			.finally(
				() => ! controller.signal.aborted && setLoading( false )
			);
		return () => controller.abort();
	}, [ query, tick ] );

	const clear = async () => {
		try {
			const r = await api.clearLog();
			createSuccessNotice( r?.message || __( 'Log cleared.', 'lw-img' ), {
				type: 'snackbar',
			} );
			setQuery( ( q ) => ( { ...q, page: 1 } ) );
			reload();
		} catch ( e ) {
			createErrorNotice( errorMessage( e ), { type: 'snackbar' } );
		}
	};

	const typeOptions = LOG_TYPES.filter(
		( t ) => result.counts[ t ] > 0 || query.filters?.type?.includes( t )
	).map( ( t ) => ( {
		value: t,
		label: sprintf(
			/* translators: 1: event type, 2: number of events. */
			__( '%1$s %2$s', 'lw-img' ),
			TYPES[ t ][ 1 ],
			formatNumber( result.counts[ t ] )
		),
	} ) );

	return (
		<Section
			title={ __( 'Events', 'lw-img' ) }
			actions={
				<div className="lw-admin-inline">
					<Button
						size="compact"
						variant="tertiary"
						icon={ update }
						label={ __( 'Refresh', 'lw-img' ) }
						isBusy={ loading }
						onClick={ reload }
					/>
					<ConfirmButton
						size="compact"
						variant="secondary"
						isDestructive
						icon={ trash }
						disabled={ ! result.counts.all && ! result.total }
						accessibleWhenDisabled
						question={ __( 'Clear all log entries?', 'lw-img' ) }
						confirmText={ __( 'Clear log', 'lw-img' ) }
						onConfirm={ clear }
					>
						{ __( 'Clear log', 'lw-img' ) }
					</ConfirmButton>
				</div>
			}
		>
			{ ! loading && ! result.enabled && (
				<Callout tone="warning">
					{ __(
						'Logging is off, so no new events are recorded. Turn on "Logging enabled" above and save.',
						'lw-img'
					) }
				</Callout>
			) }
			<DataTable
				columns={ logColumns() }
				rows={ result.items }
				total={ result.total }
				totalPages={ result.totalPages }
				query={ query }
				onQueryChange={ setQuery }
				isLoading={ loading }
				error={ error }
				errorAction={
					<Button variant="secondary" onClick={ reload }>
						{ __( 'Try again', 'lw-img' ) }
					</Button>
				}
				caption={ __( 'Event log', 'lw-img' ) }
				filters={
					typeOptions.length
						? [
								{
									field: 'type',
									label: __( 'Event', 'lw-img' ),
									options: typeOptions,
									multiple: false,
								},
							]
						: []
				}
				labels={ {
					...tableLabels(),
					search: __( 'Filter by filename…', 'lw-img' ),
					empty: __( 'No matching events', 'lw-img' ),
					emptyAll: __(
						'No events yet. Upload an image or run a bulk optimization — every conversion, skip, and failure lands here with its reason.',
						'lw-img'
					),
				} }
				getRowId={ ( r ) => r.id }
				perPageOptions={ [ 25, 50, 100 ] }
			/>
		</Section>
	);
}
