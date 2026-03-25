/**
 * Busca de afiliados (ID, código, e-mail, nome) no formulário do cupom.
 */
(function () {
	'use strict';

	var cfg =
		typeof pbAffCouponAffiliate !== 'undefined' ? pbAffCouponAffiliate : {};
	var MAX = 25;

	function fetchResults(q) {
		var body = new URLSearchParams();
		body.set('action', 'pb_affiliates_search_affiliates');
		body.set('nonce', cfg.nonce || '');
		body.set('term', q);
		return fetch(cfg.ajaxUrl || '', {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body: body.toString(),
		})
			.then(function (r) {
				return r.json();
			})
			.then(function (data) {
				if (!data || !data.success || !data.data || !Array.isArray(data.data.results)) {
					throw new Error('bad');
				}
				return data.data.results.slice(0, MAX);
			});
	}

	function closeDropdown(dd) {
		dd.hidden = true;
		dd.innerHTML = '';
	}

	function renderDropdown(dd, rows, onPick) {
		dd.innerHTML = '';
		if (!rows.length) {
			var liEmpty = document.createElement('li');
			liEmpty.className = 'pb-aff-bank-msg';
			liEmpty.setAttribute('role', 'presentation');
			liEmpty.textContent = cfg.empty || '—';
			dd.appendChild(liEmpty);
			dd.hidden = false;
			return;
		}
		rows.forEach(function (row) {
			var li = document.createElement('li');
			li.setAttribute('role', 'none');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pb-aff-bank-option';
			btn.setAttribute('role', 'option');
			btn.textContent = row.label || '';
			btn.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			btn.addEventListener('click', function () {
				onPick(row);
			});
			li.appendChild(btn);
			dd.appendChild(li);
		});
		dd.hidden = false;
	}

	function initField(wrap) {
		if (wrap.getAttribute('data-pb-aff-coupon-init') === '1') {
			return;
		}
		var hidden = wrap.querySelector('input[name="_pb_affiliate_id"]');
		var search = wrap.querySelector('.pb-aff-coupon-affiliate-search');
		var dd = wrap.querySelector('.pb-aff-coupon-affiliate-dropdown');
		if (!hidden || !search || !dd) {
			return;
		}
		wrap.setAttribute('data-pb-aff-coupon-init', '1');

		var debounceTimer;

		function applyRow(row) {
			if (!row || !row.id) {
				hidden.value = '';
				search.value = '';
				return;
			}
			hidden.value = String(parseInt(row.id, 10) || '');
			search.value = row.label || '';
		}

		function scheduleSearch() {
			clearTimeout(debounceTimer);
			var q = search.value.trim();
			debounceTimer = setTimeout(function () {
				if (!q) {
					closeDropdown(dd);
					return;
				}
				fetchResults(q)
					.then(function (rows) {
						renderDropdown(dd, rows, function (row) {
							applyRow(row);
							closeDropdown(dd);
							search.focus();
						});
					})
					.catch(function () {
						dd.innerHTML = '';
						var liErr = document.createElement('li');
						liErr.className = 'pb-aff-bank-msg is-error';
						liErr.setAttribute('role', 'presentation');
						liErr.textContent = cfg.error || '';
						dd.appendChild(liErr);
						dd.hidden = false;
					});
			}, 150);
		}

		if (hidden.value && search.getAttribute('data-pb-display')) {
			search.value = search.getAttribute('data-pb-display');
		}
		if (cfg.placeholder) {
			search.setAttribute('placeholder', cfg.placeholder);
		}

		search.addEventListener('input', function () {
			if (!search.value.trim()) {
				hidden.value = '';
			}
			scheduleSearch();
		});
		search.addEventListener('focus', scheduleSearch);
		search.addEventListener('blur', function () {
			setTimeout(function () {
				closeDropdown(dd);
			}, 180);
		});
		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) {
				closeDropdown(dd);
			}
		});
	}

	function boot() {
		document
			.querySelectorAll('.pb-aff-affiliate-coupon-field[data-pb-affiliate-coupon-combo]')
			.forEach(initField);
	}

	function runBoot() {
		boot();
		if (
			document.querySelectorAll(
				'.pb-aff-affiliate-coupon-field[data-pb-affiliate-coupon-combo]'
			).length === 0
		) {
			setTimeout(boot, 500);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runBoot);
	} else {
		runBoot();
	}
})();
