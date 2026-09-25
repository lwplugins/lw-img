/**
 * WordPress dependencies
 */
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { api } from './api';

const POLL_MS = 3000;
const RETRY_MS = 10000;

/**
 * Bulk run status, kept at app level so a run keeps being followed (and the
 * nav shows its progress) while another tab is open. Loaded lazily (first
 * visit of the Bulk tab); polled every 3 s with `assist=1` while running,
 * every 10 s after a failed poll, not at all once the run is over.
 *
 * @return {Object} { data, error, isLoading, elapsed, since, ensure, reload, act }.
 */
export default function useBulk() {
	const [ data, setData ] = useState( null );
	const [ error, setError ] = useState( null );
	const [ isLoading, setIsLoading ] = useState( false );
	const [ fetchedAt, setFetchedAt ] = useState( 0 );
	const [ now, setNow ] = useState( Date.now() );
	const [ retry, setRetry ] = useState( false );
	const started = useRef( false );
	const seq = useRef( 0 );

	const accept = useCallback( ( status ) => {
		setData( status );
		setError( null );
		setRetry( false );
		setFetchedAt( Date.now() );
		setNow( Date.now() );
		return status;
	}, [] );

	const load = useCallback(
		( assist = false ) => {
			started.current = true;
			const id = ++seq.current;
			setIsLoading( true );
			return api.bulk( assist ).then(
				( status ) => {
					setIsLoading( false );
					return id === seq.current ? accept( status ) : status;
				},
				( e ) => {
					setIsLoading( false );
					if ( id === seq.current ) {
						setError( e );
						setRetry( true );
					}
					return null;
				}
			);
		},
		[ accept ]
	);

	const ensure = useCallback( () => {
		if ( ! started.current ) {
			load();
		}
	}, [ load ] );

	const running = data?.state === 'running';

	// Poll loop: one request at a time, the next one scheduled after it.
	useEffect( () => {
		if ( ! running && ! ( retry && data ) ) {
			return;
		}
		const timer = setTimeout(
			() => load( true ),
			retry ? RETRY_MS : POLL_MS
		);
		return () => clearTimeout( timer );
	}, [ running, retry, fetchedAt, error, data, load ] );

	// Live elapsed clock between polls.
	useEffect( () => {
		if ( ! running ) {
			return;
		}
		const timer = setInterval( () => setNow( Date.now() ), 1000 );
		return () => clearInterval( timer );
	}, [ running ] );

	/**
	 * Runs a bulk action (start, cancel, …); its status reply replaces the
	 * current one. Rejections propagate to the caller (inline message).
	 *
	 * @param {Function} call Returns a promise of a status object.
	 * @return {Promise<Object>} The new status.
	 */
	const act = useCallback(
		( call ) => {
			seq.current++;
			return call().then( accept );
		},
		[ accept ]
	);

	// Seconds since the last status arrived (live clock between polls).
	const since =
		running && fetchedAt ? Math.max( 0, ( now - fetchedAt ) / 1000 ) : 0;
	const elapsed = data ? data.run.elapsed + since : 0;

	return {
		data,
		error,
		isLoading,
		elapsed,
		since,
		ensure,
		reload: () => load( false ),
		act,
	};
}
