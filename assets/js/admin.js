/**
 * LW Img — admin tab handler.
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

	function initBulk() {
		var container = document.getElementById('lw-img-bulk');
		var button    = document.getElementById('lw-img-bulk-start');

		if (!container || !button || typeof window.ajaxurl === 'undefined') {
			return;
		}

		var statusEl = document.getElementById('lw-img-bulk-status');
		var bar      = document.getElementById('lw-img-bulk-bar');
		var barFill  = document.getElementById('lw-img-bulk-bar-fill');
		var logEl         = document.getElementById('lw-img-bulk-log');
		var total         = parseInt(container.getAttribute('data-remaining'), 10) || 0;
		var done          = 0;
		var optimized     = 0;
		var prevRemaining = Infinity;

		function setStatus(text) {
			if (statusEl) {
				statusEl.textContent = text;
			}
		}

		function appendLog(item) {
			if (!logEl) {
				return;
			}
			var li = document.createElement('li');
			li.className = 'lw-img-bulk-log--' + item.result;
			li.textContent = '#' + item.id + ' ' + (item.title || '') + ' — ' + item.result + ' (' + item.detail + ')';
			logEl.insertBefore(li, logEl.firstChild);
		}

		function step() {
			var body = new FormData();
			body.append('action', 'lw_img_bulk_step');
			body.append('nonce', container.getAttribute('data-nonce'));

			fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
				.then(function (response) { return response.json(); })
				.then(function (payload) {
					if (!payload || !payload.success) {
						throw new Error(payload && payload.data && payload.data.message ? payload.data.message : 'request failed');
					}

					payload.data.processed.forEach(appendLog);
					done += payload.data.processed.length;
					payload.data.processed.forEach(function (item) {
						if (item.result === 'optimized') {
							optimized += 1;
						}
					});

					var remaining = payload.data.remaining;
					if (barFill && total > 0) {
						barFill.style.width = Math.min(100, Math.round(((total - remaining) / total) * 100)) + '%';
					}
					setStatus(remaining + ' remaining…');

					// Guard against a stalled queue: skipped/failed images stay
					// "unoptimized", so a non-decreasing remaining count means the
					// rest cannot be processed — continuing would loop forever.
					var stalled = remaining >= prevRemaining;
					prevRemaining = remaining;

					if (remaining > 0 && payload.data.processed.length > 0 && !stalled) {
						step();
					} else if (remaining > 0) {
						setStatus('Done — ' + optimized + ' optimized, ' + remaining + ' image(s) could not be processed (see the Log tab).');
						button.disabled = false;
					} else {
						setStatus('Done — ' + optimized + ' of ' + done + ' image(s) optimized.');
						button.disabled = false;
					}
				})
				.catch(function (err) {
					setStatus('Error: ' + err.message);
					button.disabled = false;
				});
		}

		button.addEventListener('click', function () {
			button.disabled = true;
			if (bar) {
				bar.hidden = false;
			}
			setStatus('Starting…');
			step();
		});
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
