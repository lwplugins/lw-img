/**
 * WordPress dependencies
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import KeyValue from '../../components/KeyValue';
import Section from '../../components/Section';
import { formatNumber } from '../../data/format';
import { LEVELS, choiceOf } from '../../data/labels';

const size = ( value ) => ( Number( value ) ? formatNumber( value ) : '∞' );

/**
 * "This run uses your Upload settings": what a bulk run will do, from the
 * saved options.
 *
 * @param {Object} props
 * @param {Object} props.options Saved options.
 */
export default function RunSummary( { options } ) {
	const rules = ( options.pattern_rules || [] ).length;
	const resize =
		Number( options.max_width ) || Number( options.max_height )
			? sprintf(
					/* translators: 1: max width, 2: max height (∞ = no limit). */
					__( 'fit inside %1$s × %2$s px', 'lw-img' ),
					size( options.max_width ),
					size( options.max_height )
				)
			: __( 'original dimensions kept', 'lw-img' );

	return (
		<Section title={ __( 'This run uses your Upload settings', 'lw-img' ) }>
			<KeyValue
				rows={ [
					{
						label: __( 'Level', 'lw-img' ),
						value: choiceOf( LEVELS, options.level ).label,
					},
					{
						label: __( 'Output', 'lw-img' ),
						value: String(
							options.output_format || ''
						).toUpperCase(),
					},
					{ label: __( 'Resize', 'lw-img' ), value: resize },
					{
						label: __( 'Pattern rules', 'lw-img' ),
						value: sprintf(
							/* translators: %s: number of pattern rules. */
							_n( '%s rule', '%s rules', rules, 'lw-img' ),
							formatNumber( rules )
						),
					},
					{
						label: __( 'EXIF', 'lw-img' ),
						value: options.keep_exif
							? __( 'kept', 'lw-img' )
							: __( 'removed', 'lw-img' ),
					},
					{
						label: __( 'Backup', 'lw-img' ),
						value: options.backup_enabled
							? __( 'on', 'lw-img' )
							: __( 'off', 'lw-img' ),
					},
				] }
			/>
			<p className="lw-admin-hint">
				{ __(
					'Thumbnails are regenerated from the converted file after every conversion — no separate regeneration needed.',
					'lw-img'
				) }{ ' ' }
				<a href="#upload">
					{ __( 'Change on the Upload tab', 'lw-img' ) }
				</a>
			</p>
		</Section>
	);
}
