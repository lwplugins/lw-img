/**
 * WordPress dependencies
 */
import { useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import FormSkeleton from './components/FormSkeleton';
import LoadError from './components/LoadError';
import Notices from './components/Notices';
import { api } from './data/api';
import useBulk from './data/useBulk';
import useRemote from './data/useRemote';
import useSettingsStore from './data/useSettingsStore';
import Footer from './shell/Footer';
import navMeta from './shell/navMeta';
import SideNav from './shell/SideNav';
import TopBar from './shell/TopBar';
import { TABS } from './shell/tabs';
import useSaveShortcut from './shell/useSaveShortcut';
import useTab from './shell/useTab';
import useUnsavedWarning from './shell/useUnsavedWarning';
import BackupTab from './tabs/backup/BackupTab';
import BulkTab from './tabs/bulk/BulkTab';
import GeneralTab from './tabs/general/GeneralTab';
import LogTab from './tabs/log/LogTab';
import StatsTab from './tabs/stats/StatsTab';
import TesterTab from './tabs/tester/TesterTab';
import UploadTab from './tabs/upload/UploadTab';

const VIEWS = {
	general: GeneralTab,
	stats: StatsTab,
	upload: UploadTab,
	bulk: BulkTab,
	backup: BackupTab,
	tester: TesterTab,
	log: LogTab,
};

// Classic links (?page=lw-img&tab=bulk) open that tab.
const INITIAL_TAB =
	new URLSearchParams( window.location.search ).get( 'tab' ) || 'general';

/**
 * Shell + one options store shared by the settings tabs (partial saves) +
 * the read models every tab may need: account (loaded now, cached on the
 * server), stats / tester (lazy) and the bulk status (lazy, polled while a
 * run is going).
 */
export default function App() {
	const store = useSettingsStore();
	const loadAccount = useCallback(
		( refresh ) => api.account( refresh ),
		[]
	);
	const loadStats = useCallback( ( refresh ) => api.stats( refresh ), [] );
	const loadTester = useCallback( ( refresh ) => api.tester( refresh ), [] );
	const account = useRemote( loadAccount );
	const stats = useRemote( loadStats, false );
	const tester = useRemote( loadTester, false );
	const bulk = useBulk();
	const tab = useTab(
		TABS.map( ( t ) => t.id ),
		INITIAL_TAB
	);
	const current = TABS.find( ( t ) => t.id === tab ) || TABS[ 0 ];
	const View = VIEWS[ current.id ];

	useUnsavedWarning( store.hasEdits );
	useSaveShortcut( store.save, store.hasEdits && ! store.isSaving );

	let content;
	if ( store.error ) {
		content = (
			<LoadError message={ store.error } onRetry={ store.reload } />
		);
	} else if ( store.isLoading ) {
		content = <FormSkeleton />;
	} else {
		content = (
			<View
				store={ store }
				account={ account }
				stats={ stats }
				tester={ tester }
				bulk={ bulk }
			/>
		);
	}

	return (
		<>
			<div className="lw-admin-shell">
				<SideNav
					tabs={ TABS }
					current={ current.id }
					meta={ navMeta( {
						errors: store.errors,
						account: account.data,
						bulk: bulk.data,
						tester: tester.data,
					} ) }
					docsUrl={ store.data?.meta.docsUrl }
				/>
				<div className="lw-admin-main">
					<TopBar
						title={ current.title }
						store={ current.save && store.data ? store : null }
					/>
					<main className="lw-admin-scroll">
						<div className="lw-admin-content">{ content }</div>
					</main>
					<Footer />
				</div>
			</div>
			<Notices />
		</>
	);
}
