/**
 * Máscara dinâmica CPF (11) / CNPJ (14) no campo de documento do afiliado.
 */
(function () {
	'use strict';

	function onlyDigits(s) {
		return String(s || '').replace(/\D/g, '');
	}

	function formatCpf(d) {
		var v = onlyDigits(d).slice(0, 11);
		if (!v.length) {
			return '';
		}
		if (v.length <= 3) {
			return v;
		}
		if (v.length <= 6) {
			return v.slice(0, 3) + '.' + v.slice(3);
		}
		if (v.length <= 9) {
			return v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6);
		}
		return v.slice(0, 3) + '.' + v.slice(3, 6) + '.' + v.slice(6, 9) + '-' + v.slice(9, 11);
	}

	function formatCnpj(d) {
		var v = onlyDigits(d).slice(0, 14);
		if (!v.length) {
			return '';
		}
		if (v.length <= 2) {
			return v;
		}
		if (v.length <= 5) {
			return v.slice(0, 2) + '.' + v.slice(2);
		}
		if (v.length <= 8) {
			return v.slice(0, 2) + '.' + v.slice(2, 5) + '.' + v.slice(5);
		}
		if (v.length <= 12) {
			return v.slice(0, 2) + '.' + v.slice(2, 5) + '.' + v.slice(5, 8) + '/' + v.slice(8);
		}
		return (
			v.slice(0, 2) +
			'.' +
			v.slice(2, 5) +
			'.' +
			v.slice(5, 8) +
			'/' +
			v.slice(8, 12) +
			'-' +
			v.slice(12, 14)
		);
	}

	function formatDoc(raw) {
		var digits = onlyDigits(raw);
		if (digits.length <= 11) {
			return formatCpf(digits);
		}
		return formatCnpj(digits);
	}

	function bind(el) {
		if (!el || el.getAttribute('data-pb-doc-mask') === '1') {
			return;
		}
		el.setAttribute('data-pb-doc-mask', '1');
		el.setAttribute('inputmode', 'numeric');
		el.setAttribute('autocomplete', 'off');

		function applyFromInput() {
			var next = formatDoc(el.value);
			if (el.value !== next) {
				el.value = next;
			}
		}

		if (el.value) {
			el.value = formatDoc(el.value);
		}

		el.addEventListener('input', applyFromInput);
		el.addEventListener('blur', function () {
			el.value = formatDoc(el.value);
		});
	}

	function boot() {
		document.querySelectorAll('input[name="pb_affiliate_bank_document"]').forEach(bind);
	}

	function runBoot() {
		boot();
		if (!document.querySelector('input[name="pb_affiliate_bank_document"]')) {
			setTimeout(boot, 400);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', runBoot);
	} else {
		runBoot();
	}
})();
