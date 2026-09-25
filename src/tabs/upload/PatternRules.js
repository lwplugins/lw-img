/**
 * WordPress dependencies
 */
import { Button, SelectControl, TextControl } from '@wordpress/components';
import { useRef } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { closeSmall, plus } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Callout from '../../components/Callout';
import SettingRow from '../../components/SettingRow';
import { LEVELS, RULE_ACTIONS, allowed } from '../../data/labels';

const EXAMPLES = [ '*-logo.png', '2026/08/*', 'clients/*/raw-*' ];

/**
 * Pattern rules repeater: wildcard pattern + action (+ level for the level
 * action). Validated on the server; row errors come as rules.N.field.
 *
 * @param {Object} props
 * @param {Object} props.store Settings store.
 */
export default function PatternRules( { store } ) {
	const rules = store.data.options.pattern_rules || [];
	const { ruleActions, enums, maxRules } = store.data.meta;
	const actions = allowed( RULE_ACTIONS, ruleActions );
	const levelChoices = allowed( LEVELS, enums.level );
	const full = rules.length >= maxRules;
	const list = useRef( null );
	const rowErrors = ( index, field ) =>
		store.errors[ `rules.${ index }.${ field }` ] || [];
	// Summary message of a rejected list (row messages sit on the rows).
	const listErrors = store.errors.pattern_rules || [];

	const update = ( next ) => store.set( 'pattern_rules', next );
	const change = ( index, patch ) =>
		update(
			rules.map( ( rule, i ) =>
				i === index ? { ...rule, ...patch } : rule
			)
		);
	const focusPattern = ( index ) =>
		window.requestAnimationFrame( () =>
			list.current
				?.querySelectorAll( '.lw-img-rule input' )
				[ index ]?.focus()
		);
	const add = ( pattern = '' ) => {
		const empty = rules.findIndex( ( rule ) => rule.pattern === '' );
		if ( pattern && empty !== -1 ) {
			change( empty, { pattern } );
			focusPattern( empty );
			return;
		}
		if ( full ) {
			return;
		}
		update( [ ...rules, { pattern, action: 'exclude', value: '' } ] );
		focusPattern( rules.length );
	};

	return (
		<SettingRow
			title={ __( 'Pattern rules', 'lw-img' ) }
			stacked
			errors={ listErrors }
		>
			<div className="lw-img-rules" ref={ list }>
				{ rules.length > 0 && (
					<div className="lw-img-rule is-head" aria-hidden="true">
						<span>{ __( 'Pattern', 'lw-img' ) }</span>
						<span>{ __( 'Action', 'lw-img' ) }</span>
					</div>
				) }
				{ rules.map( ( rule, index ) => {
					const errors = [
						...rowErrors( index, 'pattern' ),
						...rowErrors( index, 'action' ),
						...rowErrors( index, 'value' ),
					];
					return (
						<div
							// Rules have no id; the index is their identity.
							key={ index }
							className={ `lw-img-rule ${
								errors.length ? 'has-error' : ''
							}` }
						>
							<TextControl
								__next40pxDefaultSize
								__nextHasNoMarginBottom
								className="lw-admin-mono"
								label={ __( 'Pattern', 'lw-img' ) }
								hideLabelFromVision
								placeholder="*-full.jpg"
								value={ rule.pattern }
								onChange={ ( pattern ) =>
									change( index, { pattern } )
								}
							/>
							<span className="lw-img-rule__action">
								<SelectControl
									__next40pxDefaultSize
									__nextHasNoMarginBottom
									label={ __( 'Action', 'lw-img' ) }
									hideLabelFromVision
									value={ rule.action }
									options={ actions }
									onChange={ ( action ) =>
										change( index, {
											action,
											value:
												action === 'level'
													? rule.value || 'normal'
													: '',
										} )
									}
								/>
								{ rule.action === 'level' && (
									<SelectControl
										__next40pxDefaultSize
										__nextHasNoMarginBottom
										label={ __( 'Level', 'lw-img' ) }
										hideLabelFromVision
										value={ rule.value || 'normal' }
										options={ levelChoices }
										onChange={ ( value ) =>
											change( index, { value } )
										}
									/>
								) }
							</span>
							<Button
								__next40pxDefaultSize
								icon={ closeSmall }
								label={ __( 'Remove rule', 'lw-img' ) }
								onClick={ () =>
									update(
										rules.filter( ( _, i ) => i !== index )
									)
								}
							/>
							{ errors.length > 0 && (
								<ul className="lw-admin-fielderror">
									{ errors.map( ( message ) => (
										<li key={ message }>{ message }</li>
									) ) }
								</ul>
							) }
						</div>
					);
				} ) }
			</div>
			<div className="lw-admin-inline">
				<Button
					variant="secondary"
					size="compact"
					icon={ plus }
					disabled={ full }
					accessibleWhenDisabled
					onClick={ () => add() }
				>
					{ __( 'Add rule', 'lw-img' ) }
				</Button>
				{ full && (
					<span className="lw-admin-hint">
						{ sprintf(
							/* translators: %d: maximum number of pattern rules. */
							__( 'At most %d rules.', 'lw-img' ),
							maxRules
						) }
					</span>
				) }
				<span className="lw-admin-hint">
					{ __( 'Examples:', 'lw-img' ) }
				</span>
				{ EXAMPLES.map( ( pattern ) => (
					<Button
						key={ pattern }
						size="small"
						variant="tertiary"
						className="lw-img-example"
						onClick={ () => add( pattern ) }
					>
						<code>{ pattern }</code>
					</Button>
				) ) }
			</div>
			<Callout>
				{ __(
					'* matches anything. A pattern without / matches the file name; with / it matches anywhere in the path. Every matching rule applies to uploads and bulk runs alike ("Skip entirely" also keeps the file out of smart crop). "Skip entirely" wins over the others; if two level rules match, the first one listed wins.',
					'lw-img'
				) }
			</Callout>
		</SettingRow>
	);
}
