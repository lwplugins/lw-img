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

		// In-content links (defaults chips, "Stats" etc.) that jump to a tab.
		document.addEventListener('click', function (e) {
			var link = e.target.closest('a.lw-img-goto');
			if (!link) {
				return;
			}
			var tabId = (link.getAttribute('href') || '').substring(1);
			var found = false;
			tabLinks.forEach(function (navLink) {
				if (navLink.getAttribute('href').substring(1) === tabId) {
					found = true;
				}
			});
			if (found) {
				e.preventDefault();
				activateTab(tabId);
				history.replaceState(null, '', '#' + tabId);
			}
		});
	}

	// The run itself happens server-side via WP-Cron; this only keeps the
	// progress display fresh while the tab is open.
	function initBulk() {
		var container = document.getElementById('lw-img-bulk');

		if (!container || container.getAttribute('data-running') !== '1' || typeof window.ajaxurl === 'undefined') {
			return;
		}

		var finished = false;

		function setText(id, text) {
			var el = document.getElementById(id);
			if (el) {
				el.textContent = text;
			}
		}

		function setWidth(id, pct) {
			var el = document.getElementById(id);
			if (el) {
				el.style.width = Math.max(0, Math.min(100, pct)) + '%';
			}
		}

		function formatDuration(seconds) {
			seconds = Math.max(0, Math.round(seconds));
			var d = Math.floor(seconds / 86400);
			var h = Math.floor((seconds % 86400) / 3600);
			var m = Math.floor((seconds % 3600) / 60);
			var s = seconds % 60;
			if (d > 0) {
				return d + 'd ' + h + 'h';
			}
			if (h > 0) {
				return h + 'h ' + m + 'm';
			}
			if (m > 0) {
				return m + 'm ' + s + 's';
			}
			return s + 's';
		}

		function formatBytes(bytes) {
			if (bytes >= 1073741824) {
				return (bytes / 1073741824).toFixed(1) + ' GB';
			}
			if (bytes >= 1048576) {
				return (bytes / 1048576).toFixed(1) + ' MB';
			}
			if (bytes >= 1024) {
				return Math.round(bytes / 1024) + ' KB';
			}
			return bytes + ' B';
		}

		function renderFeed(entries) {
			var feed = document.getElementById('lw-img-feed');
			if (!feed) {
				return;
			}
			feed.textContent = '';
			entries.forEach(function (entry) {
				var li = document.createElement('li');

				var t = document.createElement('span');
				t.className = 'lw-img-feed-t';
				t.textContent = entry.ts ? new Date(entry.ts * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '';

				var f = document.createElement('span');
				f.className = 'lw-img-feed-f';
				f.textContent = entry.label || '—';

				var chip = document.createElement('span');
				chip.className = 'lw-img-chip lw-img-chip-' +
					(entry.result === 'optimized' ? 'ok' : (entry.result === 'failed' ? 'fail' : 'skip'));
				chip.textContent = entry.result === 'optimized' ? entry.detail : entry.result;

				li.appendChild(t);
				li.appendChild(f);
				li.appendChild(chip);
				feed.appendChild(li);
			});
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
					var total = Math.max(1, d.total);

					// Hero: percent + segmented bar.
					setText('lw-img-hero-pct', ((d.processed / total) * 100).toFixed(1));
					setText('lw-img-hero-processed', d.processed.toLocaleString());
					setText('lw-img-hero-total', d.total.toLocaleString());
					setWidth('lw-img-seg-ok', (d.optimized / total) * 100);
					setWidth('lw-img-seg-skip', (d.skipped / total) * 100);
					setWidth('lw-img-seg-fail', (d.failed / total) * 100);

					// Library tiles (run-relative approximation for pending).
					setText('lw-img-count-pending', Math.max(0, d.total - d.processed).toLocaleString());
					setText('lw-img-count-optimized', d.optimized.toLocaleString());
					setText('lw-img-count-skipped', d.skipped.toLocaleString());
					setText('lw-img-count-failed', d.failed.toLocaleString());

					// Meta row.
					if (d.elapsed > 0) {
						setText('lw-img-meta-elapsed', formatDuration(d.elapsed));
						var perMin = d.processed / (d.elapsed / 60);
						setText('lw-img-meta-speed', perMin >= 1 ? Math.round(perMin) + ' / min' : (perMin * 60).toFixed(1) + ' / h');
						if (d.processed > 0 && d.total > d.processed) {
							setText('lw-img-meta-eta', '~' + formatDuration((d.total - d.processed) * (d.elapsed / d.processed)));
						}
					}
					setText('lw-img-meta-saved', formatBytes(d.bytes_saved));

					if (d.current) {
						setText('lw-img-now-item', d.current);
					}
					renderFeed(d.recent || []);

					if (d.state === 'running') {
						window.setTimeout(poll, 3000);
					} else if (!finished) {
						finished = true;
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

	// Show/hide toggle next to the API key field on the General tab.
	function initKeyToggle() {
		var toggle = document.querySelector('.lw-img-key-toggle');
		var input  = document.getElementById('api_key');

		if (!toggle || !input) {
			return;
		}

		toggle.addEventListener('click', function () {
			var show  = input.type === 'password';
			var icon  = this.querySelector('.dashicons');
			var label = show ? this.getAttribute('data-label-hide') : this.getAttribute('data-label-show');

			input.type = show ? 'text' : 'password';
			this.setAttribute('aria-pressed', show ? 'true' : 'false');
			if (label) {
				this.setAttribute('aria-label', label);
				this.setAttribute('title', label);
			}
			if (icon) {
				icon.classList.toggle('dashicons-visibility', !show);
				icon.classList.toggle('dashicons-hidden', show);
			}
		});
	}

	// Upload tab: master-toggle dimming, segment descriptions, pattern chips.
	function initUpload() {
		var master = document.getElementById('auto_convert');
		var state  = document.getElementById('lw-img-up-state');

		if (master && state) {
			master.addEventListener('change', function () {
				var sections = document.querySelector('.lw-img-up-sections');
				if (sections) {
					sections.classList.toggle('lw-img-up-dim', !this.checked);
				}
				state.textContent = this.checked ? state.getAttribute('data-on') : state.getAttribute('data-off');
				state.classList.toggle('lw-img-up-state-off', !this.checked);
			});
		}

		document.querySelectorAll('.lw-img-seg-ctl').forEach(function (seg) {
			seg.addEventListener('change', function (e) {
				var desc = seg.parentElement ? seg.parentElement.querySelector('.lw-img-seg-desc') : null;
				if (desc && e.target && e.target.getAttribute('data-desc')) {
					desc.textContent = e.target.getAttribute('data-desc');
				}
			});
		});

		document.querySelectorAll('.lw-img-up-hint').forEach(function (hint) {
			hint.addEventListener('click', function () {
				var area = document.getElementById('exclusion_patterns');
				if (!area) {
					return;
				}
				var pattern = this.getAttribute('data-pattern') || '';
				area.value = area.value.replace(/\s+$/, '');
				area.value += (area.value ? '\n' : '') + pattern;
				area.focus();
			});
		});
	}

	// Backup tab: master-toggle status + danger card, retention presets.
	function initBackup() {
		var master = document.getElementById('backup_enabled');
		var state  = document.getElementById('lw-img-bk-state');
		var danger = document.getElementById('lw-img-bk-danger');

		if (master && state) {
			master.addEventListener('change', function () {
				state.textContent = this.checked ? state.getAttribute('data-on') : state.getAttribute('data-off');
				state.classList.toggle('lw-img-bk-state-off', !this.checked);
				if (danger) {
					danger.classList.toggle('lw-img-hidden', this.checked);
				}
			});
		}

		var days = document.getElementById('backup_retention_days');
		var presets = document.querySelectorAll('.lw-img-bk-preset');

		function syncPresets() {
			presets.forEach(function (preset) {
				preset.classList.toggle('lw-img-bk-preset-on', days && preset.getAttribute('data-days') === days.value);
			});
		}

		presets.forEach(function (preset) {
			preset.addEventListener('click', function () {
				if (days) {
					days.value = this.getAttribute('data-days');
				}
				syncPresets();
			});
		});

		if (days) {
			days.addEventListener('input', syncPresets);
		}
	}

	function init() {
		initTabs();
		initBulk();
		initKeyToggle();
		initUpload();
		initBackup();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
