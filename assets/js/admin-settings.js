/**
 * PB Afiliados — settings: conditional rows + payment mode cards.
 */
(function () {
	'use strict';

	function getModeRadios() {
		return document.querySelectorAll('input[name="pb_affiliates_settings[payment_mode]"]');
	}

	function getSelectedMode() {
		var r = getModeRadios();
		for (var i = 0; i < r.length; i++) {
			if (r[i].checked) {
				return r[i].value;
			}
		}
		return 'manual';
	}

	function togglePaymentRows() {
		var mode = getSelectedMode();
		var splitRow = document.getElementById('pb-aff-row-split-release');
		var manualMin = document.getElementById('pb-aff-row-manual-min');
		var manualRet = document.getElementById('pb-aff-row-manual-retention');

		if (splitRow) {
			splitRow.classList.toggle('pb-aff-row-hidden', mode !== 'split');
		}
		if (manualMin) {
			manualMin.classList.toggle('pb-aff-row-hidden', mode !== 'manual');
		}
		if (manualRet) {
			manualRet.classList.toggle('pb-aff-row-hidden', mode !== 'manual');
		}

		document.querySelectorAll('.pb-aff-payment-mode').forEach(function (el) {
			var input = el.querySelector('input[type="radio"]');
			el.classList.toggle('is-selected', input && input.checked);
		});
	}

	function init() {
		getModeRadios().forEach(function (radio) {
			radio.addEventListener('change', togglePaymentRows);
		});
		document.querySelectorAll('.pb-aff-payment-mode').forEach(function (label) {
			label.addEventListener('click', function (e) {
				if (e.target.tagName !== 'INPUT') {
					var input = label.querySelector('input[type="radio"]');
					if (input) {
						input.checked = true;
						togglePaymentRows();
					}
				}
			});
		});
		togglePaymentRows();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
