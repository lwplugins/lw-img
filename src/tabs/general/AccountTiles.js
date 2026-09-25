/**
 * WordPress dependencies
 */
import { createInterpolateElement } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { externalAnchor } from '../../components/links';
import StatTile from '../../components/StatTile';
import { formatBytes, formatNumber } from '../../data/format';

/**
 * Plan / this month (limited: used of limit + meter; unlimited: images) /
 * optimized on this site (account.site, from the local table).
 *
 * @param {Object} props
 * @param {Object} props.account       Account digest.
 * @param {string} props.dashboardUrl  HelloImg dashboard URL.
 * @param {string} props.dashboardHost Its host name (link text).
 */
export default function AccountTiles( {
	account,
	dashboardUrl,
	dashboardHost,
} ) {
	const { usage, website, site } = account;
	const limited = !! usage?.limited;
	const link = `<a>${ dashboardHost }</a>`;
	const manage = ( text ) =>
		createInterpolateElement( text, { a: externalAnchor( dashboardUrl ) } );

	const planDetail = limited
		? sprintf(
				/* translators: 1: monthly image limit, 2: HelloImg dashboard host. */
				__( '%1$s images/month · manage your plan at %2$s', 'lw-img' ),
				formatNumber( usage.monthlyLimit ),
				link
			)
		: sprintf(
				/* translators: %s: HelloImg dashboard host. */
				__( 'no monthly limit · manage your plan at %s', 'lw-img' ),
				link
			);

	const saved = sprintf(
		/* translators: %s: bytes saved through the API. */
		__( '%s saved via the API', 'lw-img' ),
		formatBytes( website?.bytesSaved )
	);
	let where = website?.domain || '';
	if ( limited && website ) {
		where = sprintf(
			/* translators: 1: number of images, 2: domain. */
			__( '%1$s from %2$s', 'lw-img' ),
			formatNumber( website.images ),
			website.domain
		);
	}

	return (
		<div className="lw-admin-tiles">
			<StatTile
				label={ __( 'Plan', 'lw-img' ) }
				value={ account.plan?.label || '—' }
				detail={ manage( planDetail ) }
			/>
			<StatTile
				label={ __( 'This month', 'lw-img' ) }
				value={
					limited ? (
						<>
							{ formatNumber( usage.used ) }{ ' ' }
							<small>
								/ { formatNumber( usage.monthlyLimit ) }
							</small>
						</>
					) : (
						formatNumber( website?.images )
					)
				}
				meter={
					limited && usage.percent !== null
						? Math.min( 100, usage.percent )
						: undefined
				}
				detail={ where ? `${ saved } · ${ where }` : saved }
			/>
			<StatTile
				label={ __( 'Optimized on this site', 'lw-img' ) }
				value={ formatNumber( site.optimized ) }
				detail={
					<>
						{ sprintf(
							/* translators: %s: bytes saved on this site. */
							__( '%s saved', 'lw-img' ),
							formatBytes( site.saved )
						) }
						{ ' · ' }
						<a href="#stats">{ __( 'Stats', 'lw-img' ) }</a>
					</>
				}
			/>
		</div>
	);
}
