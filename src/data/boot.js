/**
 * Server-provided boot data (SettingsPage inline script `window.lwImg`).
 */
const boot = window.lwImg || {};

export const VERSION = boot.version || '';
export const NAMESPACE = boot.namespace || 'lw-img/v1';
export const DOCS_URL = boot.docsUrl || 'https://lwplugins.com/docs/lw-img/';
export const DASHBOARD_URL = boot.dashboardUrl || 'https://app.helloimg.io/';

// Media Library list filtered by LW Image status (StatusFilter, classic).
export const mediaLibraryUrl = ( status ) =>
	`upload.php?mode=list&lw_img_status=${ encodeURIComponent( status ) }`;
