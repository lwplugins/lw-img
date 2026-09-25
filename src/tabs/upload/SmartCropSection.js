/**
 * WordPress dependencies
 */
import { Button, CheckboxControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import { SwitchRow, fieldOf } from '../../components/Fields';
import Section from '../../components/Section';
import SettingRow from '../../components/SettingRow';
import { formatNumber } from '../../data/format';

/**
 * Smart crop: toggle + hard-crop sizes (meta.imageSizes, server-filtered) + the
 * live API cost. A stored size that is no longer registered stays listed
 * (flagged) so it can be unticked — never dropped silently. It only runs on
 * converted uploads, so it is dimmed (still editable) while auto-convert is
 * off.
 *
 * @param {Object}  props
 * @param {Object}  props.store  Settings store.
 * @param {boolean} props.active Auto-convert on.
 */
export default function SmartCropSection( { store, active } ) {
	const sizes = store.data.meta.imageSizes;
	const selected = store.data.options.smartcrop_sizes || [];
	const registered = sizes.map( ( s ) => s.name );
	const stale = selected.filter( ( name ) => ! registered.includes( name ) );
	const field = fieldOf( store, 'smartcrop_sizes' );
	const setSizes = ( next ) => store.set( 'smartcrop_sizes', next );
	const toggle = ( name, on ) =>
		setSizes(
			on
				? [ ...selected, name ]
				: selected.filter( ( item ) => item !== name )
		);
	const count = selected.filter( ( n ) => registered.includes( n ) ).length;

	return (
		<Section
			title={ __( 'Smart crop', 'lw-img' ) }
			className={ active ? '' : 'is-dimmed' }
		>
			{ ! active && (
				<Callout tone="warning">
					{ __(
						'Smart crop runs only on converted uploads — turn on Auto-convert uploads to use it. The command line (wp lw-img smartcrop) works either way.',
						'lw-img'
					) }
				</Callout>
			) }
			<SwitchRow
				title={ __( 'Smart-crop thumbnails', 'lw-img' ) }
				help={ __(
					'Re-crop the selected thumbnail sizes around the subject instead of the centre. Each selected size costs one extra API call per upload. Runs in the background right after the upload; on system-cron sites the cropped thumbnails appear within the cron interval.',
					'lw-img'
				) }
				store={ store }
				name="smartcrop_enabled"
			/>
			<SettingRow
				title={ __( 'Thumbnail sizes', 'lw-img' ) }
				stacked
				{ ...field }
			>
				{ sizes.length === 0 && stale.length === 0 ? (
					<p className="lw-admin-hint">
						{ __(
							'No hard-cropped thumbnail sizes are registered on this site — smart crop has nothing to work on. Sizes that scale (keep the aspect ratio) never need it.',
							'lw-img'
						) }
					</p>
				) : (
					<>
						<div
							className="lw-img-sizes"
							role="group"
							aria-label={ __( 'Thumbnail sizes', 'lw-img' ) }
						>
							{ sizes.map( ( s ) => (
								<CheckboxControl
									key={ s.name }
									__nextHasNoMarginBottom
									label={ sprintf(
										/* translators: 1: image size name, 2: width, 3: height. */
										__( '%1$s %2$s × %3$s', 'lw-img' ),
										s.name,
										s.width,
										s.height
									) }
									checked={ selected.includes( s.name ) }
									onChange={ ( on ) => toggle( s.name, on ) }
								/>
							) ) }
							{ stale.map( ( name ) => (
								<CheckboxControl
									key={ name }
									__nextHasNoMarginBottom
									label={ sprintf(
										/* translators: %s: image size name. */
										__(
											'%s (not registered on this site)',
											'lw-img'
										),
										name
									) }
									checked
									onChange={ () => toggle( name, false ) }
								/>
							) ) }
						</div>
						<div className="lw-admin-inline">
							<Button
								variant="link"
								onClick={ () =>
									setSizes( [
										...new Set( [
											...stale,
											...registered,
										] ),
									] )
								}
							>
								{ __( 'Select all', 'lw-img' ) }
							</Button>
							<Button
								variant="link"
								onClick={ () => setSizes( [] ) }
							>
								{ __( 'Select none', 'lw-img' ) }
							</Button>
							<span className="lw-admin-hint" aria-live="polite">
								{ sprintf(
									/* translators: 1: selected sizes, 2: API calls per upload (1 + selected). */
									__(
										'Cost per upload: 1 + %1$s = %2$s API calls.',
										'lw-img'
									),
									formatNumber( count ),
									formatNumber( 1 + count )
								) }
							</span>
						</div>
					</>
				) }
			</SettingRow>
		</Section>
	);
}
