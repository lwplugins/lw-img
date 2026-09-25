/**
 * Display helpers. Every unit and word goes through __() / _n() — the classic
 * admin.js hard-coded "B/KB/MB", "/ min", "d/h/m/s" in English.
 */
/**
 * WordPress dependencies
 */
import { dateI18n, humanTimeDiff } from '@wordpress/date';
import { __, _n, sprintf } from '@wordpress/i18n';

const MS = 1000;

const locale = () => {
	try {
		return ( document.documentElement.lang || 'en' ).replace( '_', '-' );
	} catch {
		return 'en';
	}
};

let numberFormat = null;
const nf = () => {
	if ( ! numberFormat ) {
		try {
			numberFormat = new Intl.NumberFormat( [ locale(), 'en' ] );
		} catch {
			numberFormat = new Intl.NumberFormat();
		}
	}
	return numberFormat;
};

/**
 * Localised integer ("12 345").
 *
 * @param {number} value Number.
 * @return {string} Formatted.
 */
export const formatNumber = ( value ) =>
	nf().format( Math.round( Number( value ) || 0 ) );

/**
 * Localised decimal with fixed digits.
 *
 * @param {number} value  Number.
 * @param {number} digits Fraction digits.
 * @return {string} Formatted.
 */
export function formatDecimal( value, digits = 1 ) {
	try {
		return new Intl.NumberFormat( [ locale(), 'en' ], {
			minimumFractionDigits: digits,
			maximumFractionDigits: digits,
		} ).format( Number( value ) || 0 );
	} catch {
		return ( Number( value ) || 0 ).toFixed( digits );
	}
}

/**
 * 1024-based size, like the classic screen (GB/MB with 1 decimal, KB whole).
 *
 * @param {number} bytes Bytes.
 * @return {string} Size.
 */
export function formatBytes( bytes ) {
	const b = Math.max( 0, Number( bytes ) || 0 );
	if ( b >= 1024 ** 3 ) {
		return sprintf(
			/* translators: %s: size in gigabytes. */
			__( '%s GB', 'lw-img' ),
			formatDecimal( b / 1024 ** 3 )
		);
	}
	if ( b >= 1024 ** 2 ) {
		return sprintf(
			/* translators: %s: size in megabytes. */
			__( '%s MB', 'lw-img' ),
			formatDecimal( b / 1024 ** 2 )
		);
	}
	if ( b >= 1024 ) {
		return sprintf(
			/* translators: %s: size in kilobytes. */
			__( '%s KB', 'lw-img' ),
			formatNumber( b / 1024 )
		);
	}
	return sprintf(
		/* translators: %s: size in bytes. */
		__( '%s B', 'lw-img' ),
		formatNumber( b )
	);
}

/**
 * Share as a percentage (0 when the whole is 0).
 *
 * @param {number} part  Part.
 * @param {number} whole Whole.
 * @return {number} 0–100 (not clamped).
 */
export const percent = ( part, whole ) =>
	whole > 0 ? ( 100 * part ) / whole : 0;

/**
 * A duration in seconds: "2d 3h", "1h 20m", "4m 05s", "12s".
 *
 * @param {number} seconds Seconds.
 * @return {string} Duration.
 */
export function formatDuration( seconds ) {
	const s = Math.max( 0, Math.round( Number( seconds ) || 0 ) );
	const d = Math.floor( s / 86400 );
	const h = Math.floor( ( s % 86400 ) / 3600 );
	if ( d ) {
		return sprintf(
			/* translators: 1: days, 2: hours (short duration, e.g. "2d 3h"). */
			__( '%1$dd %2$dh', 'lw-img' ),
			d,
			h
		);
	}
	const m = Math.floor( ( s % 3600 ) / 60 );
	if ( h ) {
		return sprintf(
			/* translators: 1: hours, 2: minutes (short duration, e.g. "1h 20m"). */
			__( '%1$dh %2$dm', 'lw-img' ),
			h,
			m
		);
	}
	const sec = s % 60;
	if ( m ) {
		return sprintf(
			/* translators: 1: minutes, 2: seconds (short duration, e.g. "4m 5s"). */
			__( '%1$dm %2$ds', 'lw-img' ),
			m,
			sec
		);
	}
	return sprintf(
		/* translators: %d: seconds (short duration, e.g. "12s"). */
		__( '%ds', 'lw-img' ),
		sec
	);
}

/**
 * Throughput: "42 / min", or "3.5 / h" below one a minute.
 *
 * @param {number} processed Items done.
 * @param {number} elapsed   Seconds.
 * @return {string} Speed, or "—" before the first item.
 */
export function formatSpeed( processed, elapsed ) {
	if ( ! processed || ! elapsed ) {
		return '—';
	}
	const perMinute = processed / ( elapsed / 60 );
	if ( perMinute >= 1 ) {
		return sprintf(
			/* translators: %s: images per minute. */
			__( '%s / min', 'lw-img' ),
			formatNumber( perMinute )
		);
	}
	return sprintf(
		/* translators: %s: images per hour. */
		__( '%s / h', 'lw-img' ),
		formatDecimal( perMinute * 60 )
	);
}

/**
 * Remaining time ("~12m 30s"), or "—" when unknown.
 *
 * @param {number|null} seconds Seconds left.
 * @return {string} ETA.
 */
export function formatEta( seconds ) {
	if ( seconds === null || seconds === undefined ) {
		return '—';
	}
	return sprintf(
		/* translators: %s: estimated remaining time, e.g. "12m 30s". */
		__( '~%s', 'lw-img' ),
		formatDuration( Math.max( 0, seconds ) )
	);
}

/**
 * Relative past time in the site locale ("12 minutes ago"); humanTimeDiff
 * carries WordPress' own translated "ago".
 *
 * @param {number} ts Unix seconds.
 * @return {string} Label.
 */
export const ago = ( ts ) =>
	ts ? humanTimeDiff( ts * MS ) : __( 'unknown', 'lw-img' );

/**
 * "Y-m-d H:i" in the site timezone; "—" for 0.
 *
 * @param {number} ts Unix seconds.
 * @return {string} Date.
 */
export const datetime = ( ts ) =>
	ts ? dateI18n( 'Y-m-d H:i', ts * MS ) : '—';

/**
 * Local clock time "H:i" (feed rows).
 *
 * @param {number} ts Unix seconds.
 * @return {string} Time.
 */
export const clockTime = ( ts ) => ( ts ? dateI18n( 'H:i', ts * MS ) : '—' );

/**
 * "%d days" / "1 day" (retention).
 *
 * @param {number} days Days.
 * @return {string} Label.
 */
export const daysLabel = ( days ) =>
	/* translators: %d: number of days. */
	sprintf( _n( '%d day', '%d days', days, 'lw-img' ), days );
