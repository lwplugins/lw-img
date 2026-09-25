/**
 * Every REST call the admin makes, in one place (lw-img/v1, prefix /admin).
 * Responses go through ./shapes before the UI reads them, so a backend
 * shape change is a one-file fix there.
 */
/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { NAMESPACE } from './boot';
import {
	toAccount,
	toBackup,
	toBulk,
	toLog,
	toSettings,
	toStats,
	toTester,
} from './shapes';

const path = ( route ) => `/${ NAMESPACE }/admin${ route }`;
const get = ( route, args, signal ) =>
	apiFetch( { path: addQueryArgs( path( route ), args ), signal } );
const send = ( route, method, data ) =>
	apiFetch( { path: path( route ), method, data } );
// `refresh=1` bypasses the server-side cache; omitted otherwise.
const fresh = ( refresh ) => ( refresh ? { refresh: 1 } : undefined );

export const api = {
	// Settings: GET → { options, meta }; POST any subset (atomic) → same shape.
	settings: () => get( '/settings' ).then( toSettings ),
	saveSettings: ( patch ) =>
		send( '/settings', 'POST', patch ).then( toSettings ),

	// HelloImg account (10 min server cache; refresh = "Test connection").
	account: ( refresh = false ) =>
		get( '/account', fresh( refresh ) ).then( toAccount ),

	// Bulk: one status object from every route. assist=1 lets a stalled run
	// advance inline (poll only).
	bulk: ( assist = false ) =>
		get( '/bulk', assist ? { assist: 1 } : undefined ).then( toBulk ),
	bulkStart: () => send( '/bulk/start', 'POST' ).then( toBulk ),
	bulkCancel: () => send( '/bulk/cancel', 'POST' ).then( toBulk ),
	bulkRetryFailed: () => send( '/bulk/retry-failed', 'POST' ).then( toBulk ),
	bulkRescan: () => send( '/bulk/rescan', 'POST' ).then( toBulk ),
	bulkSpeed: ( profile ) =>
		send( '/bulk/speed', 'POST', { profile } ).then( toBulk ),

	// Stats (1 h server cache) and the leftover scan (runs only on request;
	// answers with the whole stats object).
	stats: ( refresh = false ) =>
		get( '/stats', fresh( refresh ) ).then( toStats ),
	rescanLeftovers: () => send( '/stats/leftovers', 'POST' ).then( toStats ),

	// Backup folder figures + cleanup schedule.
	backup: () => get( '/backup' ).then( toBackup ),

	// Environment report (10 min server cache).
	tester: ( refresh = false ) =>
		get( '/tester', fresh( refresh ) ).then( toTester ),

	// Event log, server-paged (per_page ≤ 100); type = converted|skipped|failed|restored.
	log: ( { page, perPage, type, search }, signal ) =>
		get(
			'/log',
			{
				page,
				per_page: Math.min( 100, perPage ),
				type: type || undefined,
				search: search || undefined,
			},
			signal
		).then( toLog ),
	// → { cleared, message }.
	clearLog: () => send( '/log', 'DELETE' ),
};

/**
 * Human message of a failed request.
 *
 * @param {Object} error apiFetch rejection.
 * @return {string} Message.
 */
export const errorMessage = ( error ) =>
	error?.message ||
	__( 'That did not work. Please reload the page and try again.', 'lw-img' );

/**
 * Per-field validation errors of a `400 lw_img_invalid` response. Rule rows
 * come as `rules.3.pattern|action|value`, with a summary under
 * `pattern_rules`; the bulk speed route reports `profile`.
 *
 * @param {Object} error apiFetch rejection.
 * @return {Object|null} { key: [ messages ] } or null.
 */
export function fieldErrors( error ) {
	const fields = error?.data?.fields;
	if ( ! fields || typeof fields !== 'object' ) {
		return null;
	}
	return Object.fromEntries(
		Object.entries( fields ).map( ( [ key, messages ] ) => [
			key,
			( Array.isArray( messages ) ? messages : [ messages ] ).map(
				String
			),
		] )
	);
}
