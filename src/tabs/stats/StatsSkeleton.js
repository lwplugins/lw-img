/**
 * Internal dependencies
 */
import {
	SkeletonBlock,
	SkeletonRegion,
	SkeletonRows,
	SkeletonSection,
	SkeletonText,
	SkeletonTiles,
} from '../../components/skeleton';

/**
 * Stats placeholder: hero with bar, three tiles, a list card.
 */
export default function StatsSkeleton() {
	return (
		<SkeletonRegion className="lw-skel-tab">
			<SkeletonSection>
				<SkeletonText width="40%" size="xl" />
				<SkeletonBlock height={ 12 } round />
				<SkeletonText width="100%" size="sm" />
			</SkeletonSection>
			<SkeletonTiles />
			<SkeletonSection description={ false }>
				<SkeletonRows count={ 5 } />
			</SkeletonSection>
		</SkeletonRegion>
	);
}
