/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	backup,
	chartBar,
	cloudUpload,
	cog,
	gallery,
	listView,
	tool,
} from '@wordpress/icons';

/**
 * Tab registry: hash slugs and order of the classic screen (?tab= honoured).
 * `save` = the tab edits lw_img_options (top bar Save shown). `fields` lists
 * the option keys on the tab, so a failed save can flag the tabs that hold
 * an invalid field (rule rows report as `rules.N.field`).
 */
export const TABS = [
	{
		id: 'general',
		label: __( 'General', 'lw-img' ),
		title: __( 'General Settings', 'lw-img' ),
		icon: cog,
		save: true,
		fields: [ 'api_key', 'request_timeout' ],
	},
	{
		id: 'stats',
		label: __( 'Stats', 'lw-img' ),
		title: __( 'Statistics', 'lw-img' ),
		icon: chartBar,
		save: false,
	},
	{
		id: 'upload',
		label: __( 'Upload', 'lw-img' ),
		title: __( 'Upload Settings', 'lw-img' ),
		icon: cloudUpload,
		save: true,
		fields: [
			'auto_convert',
			'output_format',
			'level',
			'keep_exif',
			'max_width',
			'max_height',
			'min_filesize_kb',
			'max_filesize_mb',
			'skip_already_webp',
			'skip_animated_gif',
			'pattern_rules',
			'redirect_missing_images',
			'smartcrop_enabled',
			'smartcrop_sizes',
		],
		prefixes: [ 'rules.', 'pattern_rules.' ],
	},
	{
		id: 'bulk',
		label: __( 'Bulk', 'lw-img' ),
		title: __( 'Bulk Optimization', 'lw-img' ),
		icon: gallery,
		save: false,
	},
	{
		id: 'backup',
		label: __( 'Backup', 'lw-img' ),
		title: __( 'Backup Settings', 'lw-img' ),
		icon: backup,
		save: true,
		fields: [
			'backup_enabled',
			'backup_retention_days',
			'delete_on_uninstall',
		],
	},
	{
		id: 'tester',
		label: __( 'Tester', 'lw-img' ),
		title: __( 'Environment Tester', 'lw-img' ),
		icon: tool,
		save: false,
	},
	{
		id: 'log',
		label: __( 'Log', 'lw-img' ),
		title: __( 'Event Log', 'lw-img' ),
		icon: listView,
		save: true,
		fields: [ 'enable_log' ],
	},
];

/**
 * Tab id that holds an option key (for error flags in the nav).
 *
 * @param {string} field Option key or error key.
 * @return {string|undefined} Tab id.
 */
export const tabOfField = ( field ) =>
	TABS.find(
		( tab ) =>
			tab.fields?.includes( field ) ||
			tab.prefixes?.some( ( prefix ) => field.startsWith( prefix ) )
	)?.id;

/**
 * Translated label of a tab id (chips and links name their target tab).
 *
 * @param {string} id Tab id.
 * @return {string} Label.
 */
export const tabLabel = ( id ) =>
	TABS.find( ( tab ) => tab.id === id )?.label || id;
