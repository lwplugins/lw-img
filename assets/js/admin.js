/**
 * LW Img — admin tab handler + bulk progress polling.
 */

(function () {
	'use strict';

	function initTabs() {
		var tabLinks  = document.querySelectorAll('.lw-img-tabs a');
		var tabPanels = document.querySelectorAll('.lw-img-tab-panel');

		if (!tabLinks.length || !tabPanels.length) {
			return;
		}

		var hash     = window.location.hash.substring(1);
		var firstTab = tabLinks[0].getAttribute('href').substring(1);
		var validTab = false;

		tabLinks.forEach(function (link) {
			if (link.getAttribute('href').substring(1) === hash) {
				validTab = true;
			}
		});

		activateTab(validTab ? hash : firstTab);

		tabLinks.forEach(function (link) {
			link.addEventListener('click', function (e) {
				e.preventDefault();
				var tabId = this.getAttribute('href').substring(1);
				activateTab(tabId);
				history.replaceState(null, '', '#' + tabId);
			});
		});

		// Preserve active tab after Settings save redirect.
		var settings = document.querySelector('.lw-img-settings');
		var form     = settings ? settings.closest('form') : null;
		if (form) {
			form.addEventListener('submit', function () {
				var activeLink = document.querySelector('.lw-img-tabs a.active');
				if (!activeLink) {
					return;
				}
				var tabSlug = activeLink.getAttribute('href').substring(1);
				var referer = form.querySelector('input[name="_wp_http_referer"]');
				if (referer && referer.value.indexOf('#') === -1) {
					referer.value += '#' + tabSlug;
				}
			});
		}

		function activateTab(tabId) {
			tabLinks.forEach(function (link) {
				var linkTabId = link.getAttribute('href').substring(1);
				if (linkTabId === tabId) {
					link.classList.add('active');
				} else {
					link.classList.remove('active');
				}
			});

			tabPanels.forEach(function (panel) {
				if (panel.id === 'tab-' + tabId) {
					panel.classList.add('active');
				} else {
					panel.classList.remove('active');
				}
			});
		}
	}

	// The run itself happens server-side via WP-Cron; this only keeps the
	// progress display fresh while the tab is open.
	function initBulk() {
		var container = document.getElementById('lw-img-bulk');

		if (!container || container.getAttribute('data-running') !== '1' || typeof window.ajaxurl === 'undefined') {
			return;
		}

		var statusEl = document.getElementById('lw-img-bulk-status');
		var barFill  = document.getElementById('lw-img-bulk-bar-fill');
		var finished = false;

		function setStatus(text) {
			if (statusEl) {
				statusEl.textContent = text;
			}
		}

		function setCount(id, value) {
			var el = document.getElementById(id);
			if (el) {
				el.textContent = value.toLocaleString();
			}
		}

		function formatDuration(seconds) {
			seconds = Math.max(0, Math.round(seconds));
			var h = Math.floor(seconds / 3600);
			var m = Math.floor((seconds % 3600) / 60);
			var s = seconds % 60;
			if (h > 0) {
				return h + 'h ' + m + 'm';
			}
			if (m > 0) {
				return m + 'm ' + s + 's';
			}
			return s + 's';
		}

		function poll() {
			var body = new FormData();
			body.append('action', 'lw_img_bulk_status');
			body.append('nonce', container.getAttribute('data-nonce'));

			fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (response) { return response.json(); })
				.then(function (payload) {
					if (!payload || !payload.success) {
						throw new Error('status request failed');
					}

					var d = payload.data;

					if (barFill && d.total > 0) {
						barFill.style.width = Math.min(100, Math.round((d.processed / d.total) * 100)) + '%';
					}

					setCount('lw-img-count-pending', Math.max(0, d.total - d.processed));
					setCount('lw-img-count-optimized', d.optimized);
					setCount('lw-img-count-skipped', d.skipped);
					setCount('lw-img-count-failed', d.failed);

					if (d.state === 'running') {
						var line = d.processed + ' / ' + d.total + ' — ' +
							d.optimized + ' optimized, ' + d.skipped + ' skipped, ' + d.failed + ' failed';

						if (d.elapsed > 0) {
							line += ' · ' + formatDuration(d.elapsed) + ' elapsed';
							if (d.processed > 0 && d.total > d.processed) {
								var eta = (d.total - d.processed) * (d.elapsed / d.processed);
								line += ' · ~' + formatDuration(eta) + ' left';
							}
						}

						if (d.current) {
							line += ' · Now: ' + d.current;
						}

						setStatus(line);
						window.setTimeout(poll, 3000);
					} else if (!finished) {
						finished = true;
						setStatus('Finished — reloading…');
						window.location.reload();
					}
				})
				.catch(function () {
					// Transient hiccup (or logged-out session): retry slower.
					window.setTimeout(poll, 10000);
				});
		}

		poll();
	}

	function init() {
		initTabs();
		initBulk();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
