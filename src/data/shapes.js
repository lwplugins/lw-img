/**
 * Response adapters: the ONLY place that knows the backend's field names
 * (lw-img/v1 admin routes, 2.0.0 — includes/Rest/Admin/*). The UI reads the
 * camelCase objects built here.
 */
/**
 * Internal dependencies
 */
import { DASHBOARD_URL, DOCS_URL } from './boot';

const obj = ( value ) =>
	value && typeof value === 'object' && ! Array.isArray( value ) ? value : {};
const list = ( value ) => ( Array.isArray( value ) ? value : [] );
const num = ( value ) => Number( value ) || 0;
const numOrNull = ( value ) =>
	value === null || value === undefined ? null : Number( value ) || 0;
const str = ( value ) =>
	value === null || value === undefined ? '' : String( value );
const oneOf = ( value, allowed, fallback ) =>
	allowed.includes( value ) ? value : fallback;
const strings = ( value ) => list( value ).map( String );

export const STATUSES = [ 'ok', 'warning', 'critical', 'info' ];
export const SPEEDS = [ 'gentle', 'normal', 'fast' ];
export const RUN_STATES = [ 'idle', 'running', 'done', 'cancelled', 'halted' ];
export const LOG_TYPES = [ 'converted', 'skipped', 'failed', 'restored' ];

// Settings ------------------------------------------------------------------

/**
 * GET/POST /admin/settings → { options, meta }.
 *
 * The API key never leaves the server: `options.api_key` is
 * `{ set, source: constant|option|none, hint }`. It moves to meta.apiKey and
 * the draft keeps `options.api_key = null` (= unchanged); a typed key is a
 * string, a removal is ''.
 *
 * @param {Object} data Response.
 * @return {Object} { options, meta }.
 */
export function toSettings( data ) {
	const meta = obj( data?.meta );
	const key = obj( data?.options?.api_key );
	const caps = obj( meta.capabilities );
	const lockedConstants = Object.fromEntries(
		Object.entries( obj( meta.locked ) ).map( ( [ k, v ] ) => [
			k,
			String( v ),
		] )
	);
	const options = { ...obj( data?.options ), api_key: null };
	options.pattern_rules = list( options.pattern_rules ).map( ( rule ) => ( {
		pattern: str( rule?.pattern ),
		action: str( rule?.action ),
		value: str( rule?.value ),
	} ) );
	options.smartcrop_sizes = strings( options.smartcrop_sizes );

	return {
		options,
		meta: {
			apiKey: {
				set: !! key.set,
				source: oneOf(
					key.source,
					[ 'constant', 'option', 'none' ],
					'none'
				),
				hint: str( key.hint ),
			},
			locked: Object.keys( lockedConstants ),
			lockedConstants,
			ranges: obj( meta.ranges ),
			enums: Object.fromEntries(
				Object.entries( obj( meta.enums ) ).map( ( [ k, v ] ) => [
					k,
					strings( v ),
				] )
			),
			ruleActions: strings( meta.rule_actions ),
			maxRules: num( meta.max_rules ) || 100,
			// Hard-crop sizes only (the server filters them).
			imageSizes: list( meta.image_sizes ).map( ( size ) => ( {
				name: str( size?.name ),
				width: num( size?.width ),
				height: num( size?.height ),
			} ) ),
			retentionPresets: list( meta.retention_presets ).map( num ),
			backupPath: str( meta.backup_path ) || 'uploads/lw-img-backups/',
			dashboardUrl: str( meta.dashboard_url ) || DASHBOARD_URL,
			dashboardHost: str( meta.dashboard_host ),
			docsUrl: str( meta.docs_url ) || DOCS_URL,
			siteHost: str( meta.site_host ),
			capabilities: {
				webp: !! caps.webp_thumbnails,
				avif: !! caps.avif_thumbnails,
			},
		},
	};
}

// Account -------------------------------------------------------------------

/**
 * GET /admin/account → HelloImg account digest (cached server-side).
 *
 * @param {Object} data Response.
 * @return {Object} Account.
 */
export function toAccount( data ) {
	const d = obj( data );
	const plan = d.plan ? obj( d.plan ) : null;
	const usage = d.usage ? obj( d.usage ) : null;
	const website = d.website ? obj( d.website ) : null;
	const error = d.error ? obj( d.error ) : null;
	return {
		configured: !! d.configured,
		connected: !! d.connected,
		plan: plan
			? { slug: str( plan.slug ), label: str( plan.label ) }
			: null,
		month: str( d.month ),
		usage: usage
			? {
					limited: !! usage.limited,
					monthlyLimit: numOrNull( usage.monthly_limit ),
					used: numOrNull( usage.used ),
					percent: numOrNull( usage.percent ),
				}
			: null,
		website: website
			? {
					domain: str( website.domain ),
					images: num( website.images ),
					bytesSaved: num( website.bytes_saved ),
				}
			: null,
		siteHost: str( d.site_host ),
		siteMismatch: !! d.site_mismatch,
		site: {
			optimized: num( d.site?.optimized ),
			saved: num( d.site?.saved ),
		},
		error: error ? str( error.message ) : '',
		checkedAt: num( d.checked_at ),
		cached: !! d.cached,
	};
}

// Bulk ----------------------------------------------------------------------

/**
 * GET /admin/bulk and every POST /admin/bulk/* → one status object.
 * `run` = the CURRENT run's counters (null before the first run),
 * `library` = all-time table totals.
 *
 * @param {Object} data Response.
 * @return {Object} Bulk status.
 */
export function toBulk( data ) {
	const d = obj( data );
	const run = d.run ? obj( d.run ) : null;
	const lib = obj( d.library );
	const halt = d.halt ? obj( d.halt ) : null;
	const gate = obj( d.gate );
	const lock = obj( d.lock );
	const notice = d.notice ? obj( d.notice ) : null;

	return {
		state: oneOf( d.state, RUN_STATES, 'idle' ),
		halt: halt
			? {
					reason: str( halt.reason ),
					message: str( halt.message ),
					detail: str( halt.detail ),
				}
			: null,
		run: {
			exists: !! run,
			total: num( run?.total ),
			processed: num( run?.processed ),
			optimized: num( run?.optimized ),
			skipped: num( run?.skipped ),
			failed: num( run?.failed ),
			pending: num( run?.pending ),
			percent: num( run?.percent ),
			bytesIn: num( run?.bytes_in ),
			bytesSaved: num( run?.bytes_saved ),
			savedPercent: num( run?.saved_percent ),
			startedAt: num( run?.started_at ),
			finishedAt: num( run?.finished_at ),
			elapsed: num( run?.elapsed ),
			eta: numOrNull( run?.eta ),
			current: str( run?.current ),
		},
		library: {
			pending: num( lib.pending ),
			optimized: num( lib.optimized ),
			skipped: num( lib.skipped ),
			failed: num( lib.failed ),
		},
		skipReasons: list( lib.skip_reasons ).map( ( r ) => ( {
			reason: str( r?.reason ),
			count: num( r?.count ),
		} ) ),
		speed: oneOf( d.speed?.profile, SPEEDS, 'normal' ),
		recent: list( d.recent ).map( ( item, index ) => ( {
			key: `${ num( item?.ts ) }-${ index }`,
			ts: num( item?.ts ),
			label: str( item?.label ),
			result: str( item?.result ),
			detail: str( item?.detail ),
		} ) ),
		stalled: !! lock.stalled,
		locked: !! lock.active,
		canStart: !! gate.can_start,
		gate: list( gate.reasons ).map( ( g ) => ( {
			code: str( g?.code ),
			message: str( g?.message ),
		} ) ),
		notice: notice
			? {
					tone: notice.type === 'success' ? 'ok' : 'info',
					message: str( notice.message ),
				}
			: null,
		queued: numOrNull( d.queued ),
	};
}

// Stats ---------------------------------------------------------------------

/**
 * GET /admin/stats and POST /admin/stats/leftovers (same shape).
 *
 * @param {Object} data Response.
 * @return {Object} Stats.
 */
export function toStats( data ) {
	const d = obj( data );
	const l = obj( d.leftovers );
	return {
		count: num( d.count ),
		original: num( d.original ),
		optimized: num( d.optimized ),
		saved: num( d.saved ),
		percent: num( d.percent ),
		average: num( d.average ),
		libraryTotal: num( d.library_total ),
		libraryPercent: num( d.library_percent ),
		wins: list( d.wins ).map( ( w, index ) => ( {
			key: `${ str( w?.file ) }-${ index }`,
			file: str( w?.file ),
			original: num( w?.original ),
			new: num( w?.new ),
			percent: num( w?.percent ),
		} ) ),
		backup: {
			bytes: num( d.backup?.bytes ),
			files: num( d.backup?.files ),
		},
		retentionDays: num( d.retention_days ),
		generatedAt: num( d.generated_at ),
		leftovers: {
			sources: list( l.sources ).map( ( s ) => ( {
				name: str( s?.name ),
				path: str( s?.path ),
				type: str( s?.type ),
				bytes: num( s?.bytes ),
				files: num( s?.files ),
				partial: !! s?.partial,
			} ) ),
			total: num( l.total_bytes ),
			partial: !! l.partial,
			scannedAt: num( l.scanned_at ),
			knownSources: strings( l.known_sources ),
		},
	};
}

// Backup --------------------------------------------------------------------

/**
 * GET /admin/backup → folder figures and the cleanup schedule.
 *
 * @param {Object} data Response.
 * @return {Object} Backup.
 */
export function toBackup( data ) {
	const d = obj( data );
	const cleanup = obj( d.cleanup );
	return {
		bytes: num( d.bytes ),
		files: num( d.files ),
		path: str( d.path ),
		measuredAt: num( d.measured_at ),
		nextCleanup: num( cleanup.next_run ),
		wpCronDisabled: !! cleanup.wp_cron_disabled,
	};
}

// Tester --------------------------------------------------------------------

const toCheck = ( c, index ) => ( {
	id: `${ str( c?.section ) }-${ str( c?.label ) }-${ index }`,
	label: str( c?.label ),
	status: oneOf( c?.status, STATUSES, 'info' ),
	message: str( c?.message ),
	fix: str( c?.fix ),
} );

/**
 * GET /admin/tester → verdict, needs-attention items, sections.
 *
 * @param {Object} data Response.
 * @return {Object} Report.
 */
export function toTester( data ) {
	const d = obj( data );
	const v = obj( d.verdict );
	return {
		verdict: oneOf( v.status, STATUSES, 'ok' ),
		counts: Object.fromEntries(
			STATUSES.map( ( s ) => [ s, num( v[ s ] ) ] )
		),
		attention: list( d.attention ).map( toCheck ),
		sections: list( d.sections ).map( ( s ) => ( {
			id: str( s?.id ),
			label: str( s?.label ),
			status: oneOf( s?.status, STATUSES, 'ok' ),
			critical: num( s?.critical ),
			warning: num( s?.warning ),
			checks: list( s?.rows ).map( toCheck ),
		} ) ),
		generatedAt: num( d.generated_at ),
		cacheTtl: num( d.cache_ttl ),
	};
}

// Log -----------------------------------------------------------------------

/**
 * GET /admin/log → one page of events + per-type counts (for the chips).
 *
 * @param {Object} data Response.
 * @return {Object} { items, total, totalPages, counts, enabled }.
 */
export function toLog( data ) {
	const d = obj( data );
	const counts = obj( d.counts );
	return {
		items: list( d.entries ).map( ( e ) => ( {
			id: str( e?.id ),
			status: oneOf( e?.status, LOG_TYPES, 'skipped' ),
			file: str( e?.file ),
			mime: str( e?.mime ),
			mimeTo: str( e?.mime_to ),
			ts: num( e?.ts ),
			editUrl: str( e?.edit_url ),
			sizeIn: num( e?.size_in ),
			sizeOut: num( e?.size_out ),
			percent: num( e?.percent ),
			jobId: str( e?.job_id ),
			reason: str( e?.reason ),
			error: str( e?.error ),
		} ) ),
		total: num( d.total ),
		totalPages: Math.max( 1, num( d.pages ) ),
		counts: Object.fromEntries(
			[ 'all', ...LOG_TYPES ].map( ( t ) => [ t, num( counts[ t ] ) ] )
		),
		enabled: !! d.enabled,
		maxEntries: num( d.max_entries ) || 200,
	};
}
