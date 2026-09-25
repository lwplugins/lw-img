/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Icon, archive, gallery, code } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Section from '../../components/Section';

/**
 * Static "How to restore" guide (Compare lives on the attachment edit
 * screen, not in the Media Library row actions — the classic text said
 * otherwise).
 */
export default function RestoreGuide() {
	const items = [
		[
			gallery,
			__( 'Media Library', 'lw-img' ),
			__(
				'Hover an optimized image and click "Restore original" — the original comes back and thumbnails are regenerated. A side-by-side Compare view is on the image\'s edit screen.',
				'lw-img'
			),
		],
		[
			code,
			__( 'WP-CLI', 'lw-img' ),
			__(
				'wp lw-img restore 123 456 — restore any number of attachments from the command line.',
				'lw-img'
			),
		],
		[
			archive,
			__( 'Kept on uninstall', 'lw-img' ),
			__(
				'Backups are never deleted when the plugin is removed — your originals stay safe in the uploads folder.',
				'lw-img'
			),
		],
	];

	return (
		<Section title={ __( 'How to restore', 'lw-img' ) }>
			<ul className="lw-img-guide">
				{ items.map( ( [ icon, title, text ] ) => (
					<li key={ title }>
						<Icon icon={ icon } size={ 24 } />
						<span>
							<strong>{ title }</strong>
							<span>{ text }</span>
						</span>
					</li>
				) ) }
			</ul>
		</Section>
	);
}
