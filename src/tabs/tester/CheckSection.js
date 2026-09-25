/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';
import {
	Icon,
	cloud,
	external,
	file,
	scheduled,
	tool,
	archive,
} from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Section from '../../components/Section';
import StatusBadge from '../../components/StatusBadge';

const STATUS_LABELS = {
	ok: __( 'OK', 'lw-img' ),
	warning: __( 'Warning', 'lw-img' ),
	critical: __( 'Critical', 'lw-img' ),
	info: __( 'Info', 'lw-img' ),
};

// Icon per section id (the label comes translated from the server).
const ICONS = {
	database: archive,
	environment: tool,
	filesystem: file,
	cron: scheduled,
	redirects: external,
	api: cloud,
};

function pill( { status, critical, warning } ) {
	if ( status === 'critical' ) {
		return [
			'critical',
			sprintf(
				/* translators: %d: number of issues. */
				_n( '%d issue', '%d issues', critical, 'lw-img' ),
				critical
			),
		];
	}
	if ( status === 'warning' ) {
		return [
			'warning',
			sprintf(
				/* translators: %d: number of warnings. */
				_n( '%d warning', '%d warnings', warning, 'lw-img' ),
				warning
			),
		];
	}
	return [ 'ok', STATUS_LABELS.ok ];
}

/**
 * One report section: server status pill + rows (status, label, message).
 *
 * @param {Object} props
 * @param {Object} props.section { id, label, status, critical, warning, checks }.
 */
export default function CheckSection( { section } ) {
	const [ status, label ] = pill( section );

	return (
		<Section
			title={
				<span className="lw-admin-inline">
					<Icon icon={ ICONS[ section.id ] || tool } size={ 20 } />
					{ section.label }
				</span>
			}
			badge={ <StatusBadge status={ status }>{ label }</StatusBadge> }
		>
			<ul className="lw-img-checks">
				{ section.checks.map( ( check ) => (
					<li key={ check.id }>
						<StatusBadge status={ check.status }>
							{ STATUS_LABELS[ check.status ] }
						</StatusBadge>
						<span className="lw-img-checks__label">
							{ check.label }
						</span>
						<span className="lw-img-checks__detail">
							{ check.message }
						</span>
					</li>
				) ) }
			</ul>
		</Section>
	);
}
