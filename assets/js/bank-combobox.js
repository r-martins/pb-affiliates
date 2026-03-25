/**
 * Combobox: busca em lista de bancos (api-bancos JSON).
 */
(function () {
	'use strict';

	var cfg = typeof pbAffBankCombo !== 'undefined' ? pbAffBankCombo : {};
	var FALLBACK_URL =
		'https://cdn.jsdelivr.net/gh/r-martins/api-bancos@master/json/all.json';
	var URL = (cfg.url && String(cfg.url).trim()) || FALLBACK_URL;
	var MAX = 25;

	function padDigits(str, len) {
		var s = String(str || '');
		while (s.length < len) {
			s = '0' + s;
		}
		return s;
	}

	function padBankCode(str) {
		var t = String(str || '').trim();
		if (!t) {
			return '';
		}
		var digits = t.replace(/\D/g, '');
		if (!digits) {
			return '';
		}
		return digits.length <= 3 ? padDigits(digits, 3) : digits;
	}

	function validBanks(list) {
		if (!Array.isArray(list)) {
			return [];
		}
		return list.filter(function (b) {
			var c = b && b.numero_codigo ? String(b.numero_codigo).trim() : '';
			return c && c.toLowerCase() !== 'n/a';
		});
	}

	function formatLine(b) {
		var code = String(b.numero_codigo || '').trim();
		var name = String(b.nome_extenso || '').trim();
		return code + ' - ' + name;
	}

	function normalizeSearch(s) {
		var t = String(s || '').toLowerCase();
		try {
			t = t.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
		} catch (e) {}
		return t;
	}

	function filterBanks(banks, query) {
		var q = normalizeSearch(query);
		if (!q) {
			return banks.slice(0, MAX);
		}
		var out = [];
		for (var i = 0; i < banks.length && out.length < MAX; i++) {
			var b = banks[i];
			var code = String(b.numero_codigo || '').trim();
			var codeNorm = normalizeSearch(code);
			var ext = normalizeSearch(b.nome_extenso || '');
			var red = normalizeSearch(b.nome_reduzido || '');
			var codeDigits = normalizeSearch(code.replace(/\D/g, ''));
			if (
				codeNorm.indexOf(q) === 0 ||
				codeDigits.indexOf(q) !== -1 ||
				ext.indexOf(q) !== -1 ||
				red.indexOf(q) !== -1
			) {
				out.push(b);
			}
		}
		return out;
	}

	function findByCode(banks, code) {
		var p = padBankCode(code);
		if (!p) {
			return null;
		}
		for (var i = 0; i < banks.length; i++) {
			if (padBankCode(banks[i].numero_codigo) === p) {
				return banks[i];
			}
		}
		return null;
	}

	var cache = null;
	var loading = null;

	function loadBanks() {
		if (cache) {
			return Promise.resolve(cache);
		}
		if (loading) {
			return loading;
		}
		loading = fetch(URL, {
			credentials: 'omit',
			cache: 'default',
			mode: 'cors',
		})
			.then(function (r) {
				if (!r.ok) {
					throw new Error('fetch');
				}
				return r.json();
			})
			.then(function (data) {
				cache = validBanks(data);
				loading = null;
				return cache;
			})
			.catch(function () {
				loading = null;
				throw new Error('load');
			});
		return loading;
	}

	function closeDropdown(dd) {
		dd.hidden = true;
		dd.innerHTML = '';
	}

	function renderDropdown(dd, banks, query, onPick) {
		dd.innerHTML = '';
		if (!banks.length) {
			var liEmpty = document.createElement('li');
			liEmpty.className = 'pb-aff-bank-msg';
			liEmpty.setAttribute('role', 'presentation');
			liEmpty.textContent = cfg.empty || '—';
			dd.appendChild(liEmpty);
			dd.hidden = false;
			return;
		}
		banks.forEach(function (b) {
			var li = document.createElement('li');
			li.setAttribute('role', 'none');
			var btn = document.createElement('button');
			btn.type = 'button';
			btn.className = 'pb-aff-bank-option';
			btn.setAttribute('role', 'option');
			btn.textContent = formatLine(b);
			btn.addEventListener('mousedown', function (e) {
				e.preventDefault();
			});
			btn.addEventListener('click', function () {
				onPick(b);
			});
			li.appendChild(btn);
			dd.appendChild(li);
		});
		dd.hidden = false;
	}

	function initField(wrap) {
		if (wrap.getAttribute('data-pb-bank-init') === '1') {
			return;
		}
		var hidden = wrap.querySelector('input[name="pb_affiliate_bank_code"]');
		var search = wrap.querySelector('.pb-aff-bank-search');
		var dd = wrap.querySelector('.pb-aff-bank-dropdown');
		if (!hidden || !search || !dd) {
			return;
		}
		wrap.setAttribute('data-pb-bank-init', '1');

		var debounceTimer;

		function applyBank(b) {
			if (!b) {
				hidden.value = '';
				search.value = '';
				return;
			}
			hidden.value = String(b.numero_codigo || '').trim();
			search.value = formatLine(b);
		}

		function tryResolveFromInput() {
			var v = search.value.trim();
			if (!v) {
				hidden.value = '';
				return;
			}
			var dash = v.indexOf(' - ');
			if (dash !== -1) {
				var codePart = v.slice(0, dash).trim();
				loadBanks().then(function (banks) {
					var found = findByCode(banks, codePart);
					if (found) {
						applyBank(found);
					}
				});
				return;
			}
			loadBanks().then(function (banks) {
				var found = findByCode(banks, v);
				if (found) {
					applyBank(found);
				}
			});
		}

		function scheduleSearch() {
			clearTimeout(debounceTimer);
			var q = search.value;
			debounceTimer = setTimeout(function () {
				loadBanks()
					.then(function (banks) {
						var list = filterBanks(banks, q);
						renderDropdown(dd, list, q, function (b) {
							applyBank(b);
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
			}, 120);
		}

		loadBanks()
			.then(function (banks) {
				var code = hidden.value.trim();
				if (code) {
					var found = findByCode(banks, code);
					if (found) {
						search.value = formatLine(found);
					} else {
						search.value = code;
					}
				}
			})
			.catch(function () {
				var msg = document.createElement('div');
				msg.className = 'pb-aff-bank-msg is-error';
				msg.textContent = cfg.error || '';
				wrap.insertBefore(msg, dd);
			});

		search.addEventListener('input', scheduleSearch);

		search.addEventListener('focus', function () {
			scheduleSearch();
		});

		search.addEventListener('blur', function () {
			setTimeout(function () {
				closeDropdown(dd);
				tryResolveFromInput();
			}, 180);
		});

		document.addEventListener('click', function (e) {
			if (!wrap.contains(e.target)) {
				closeDropdown(dd);
			}
		});
	}

	function boot() {
		var nodes = document.querySelectorAll('.pb-aff-bank-field[data-pb-bank-combo]');
		nodes.forEach(initField);
	}

	function runBoot() {
		boot();
		if (document.querySelectorAll('.pb-aff-bank-field[data-pb-bank-combo]').length === 0) {
			setTimeout(boot, 500);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runBoot);
	} else {
		runBoot();
	}
})();
