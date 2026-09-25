/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Why Start is disabled: the server's gate reasons (translated messages,
 * codes running|no_key|redirects|nothing_pending), each with a link to the
 * fix where there is one.
 *
 * @param {Object} props
 * @param {Array}  props.gate [ { code, message } ].
 */
export default function GateReasons( { gate } ) {
	if ( ! gate.length ) {
		return null;
	}

	return (
		<ul className="lw-img-gate" id="lw-img-gate">
			{ gate.map( ( { code, message } ) => (
				<li key={ code + message }>
					{ message }
					{ code === 'no_key' && (
						<>
							{ ' — ' }
							<a href="#general">
								{ __( 'General tab', 'lw-img' ) }
							</a>
						</>
					) }
					{ code === 'redirects' && (
						<>
							{ ' — ' }
							<a href="#tester">
								{ __(
									'see the fix on the Tester tab',
									'lw-img'
								) }
							</a>
						</>
					) }
				</li>
			) ) }
		</ul>
	);
}
