/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Translated UI strings for `@lwplugins/data-table` (it has no text domain).
 *
 * @return {Object} Labels.
 */
export function tableLabels() {
	return {
		search: __( 'Search', 'lw-img' ),
		filter: __( 'Filter', 'lw-img' ),
		clear: __( 'Clear', 'lw-img' ),
		clearAll: __( 'Clear all filters', 'lw-img' ),
		all: __( 'All', 'lw-img' ),
		empty: __( 'No entries match these filters.', 'lw-img' ),
		emptyAll: __( 'Nothing here yet.', 'lw-img' ),
		loading: __( 'Loading…', 'lw-img' ),
		previous: __( 'Previous page', 'lw-img' ),
		next: __( 'Next page', 'lw-img' ),
		perPage: __( 'Rows per page', 'lw-img' ),
		selectAll: __( 'Select all rows on this page', 'lw-img' ),
		clearSelection: __( 'Clear selection', 'lw-img' ),
		bulkActions: __( 'Bulk actions', 'lw-img' ),
		entries: ( n ) =>
			sprintf(
				/* translators: %d: number of rows. */ _n(
					'%d entry',
					'%d entries',
					n,
					'lw-img'
				),
				n
			),
		results: ( n ) =>
			sprintf(
				/* translators: %d: number of results. */ _n(
					'%d result',
					'%d results',
					n,
					'lw-img'
				),
				n
			),
		page: ( p, t ) =>
			sprintf(
				/* translators: 1: current page, 2: total pages. */ __(
					'Page %1$d of %2$d',
					'lw-img'
				),
				p,
				t
			),
		selectRow: ( label ) =>
			sprintf(
				/* translators: %s: row name. */ __( 'Select: %s', 'lw-img' ),
				label
			),
		selected: ( n, onPage ) =>
			n === onPage
				? sprintf(
						/* translators: %d: number of selected rows. */
						_n( '%d selected', '%d selected', n, 'lw-img' ),
						n
					)
				: sprintf(
						/* translators: 1: selected rows, 2: of those, on this page. */
						__( '%1$d selected, %2$d on this page', 'lw-img' ),
						n,
						onPage
					),
		eligible: ( e, n ) =>
			sprintf(
				/* translators: 1: rows the action applies to, 2: selected rows on this page. */ __(
					'applies to %1$d of %2$d',
					'lw-img'
				),
				e,
				n
			),
	};
}
