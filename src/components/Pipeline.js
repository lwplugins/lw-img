/**
 * WordPress dependencies
 */
import { Icon, chevronRight } from '@wordpress/icons';
import { Fragment } from '@wordpress/element';

/**
 * Horizontal "what happens" strip: icon + label steps with chevrons.
 *
 * @param {Object}  props
 * @param {Array}   props.steps  { icon, label, muted? }.
 * @param {boolean} props.active Greyed out when false.
 */
export default function Pipeline( { steps, active = true } ) {
	return (
		<ol className={ `lw-img-pipeline ${ active ? '' : 'is-inactive' }` }>
			{ steps.map( ( step, index ) => (
				<Fragment key={ step.label }>
					{ index > 0 && (
						<li className="lw-img-pipeline__sep" aria-hidden="true">
							<Icon icon={ chevronRight } size={ 20 } />
						</li>
					) }
					<li
						className={ `lw-img-pipeline__step ${ step.muted ? 'is-muted' : '' }` }
					>
						<Icon icon={ step.icon } size={ 24 } />
						<span>{ step.label }</span>
					</li>
				</Fragment>
			) ) }
		</ol>
	);
}
