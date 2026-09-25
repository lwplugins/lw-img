/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useCopyToClipboard } from '@wordpress/compose';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { copy } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Section from '../../components/Section';

function CopyButton( { text } ) {
	const [ copied, setCopied ] = useState( false );
	const ref = useCopyToClipboard( text, () => {
		setCopied( true );
		setTimeout( () => setCopied( false ), 1500 );
	} );

	return (
		<Button ref={ ref } size="compact" variant="secondary" icon={ copy }>
			{ copied ? __( 'Copied', 'lw-img' ) : __( 'Copy', 'lw-img' ) }
		</Button>
	);
}

/**
 * Criticals first, then warnings, each with its copyable fix command.
 *
 * @param {Object} props
 * @param {Array}  props.items Failing checks.
 */
export default function FixList( { items } ) {
	if ( ! items.length ) {
		return null;
	}

	return (
		<Section title={ __( 'Needs attention', 'lw-img' ) }>
			{ items.map( ( item ) => (
				<div
					key={ item.id }
					className={ `lw-img-fix is-${ item.status }` }
				>
					<strong>{ item.label }</strong>
					<span>{ item.message }</span>
					{ item.fix && (
						<div className="lw-img-fix__code">
							<pre>
								<code>{ item.fix }</code>
							</pre>
							<CopyButton text={ item.fix } />
						</div>
					) }
				</div>
			) ) }
		</Section>
	);
}
