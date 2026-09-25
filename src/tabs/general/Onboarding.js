/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import Section from '../../components/Section';

/**
 * First steps, shown while no API key is set (no wizard: nothing to store).
 */
export default function Onboarding() {
	const steps = [
		[
			__( 'Add your API key', 'lw-img' ),
			__(
				'Grab your key from the HelloImg dashboard and paste it here — Test connection verifies it instantly.',
				'lw-img'
			),
		],
		[
			__( 'Upload as usual', 'lw-img' ),
			__(
				'New JPEG / PNG / HEIC / TIFF / BMP / GIF uploads become WebP automatically — originals are backed up first, and nothing ever fails because of LW Img.',
				'lw-img'
			),
		],
		[
			__( 'Optimize the existing library', 'lw-img' ),
			__(
				'The Bulk tab converts everything already in the Media Library, in the background. The Tester tab checks your server first.',
				'lw-img'
			),
		],
	];

	return (
		<Section title={ __( 'Getting started', 'lw-img' ) }>
			<ol className="lw-img-steps">
				{ steps.map( ( [ title, text ], index ) => (
					<li key={ title }>
						<span className="lw-img-steps__num" aria-hidden="true">
							{ index + 1 }
						</span>
						<span>
							<strong>{ title }</strong>
							<span>{ text }</span>
						</span>
					</li>
				) ) }
			</ol>
			<Callout>
				{ __(
					'Everything is reversible: originals live in uploads/lw-img-backups/ and can be restored per image from the Media Library.',
					'lw-img'
				) }
			</Callout>
		</Section>
	);
}
