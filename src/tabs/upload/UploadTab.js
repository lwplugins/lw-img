/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	backup,
	cloudUpload,
	gallery,
	update as convert,
} from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import { NumberInput, SwitchRow, fieldOf } from '../../components/Fields';
import Pipeline from '../../components/Pipeline';
import Section from '../../components/Section';
import Segmented from '../../components/Segmented';
import SettingRow from '../../components/SettingRow';
import { FORMATS, LEVELS, allowed, choiceOf } from '../../data/labels';
import PatternRules from './PatternRules';
import SmartCropSection from './SmartCropSection';

/**
 * What happens to every new upload. "Auto-convert uploads" off only stops
 * upload-time conversion (and smart crop, which needs it): conversion
 * settings, size limits, skip rules, pattern rules and redirects still
 * drive bulk runs and "Optimize now", so they stay fully enabled — the
 * classic screen greyed them out and blocked the mouse.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function UploadTab( { store } ) {
	const { options, meta } = store.data;
	const on = !! options.auto_convert;
	const formats = allowed( FORMATS, meta.enums.output_format );
	const levels = allowed( LEVELS, meta.enums.level );
	const errorsOf = ( ...keys ) =>
		keys.flatMap( ( key ) => fieldOf( store, key ).errors );

	return (
		<>
			<Section
				title={ __( 'Upload', 'lw-img' ) }
				description={ __(
					'What happens to every new image upload.',
					'lw-img'
				) }
			>
				<SwitchRow
					title={ __( 'Auto-convert uploads', 'lw-img' ) }
					store={ store }
					name="auto_convert"
					onText={ __( 'On', 'lw-img' ) }
					offText={ __(
						'Off — uploads are left untouched',
						'lw-img'
					) }
				/>
				<Pipeline
					active={ on }
					steps={ [
						{ icon: cloudUpload, label: __( 'Upload', 'lw-img' ) },
						{ icon: convert, label: __( 'Convert', 'lw-img' ) },
						{
							icon: backup,
							label: __( 'Back up original', 'lw-img' ),
							muted: ! options.backup_enabled,
						},
						{ icon: gallery, label: __( 'Thumbnails', 'lw-img' ) },
					] }
				/>
				{ ! on && (
					<Callout>
						{ __(
							'New uploads are left untouched. The settings below still apply to bulk runs and to "Optimize now" in the Media Library, and old-URL redirects keep working.',
							'lw-img'
						) }
					</Callout>
				) }
			</Section>

			<Section title={ __( 'Conversion', 'lw-img' ) }>
				<SettingRow
					title={ __( 'Output format', 'lw-img' ) }
					help={ choiceOf( FORMATS, options.output_format ).help }
					errors={ errorsOf( 'output_format' ) }
				>
					<Segmented
						label={ __( 'Output format', 'lw-img' ) }
						value={ options.output_format }
						options={ formats }
						onChange={ ( value ) =>
							store.set( 'output_format', value )
						}
					/>
				</SettingRow>
				<SettingRow
					title={ __( 'Optimization level', 'lw-img' ) }
					help={ choiceOf( LEVELS, options.level ).help }
					errors={ errorsOf( 'level' ) }
				>
					<Segmented
						label={ __( 'Optimization level', 'lw-img' ) }
						value={ options.level }
						options={ levels }
						onChange={ ( value ) => store.set( 'level', value ) }
					/>
				</SettingRow>
				<SwitchRow
					title={ __( 'Keep EXIF metadata', 'lw-img' ) }
					help={ __(
						'Camera info, GPS, copyright. Off by default — smaller files and removes potentially sensitive data.',
						'lw-img'
					) }
					store={ store }
					name="keep_exif"
				/>
			</Section>

			<Section title={ __( 'Size limits', 'lw-img' ) }>
				<SettingRow
					title={ __( 'Resize large images', 'lw-img' ) }
					help={ __(
						'Images are scaled down proportionally to fit; small images are never upscaled. 0 = no limit.',
						'lw-img'
					) }
					errors={ errorsOf( 'max_width', 'max_height' ) }
				>
					<span className="lw-admin-inline">
						<NumberInput
							label={ __( 'Maximum width in pixels', 'lw-img' ) }
							store={ store }
							name="max_width"
						/>
						<span aria-hidden="true">×</span>
						<NumberInput
							label={ __( 'Maximum height in pixels', 'lw-img' ) }
							store={ store }
							name="max_height"
							unit={ __( 'px', 'lw-img' ) }
						/>
					</span>
				</SettingRow>
				<SettingRow
					title={ __( 'File size range', 'lw-img' ) }
					help={ __(
						'Files outside the range are skipped (original kept). Tiny files rarely benefit; the API rejects images over 10 MB.',
						'lw-img'
					) }
					errors={ errorsOf( 'min_filesize_kb', 'max_filesize_mb' ) }
				>
					<span className="lw-admin-inline">
						<NumberInput
							label={ __(
								'Minimum file size in kilobytes',
								'lw-img'
							) }
							store={ store }
							name="min_filesize_kb"
							unit={ __( 'KB min', 'lw-img' ) }
						/>
						<span aria-hidden="true">–</span>
						<NumberInput
							label={ __(
								'Maximum file size in megabytes',
								'lw-img'
							) }
							store={ store }
							name="max_filesize_mb"
							unit={ __( 'MB max', 'lw-img' ) }
						/>
					</span>
				</SettingRow>
			</Section>

			<Section title={ __( 'Skip & exclude', 'lw-img' ) }>
				<SwitchRow
					title={ __( 'Skip already-WebP / AVIF uploads', 'lw-img' ) }
					help={ __(
						'Recommended — no API call, no credit usage.',
						'lw-img'
					) }
					store={ store }
					name="skip_already_webp"
				/>
				<SwitchRow
					title={ __( 'Skip animated GIFs', 'lw-img' ) }
					help={ __(
						'Off by default: animated GIFs become animated WebP with frames and timing preserved. If the WebP would be larger, the original is kept automatically.',
						'lw-img'
					) }
					store={ store }
					name="skip_animated_gif"
				/>
				<PatternRules store={ store } />
			</Section>

			<Section title={ __( 'After conversion', 'lw-img' ) }>
				<SwitchRow
					title={ __(
						'Redirect old image URLs to the converted file',
						'lw-img'
					) }
					help={ __(
						'Recommended. Nothing is stored: when a request for photo.jpg 404s, the plugin matches the path to its converted attachment and answers with a 301 to photo.webp — external links, sent newsletters and search results keep working. The Tester tab checks that the server lets these requests through.',
						'lw-img'
					) }
					store={ store }
					name="redirect_missing_images"
				/>
			</Section>

			<SmartCropSection store={ store } active={ on } />
		</>
	);
}
