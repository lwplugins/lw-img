/**
 * LW Img — ask what to do with the plugin's data before deactivating.
 */

(function () {
	'use strict';

	var cfg = window.lwImgDeactivate;
	if (!cfg || typeof window.ajaxurl === 'undefined') {
		return;
	}

	var row  = document.querySelector('tr[data-plugin="' + cfg.plugin + '"]');
	var link = row ? row.querySelector('.deactivate a') : null;
	var box  = document.getElementById('lw-img-deactivate');
	if (!link || !box) {
		return;
	}

	function close() {
		box.hidden = true;
	}

	link.addEventListener('click', function (e) {
		e.preventDefault();
		box.hidden = false;
		var checked = box.querySelector('input[name="lw_img_delete"]:checked');
		if (checked) {
			checked.focus();
		}
	});

	document.getElementById('lw-img-deact-cancel').addEventListener('click', close);
	box.addEventListener('click', function (e) {
		if (e.target === box) {
			close();
		}
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !box.hidden) {
			close();
		}
	});

	document.getElementById('lw-img-deact-go').addEventListener('click', function () {
		var choice = box.querySelector('input[name="lw_img_delete"]:checked');
		var body   = new FormData();
		body.append('action', cfg.action);
		body.append('nonce', cfg.nonce);
		body.append('delete', choice ? choice.value : '0');
		this.disabled = true;

		// Whatever the save returns, continue with the deactivation: an
		// unsaved answer falls back to "keep", which is the safe default.
		fetch(window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
			.catch(function () {})
			.then(function () {
				window.location.href = link.href;
			});
	});
})();
