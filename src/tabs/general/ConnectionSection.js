/**
 * WordPress dependencies
 */
import { Button, Notice } from '@wordpress/components';
import { createInterpolateElement, useState } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import { Icon, published, error as errorIcon } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Section from '../../components/Section';
import StatusBadge from '../../components/StatusBadge';
import { externalAnchor } from '../../components/links';
import ApiKeyRow from './ApiKeyRow';

/**
 * Pill + lead line for the current connection state.
 *
 * @param {Object} apiKey  { set, source }.
 * @param {Object} account useRemote( account ).
 * @return {Array} [ status, label, lead ].
 */
function connectionState( apiKey, account ) {
	if ( ! apiKey.set ) {
		return [
			'info',
			__( 'Not connected', 'lw-img' ),
			__(
				'Paste an API key to enable conversion. Free tier: 1,000 images / month.',
				'lw-img'
			),
		];
	}
	if ( ! account.data ) {
		return account.error
			? [ 'critical', __( 'Error', 'lw-img' ), account.error.message ]
			: [ 'idle', __( 'Checking…', 'lw-img' ), '' ];
	}
	if ( account.data.connected ) {
		return [
			'ok',
			__( 'Connected', 'lw-img' ),
			// The key row itself says when the key comes from wp-config.php.
			apiKey.source === 'constant'
				? __( 'New uploads are converted automatically.', 'lw-img' )
				: __(
						'New uploads are converted automatically. The key is stored in wp_options.',
						'lw-img'
					),
		];
	}
	return [ 'critical', __( 'Error', 'lw-img' ), account.data.error ];
}

/**
 * HelloImg API hero: state pill, key field, in-place "Test connection"
 * (GET /admin/account?refresh=1 — no page reload), dashboard / Tester
 * links, and the site-mismatch warning.
 *
 * @param {Object} props
 * @param {Object} props.store   Settings store.
 * @param {Object} props.account useRemote( account ).
 */
export default function ConnectionSection( { store, account } ) {
	const [ tested, setTested ] = useState( false );
	const [ isTesting, setIsTesting ] = useState( false );
	const { apiKey, dashboardUrl, dashboardHost } = store.data.meta;
	const draft = store.data.options.api_key;
	const [ pill, pillLabel, lead ] = connectionState( apiKey, account );
	const keyDirty = draft !== null;

	const test = async () => {
		setIsTesting( true );
		// A typed key is saved first; the test always checks the saved key.
		const saved = keyDirty ? await store.save() : true;
		if ( saved ) {
			await account.reload( true );
			setTested( true );
		}
		setIsTesting( false );
	};

	const data = account.data;
	let result = null;
	if ( tested && ! isTesting && data?.connected ) {
		result = (
			<p className="lw-admin-testresult is-ok" role="status">
				<Icon icon={ published } size={ 20 } />
				{ data.plan?.label
					? sprintf(
							/* translators: %s: HelloImg plan name. */
							__(
								'Connection test passed — the API key works (plan: %s).',
								'lw-img'
							),
							data.plan.label
						)
					: __(
							'Connection test passed — the API key works.',
							'lw-img'
						) }
			</p>
		);
	} else if ( tested && ! isTesting && ( data || account.error ) ) {
		result = (
			<p className="lw-admin-testresult is-error" role="alert">
				<Icon icon={ errorIcon } size={ 20 } />
				{ sprintf(
					/* translators: %s: error message. */
					__( 'Connection test failed: %s', 'lw-img' ),
					data?.error || account.error?.message || ''
				) }
			</p>
		);
	}

	const canTest = apiKey.set || ( keyDirty && draft !== '' );
	const testButton = canTest ? (
		<Button
			__next40pxDefaultSize
			variant="secondary"
			isBusy={ isTesting }
			disabled={ isTesting || store.isSaving || draft === '' }
			accessibleWhenDisabled
			onClick={ test }
		>
			{ keyDirty && draft !== ''
				? __( 'Save and test', 'lw-img' )
				: __( 'Test connection', 'lw-img' ) }
		</Button>
	) : null;

	return (
		<Section
			title={ __( 'HelloImg API', 'lw-img' ) }
			badge={ <StatusBadge status={ pill }>{ pillLabel }</StatusBadge> }
			description={ lead }
			actions={ testButton }
		>
			{ result }
			<ApiKeyRow store={ store } onSave={ store.save } />
			<p className="lw-admin-hint">
				{ createInterpolateElement(
					sprintf(
						/* translators: %s: HelloImg dashboard host name. */
						__( 'Get your API key at %s', 'lw-img' ),
						`<a>${ dashboardHost }</a>`
					),
					{ a: externalAnchor( dashboardUrl ) }
				) }
				{ ' · ' }
				<a href="#tester">
					{ __(
						'Full environment checks on the Tester tab',
						'lw-img'
					) }
				</a>
			</p>
			{ data?.siteMismatch && (
				<Notice status="warning" isDismissible={ false }>
					{ createInterpolateElement(
						sprintf(
							/* translators: 1: domain the key belongs to, 2: this site's host, 3: HelloImg dashboard host. */
							__(
								'This API key belongs to %1$s, but this site is %2$s. The API refuses requests from other sites — create a key for this site at %3$s.',
								'lw-img'
							),
							`<b>${ data.website?.domain || '' }</b>`,
							`<b>${ data.siteHost }</b>`,
							`<a>${ dashboardHost }</a>`
						),
						{ b: <strong />, a: externalAnchor( dashboardUrl ) }
					) }
				</Notice>
			) }
		</Section>
	);
}
